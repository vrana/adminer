<?php
/** Driver for https://api-docs.igdb.com/
* @link https://demo.adminer.org/igdb/?igdb=IGDB&db=api
* username: your Client-ID
* password: your access token from https://id.twitch.tv/oauth2/token
* @link https://www.adminer.org/static/plugins/igdb.png
*/

namespace Adminer;

add_driver("igdb", "APICalypse");

if (isset($_GET["igdb"])) {
	define('Adminer\DRIVER', "igdb");

	class Db extends SqlDb {
		public $extension = "json";
		public $server_info = "v4";
		private $username;
		private $password;

		function attach($server, $username, $password): string {
			$this->username = $username;
			$this->password = $password;
			return '';
		}

		function select_db($database) {
			return ($database == "api");
		}

		function request($endpoint, $query, $method = 'POST') {
			$context = stream_context_create(array('http' => array(
				'method' => $method,
				'header' => array(
					"Content-Type: text/plain",
					"Client-ID: $this->username",
					"Authorization: Bearer $this->password",
				),
				'content' => $query,
				'ignore_errors' => true,
			)));
			$response = file_get_contents("https://api.igdb.com/v4/$endpoint", false, $context);
			$return = json_decode($response, true);
			if ($http_response_header[0] != 'HTTP/1.1 200 OK') {
				if (is_array($return)) {
					foreach (is_array($return[0]) ? $return : array($return) as $rows) {
						foreach ($rows as $key => $val) {
							$this->error .= '<b>' . h($key) . ':</b> ' . (is_url($val) ? '<a href="' . h($val) . '"' . target_blank() . '>' . h($val) . '</a>' : h($val)) . '<br>';
						}
					}
				} else {
					$this->error = htmlspecialchars(strip_tags($response), 0, null, false);
				}
				return false;
			}
			return $return;
		}

		function query($query, $unbuffered = false) {
			if (preg_match('~^SELECT COUNT\(\*\) FROM (\w+)( WHERE ((MATCH \(search\) AGAINST \((.+)\))|.+))?$~', $query, $match)) {
				return new Result(array($this->request("$match[1]/count", ($match[5] ? 'search "' . addcslashes($match[5], '\\"') . '";'
					: ($match[3] ? 'where ' . str_replace(' AND ', ' & ', $match[3]) . ';'
					: ''
				)))));
			}
			if (preg_match('~^\s*(GET|POST|DELETE)\s+([\w/?=]+)\s*;\s*(.*)$~s', $query, $match)) {
				$endpoint = $match[2];
				$response = $this->request($endpoint, $match[3], $match[1]);
				if ($response === false) {
					return $response;
				}
				$return = new Result(is_array($response[0]) ? $response : array($response));
				$return->table = $endpoint;
				if ($endpoint == 'multiquery') {
					$return->results = $response;
				}
				return $return;
			}
			$this->error = "Syntax:<br>POST &lt;endpoint>; fields ...;";
			return false;
		}

		function store_result() {
			if ($this->multi && ($result = current($this->multi->results))) {
				echo "<h3>" . h($result['name']) . "</h3>\n";
				$this->multi->__construct($result['count'] ? array(array('count' => $result['count'])) : $result['result']);
			}
			return $this->multi;
		}

		function next_result(): bool {
			return $this->multi && next($this->multi->results);
		}

		function quote($string): string {
			return $string;
		}
	}

	class Result {
		public $num_rows;
		public $table;
		public $results = array();
		private $result;
		private $fields;

		function __construct($result) {
			$keys = array();
			foreach ($result as $i => $row) {
				foreach ($row as $key => $val) {
					$keys[$key] = null;
					if (is_array($val) && is_int($val[0])) {
						$result[$i][$key] = "(" . implode(",", $val) . ")";
					}
				}
			}
			foreach ($result as $i => $row) {
				$result[$i] = array_merge($keys, $row);
			}
			$this->result = $result;
			$this->num_rows = count($result);
			$this->fields = array_keys(idx($result, 0, array()));
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
			return ($field != '' ? (object) array('name' => $field, 'type' => 15, 'charsetnr' => 0, 'orgtable' => $this->table) : false);
		}
	}

	class Driver extends SqlDriver {
		static $extensions = array("json");
		static $jush = "igdb";
		private static $docsFilename = __DIR__ . DIRECTORY_SEPARATOR . 'igdb-api.html';

		public $delimiter = ";;";
		public $operators = array("=", "<", ">", "<=", ">=", "!=", "~");

		public $tables = array();
		public $links = array();
		public $fields = array();
		public $foreignKeys = array();
		public $foundRows = null;

		static function connect(string $server, string $username, string $password) {
			if (!file_exists(self::$docsFilename)) {
				return "Download https://api-docs.igdb.com/ and save it as " . self::$docsFilename; // copy() doesn't work - bot protection
			}
			return parent::connect($server, $username, $password);
		}

		function __construct($connection) {
			parent::__construct($connection);
			libxml_use_internal_errors(true);
			$dom = new \DOMDocument();
			$dom->loadHTMLFile(self::$docsFilename);
			$xpath = new \DOMXPath($dom);
			$els = $xpath->query('//div[@class="content"]/*');
			$link = '';
			foreach ($els as $i => $el) {
				if ($el->tagName == 'h2') {
					$link = $el->getAttribute('id');
				}
				if ($el->nodeValue == 'Request Path') {
					$table = preg_replace('~^https://api.igdb.com/v4/~', '', $els[$i+1]->firstElementChild->nodeValue);
					$comment = $els[$i-1]->tagName == 'p' ? $els[$i-1]->nodeValue : '';
					if (preg_match('~^DEPRECATED!~', $comment)) {
						continue;
					}
					$this->fields[$table]['id'] = array('full_type' => 'bigserial', 'comment' => '');
					$this->links[$link] = $table;
					$this->tables[$table] = array('Name' => $table, 'Comment' => $comment);
					foreach ($xpath->query('tbody/tr', $els[$i+2]) as $tr) {
						$tds = $xpath->query('td', $tr);
						$field = $tds[0]->nodeValue;
						$comment = $tds[2]->nodeValue;
						if ($field != 'checksum' && $field != 'content_descriptions' && !preg_match('~^DEPRECATED!~', $comment)) {
							$this->fields[$table][$field] = array(
								'full_type' => str_replace('  ', ' ', $tds[1]->nodeValue),
								'comment' => str_replace('  ', ' ', $comment),
							);
							$ref = $xpath->query('a/@href', $tds[1]);
							if (count($ref) && !in_array($ref[0]->value, array('#game-version-feature-enums', '#tag-numbers'))) {
								$this->foreignKeys[$table][$field] = substr($ref[0]->value, 1);
							} elseif ($field === 'game_id') { // game_time_to_beats, popularity_primitives
								$this->foreignKeys[$table][$field] = 'game';
							}
						}
					}
					uksort($this->fields[$table], function ($a, $b) use ($table) {
						return (($b == 'id') - ($a == 'id'))
							?: (($b == 'name') - ($a == 'name'))
							?: (($a == 'updated_at') - ($b == 'updated_at'))
							?: (($a == 'created_at') - ($b == 'created_at'))
							?: (!idx($this->foreignKeys[$table], $b) - !idx($this->foreignKeys[$table], $a))
							?: ($a < $b ? -1 : 1)
						;
					});
				}
			}
			$this->tables['webhooks'] = array('Name' => 'webhooks', 'Comment' => 'Webhooks allow us to push data to you when it is added, updated, or deleted.');
			$this->links['webhooks'] = 'webhooks';
			$this->fields['webhooks'] = array(
				'endpoint' => array(
					'full_type' => 'String',
					'comment' => 'Specify what type of data you want from your webhook.',
					'privileges' => array('insert' => 1),
				),
				'id' => array('comment' => 'A unique ID for the webhook'),
				'url' => array(
					'full_type' => 'String',
					'length' => '100',
					'comment' => 'Your prepared url that is ready to accept data from us',
					'privileges' => array('select' => 1, 'insert' => 1),
				),
				'method' => array(
					'full_type' => 'enum',
					'length' => "('create','delete','update')",
					'comment' => 'The type of data you are expecting to your url, there are three types of methods',
					'privileges' => array('insert' => 1),
				),
				'category' => array('comment' => 'Based on the endpoint you chose'),
				'sub_category' => array('comment' => 'Based on your method (can be 0, 1, 2)'),
				'active' => array('comment' => 'Is the webhook currently active'),
				'api_key' => array('comment' => 'Displays the api key the webhook is connected to'),
				'secret' => array(
					'full_type' => 'String',
					'comment' => 'Your “secret” password for your webhook',
					'privileges' => array('select' => 1, 'insert' => 1),
				),
				'created_at' => array('comment' => 'Created at date'),
				'updated_at' => array('comment' => 'Updated at date'),
			);
		}

		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
			$query = '';
			$search = preg_match('~^MATCH \(search\) AGAINST \((.+)\)$~', $where[0], $match);
			if ($search) {
				$query = 'search "' . addcslashes($match[1], '\\"') . "\";\n";
				unset($where[0]);
			}
			foreach ($where as $i => $val) {
				$where[$i] = str_replace(' OR ', ' | ', $val);
			}
			$fields = array_keys($this->fields[$table]);
			$common = ($where ? "where " . implode(" & ", $where) . ";" : "");
			if ($table != 'webhooks') {
				$query .= "fields " . implode(",", $select == array('*') ? $fields : $select) . ";"
					. ($where ? "\n$common" : "")
					. ($order ? "\nsort " . strtolower(implode(",", $order)) . ";" : "")
					. "\nlimit $limit;"
					. ($page ? "\noffset " . ($page * $limit) . ";" : "")
				;
			}
			$start = microtime(true);
			$multi = (!$search && $table != 'webhooks' && array_key_exists($table, driver()->tables));
			$method = ($table == 'webhooks' ? 'GET' : 'POST');
			$return = ($multi
				? $this->conn->request('multiquery', "query $table \"result\" { $query };\nquery $table/count \"count\" { $common };")
				: $this->conn->request($table, $query, $method)
			);
			if ($print) {
				echo adminer()->selectQuery("$method $table;\n$query", $start);
			}
			if ($return === false) {
				return $return;
			}
			$this->foundRows = ($multi ? $return[1]['count'] : null);
			$return = ($multi ? $return[0]['result'] : $return);
			if ($return && $table != 'webhooks') {
				$keys = ($select != array('*') ? $select : $fields);
				$return[0] = array_merge(array_fill_keys($keys, null), $return[0]);
			}
			return new Result($return);
		}

		function insert($table, $set) {
			$content = array();
			foreach ($set as $key => $val) {
				if ($key != 'endpoint') {
					$content[] = urlencode($key) . '=' . urlencode($val);
				}
			}
			return queries("POST $set[endpoint]/$table; " . implode('&', $content));
		}

		function delete($table, $queryWhere, $limit = 0) {
			preg_match_all('~\bid = (\d+)~', $queryWhere, $matches);
			$this->conn->affected_rows = 0;
			foreach ($matches[1] as $id) {
				$result = queries("DELETE $table/$id;");
				if (!$result) {
					return false;
				}
				$row = $result->fetch_row();
				if (!$row[0]) {
					$this->conn->error = "ID $id not found.";
					return false;
				}
				$this->conn->affected_rows++;
			}
			return true;
		}

		function value($val, $field): ?string {
			return ($val && in_array($field['full_type'], array('Unix Time Stamp', 'datetime')) ? str_replace(' 00:00:00', '', gmdate('Y-m-d H:i:s', $val)) : $val);
		}

		function tableHelp($name, $is_view = false) {
			return strtolower("https://api-docs.igdb.com/#" . array_search($name, $this->links));
		}
	}

	function logged_user() {
		return $_GET["username"];
	}

	function get_databases($flush) {
		return array("api");
	}

	function collations() {
		return array();
	}

	function db_collation($db, $collations) {
	}

	function information_schema($db) {
	}

	function indexes($table, $connection2 = null) {
		$return = array(array("type" => "PRIMARY", "columns" => array("id")));
		if (in_array($table, array('characters', 'collections', 'games', 'platforms', 'themes'))) { // https://api-docs.igdb.com/#search-1
			$return[] = array("type" => "FULLTEXT", "columns" => array("search"));
		}
		return $return;
	}

	function fields($table) {
		$return = array();
		foreach (driver()->fields[$table] ?: array() as $key => $val) {
			$return[$key] = $val + array(
				"field" => $key,
				"type" => (preg_match('~^int|bool|enum~i', $val['full_type']) ? $val['full_type'] : 'varchar'), // shorten reference columns
				"privileges" => array("select" => 1) + ($table == 'webhooks' ? array() : array("where" => 1, "order" => 1)),
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
		return $query;
	}

	function idf_escape($idf) {
		return $idf;
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function foreign_keys($table) {
		$return = array();
		foreach (driver()->foreignKeys[$table] ?: array() as $key => $val) {
			$return[] = array(
				'table' => driver()->links[$val],
				'source' => array($key),
				'target' => array('id'),
			);
		}
		return $return;
	}

	function tables_list() {
		return array_fill_keys(array_keys(table_status()), 'table');
	}

	function table_status($name = "", $fast = false) {
		$tables = driver()->tables;
		return ($name != '' ? ($tables[$name] ? array($name => $tables[$name]) : array()) : $tables);
	}

	function count_tables($databases) {
		return array(reset($databases) => count(tables_list()));
	}

	function error() {
		return connection()->error;
	}

	function is_view($table_status) {
		return false;
	}

	function found_rows($table_status, $where) {
		return driver()->foundRows;
	}

	function fk_support($table_status) {
		return true;
	}

	function last_id($result): string {
		$row = $result->fetch_assoc();
		return (string) $row['id'];
	}

	function support($feature) {
		return in_array($feature, array('columns', 'comment', 'sql', 'table'));
	}
}
