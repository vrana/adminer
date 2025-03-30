<?php
/** Experimental driver for IMAP created just for fun. Features:
* - list mailboxes with number of messages (Rows) and unread messages (Data Free)
* - creating and dropping mailboxes work, truncate does expunge on all mailboxes
* - list messages in each mailbox - limit and offset works but there's no search and order
* - for each message, there's subject, from, to, date and some flags
* - editing the message shows some other information
* - deleting marks the message for deletion but doesn't expunge the mailbox
* - inserting or updating the message does nothing
* @link https://www.adminer.org/static/plugins/imap.png
*/

namespace Adminer;

add_driver("imap", "IMAP");

if (isset($_GET["imap"])) {
	define('Adminer\DRIVER', "imap");

	if (extension_loaded("imap")) {
		class Db extends SqlDb {
			public string $extension = "IMAP";
			public $server_info = "?"; // imap_mailboxmsginfo() or imap_check() don't return anything useful
			private $mailbox;
			private $imap;

			function attach(?string $server, string $username, string $password): string {
				$this->mailbox = "{" . "$server:993/ssl}"; // Adminer disallows specifying privileged port in server name
				$this->imap = @imap_open($this->mailbox, $username, $password, OP_HALFOPEN, 1);
				return ($this->imap ? '' : imap_last_error());
			}

			function select_db(string $database): bool {
				return ($database == "mail");
			}

			function query(string $query, bool $unbuffered = false) {
				if (preg_match('~DELETE FROM "(.+?)"~', $query)) {
					preg_match_all('~"uid" = (\d+)~', $query, $matches);
					return imap_delete($this->imap, implode(",", $matches[1]), FT_UID);
				} elseif (preg_match('~^SELECT COUNT\(\*\)\sFROM "(.+?)"~s', $query, $match)) {
					$status = table_status1($match[1]);
					return new Result(array(array($status["Rows"])));
				} elseif (preg_match('~^SELECT (.+)\sFROM "(.+?)"(?:\sWHERE "uid" = (\d+))?.*?(?:\sLIMIT (\d+)(?:\sOFFSET (\d+))?)?~s', $query, $match)) {
					list(, $columns, $table, $uid, $limit, $offset) = $match;
					imap_reopen($this->imap, $this->mailbox . $table);
					if ($uid) {
						$return = array((array) imap_fetchstructure($this->imap, $uid, FT_UID));
					} else {
						$count = imap_num_msg($this->imap);
						$range = ($offset + 1) . ":" . ($limit ? min($count, $offset + $limit) : $count);
						$return = array();
						$fields = fields($table);
						$columns = ($columns == "*" ? $fields : array_flip(explode(", ", $columns)));
						$empty = array_fill_keys(array_keys($fields), null);
						foreach (imap_fetch_overview($this->imap, $range) as $row) {
							// imap_utf8 doesn't work with some strings
							$row->subject = iconv_mime_decode($row->subject, 2, "utf-8");
							$row->from = iconv_mime_decode($row->from, 2, "utf-8");
							$row->to = iconv_mime_decode($row->to, 2, "utf-8");
							$row->udate = gmdate("Y-m-d H:i:s", $row->udate);
							$return[] = array_intersect_key(array_merge($empty, (array) $row), $columns);
						}
					}
					return new Result($return);
				}
				return false;
			}

			function quote(string $string): string {
				return $string;
			}

			function tables_list() {
				static $return;
				if ($return === null) {
					$return = array();
					foreach (imap_list($this->imap, $this->mailbox, "*") as $val) {
						$return[substr($val, strlen($this->mailbox))] = "table";
					}
				}
				return array_reverse($return);
			}

			function table_status($name, $fast) {
				if ($fast) {
					return array("Name" => $name);
				}
				$return = imap_status($this->imap, $this->mailbox . $name, SA_ALL);
				return array(
					"Name" => $name,
					"Rows" => $return->messages,
					"Auto_increment" => $return->uidnext,
					"Data_length" => $return->messages, // this is used on database overview
					"Data_free" => $return->unseen,
				);
			}

			function create($name) {
				return imap_createmailbox($this->imap, $this->mailbox . $name);
			}

			function drop($name) {
				return imap_deletemailbox($this->imap, $this->mailbox . $name);
			}

			function expunge() {
				return imap_expunge($this->imap);
			}
		}

		class Result {
			public $num_rows;
			private $result;
			private $fields;

			function __construct($result) {
				$this->result = $result;
				$this->num_rows = count($result);
				$this->fields = array_keys(idx($result, 0,  array()));
			}

			function fetch_assoc() {
				$row = current($this->result);
				next($this->result);
				return $row;
			}

			function fetch_row() {
				$row = $this->fetch_assoc();
				return ($row ? array_values($row) : false);
			}

			function fetch_field(): \stdClass {
				$field = current($this->fields);
				next($this->fields);
				return ($field != '' ? (object) array('name' => $field, 'type' => 15, 'charsetnr' => 0) : false);
			}
		}
	}

	class Driver extends SqlDriver {
		static array $extensions = array("imap");
		static string $jush = "imap";
		public array $insertFunctions = array("json");
	}

	function logged_user() {
		return $_GET["username"];
	}

	function get_databases($flush) {
		return array("mail");
	}

	function collations() {
		return array();
	}

	function db_collation($db, $collations) {
	}

	function information_schema($db) {
	}

	function indexes($table, $connection2 = null) {
		return array(array("type" => "PRIMARY", "columns" => array("uid")));
	}

	function fields($table) {
		$return = array();
		foreach (
			array( // taken from imap_fetch_overview
				'subject' => 'the messages subject',
				'from' => 'who sent it',
				'to' => 'recipient',
				'date' => 'when was it sent',
				'message_id' => 'Message-ID',
				'references' => 'is a reference to this message id',
				'in_reply_to' => 'is a reply to this message id',
				'size' => 'size in bytes',
				'uid' => 'UID the message has in the mailbox',
				'msgno' => 'message sequence number in the mailbox',
				'recent' => 'flagged as recent',
				'flagged' => 'flagged',
				'answered' => 'flagged as answered',
				'deleted' => 'flagged for deletion',
				'seen' => 'flagged as already read',
				'draft' => 'flagged as being a draft',
				'udate' => 'the GMT time of the arrival date',
			) as $name => $comment
		) {
			$return[$name] = array(
				"field" => $name,
				"type" => (preg_match('~^(size|uid|msgno)$~', $name) ? "int" : ""),
				"privileges" => array("select" => 1),
				"comment" => $comment,
			);
		}
		return $return;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function foreign_keys($table) {
		return array();
	}

	function tables_list() {
		return connection()->tables_list();
	}

	function table_status($name = "", $fast = false) {
		$return = array();
		foreach (($name != "" ? array($name => 1) : tables_list()) as $table => $type) {
			$return[$table] = connection()->table_status($table, $fast);
		}
		return $return;
	}

	function count_tables($databases) {
		return array(reset($databases) => count(tables_list()));
	}

	function error() {
		return h(connection()->error);
	}

	function is_view($table_status) {
		return false;
	}

	function found_rows($table_status, $where) {
		return $table_status["Rows"];
	}

	function fk_support($table_status) {
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		return connection()->create($name);
	}

	function drop_tables($tables) {
		$return = true;
		foreach ($tables as $name) {
			$return = $return && connection()->drop($name);
		}
		return $return;
	}

	function truncate_tables($tables) {
		return connection()->expunge();
	}

	function support($feature) {
		return false;
	}
}
