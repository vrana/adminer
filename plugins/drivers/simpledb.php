<?php
namespace Adminer;

add_driver("simpledb", "SimpleDB");

if (isset($_GET["simpledb"])) {
	define('Adminer\DRIVER', "simpledb");

	if (class_exists('SimpleXMLElement') && ini_bool('allow_url_fopen')) {
		class Db extends SqlDb {
			public $extension = "SimpleXML", $server_info = '2009-04-15', $timeout, $next;

			function attach(array $server, string $username, string $password): string {
				return '';
			}

			function select_db(string $database): bool {
				return ($database == "domain");
			}

			function query(string $query, bool $unbuffered = false) {
				$params = array('SelectExpression' => $query, 'ConsistentRead' => 'true');
				if ($this->next) {
					$params['NextToken'] = $this->next;
				}
				$result = sdb_request_all('Select', 'Item', $params, $this->timeout); //! respect $unbuffered
				$this->timeout = 0;
				if ($result === false) {
					return $result;
				}
				if (preg_match('~^\s*SELECT\s+COUNT\(~i', $query)) {
					$sum = 0;
					foreach ($result as $item) {
						$sum += $item->Attribute->Value;
					}
					$result = array((object) array('Attribute' => array((object) array(
						'Name' => 'Count',
						'Value' => $sum,
					))));
				}
				return new Result($result);
			}

			function quote(string $string): string {
				return "'" . str_replace("'", "''", $string) . "'";
			}
		}

		class Result {
			public $num_rows;
			private $rows = array(), $offset = 0;

			function __construct(array $result) {
				foreach ($result as $item) {
					$row = array();
					if ($item->Name != '') { // SELECT COUNT(*)
						$row['itemName()'] = (string) $item->Name;
					}
					foreach ($item->Attribute as $attribute) {
						$name = $this->processValue($attribute->Name);
						$value = $this->processValue($attribute->Value);
						if (isset($row[$name])) {
							$row[$name] = (array) $row[$name];
							$row[$name][] = $value;
						} else {
							$row[$name] = $value;
						}
					}
					$this->rows[] = $row;
					foreach ($row as $key => $val) {
						if (!isset($this->rows[0][$key])) {
							$this->rows[0][$key] = null;
						}
					}
				}
				$this->num_rows = count($this->rows);
			}

			private function processValue($element) {
				return (is_object($element) && $element['encoding'] == 'base64' ? base64_decode($element) : (string) $element);
			}

			function fetch_assoc() {
				$row = current($this->rows);
				if (!$row) {
					return $row;
				}
				$return = array();
				foreach ($this->rows[0] as $key => $val) {
					$return[$key] = $row[$key];
				}
				next($this->rows);
				return $return;
			}

			function fetch_row() {
				$return = $this->fetch_assoc();
				if (!$return) {
					return $return;
				}
				return array_values($return);
			}

			function fetch_field(): \stdClass {
				$keys = array_keys($this->rows[0]);
				return (object) array('name' => $keys[$this->offset++], 'type' => 15, 'charsetnr' => 0);
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("SimpleXML + allow_url_fopen");
		static $jush = "simpledb";
		static $passwords = false;

		static $serverSchemes = array("http", "https");

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "IS NOT NULL");
		public $grouping = array("count");

		public $primary = "itemName()";

		/** Get the JUSH module inlined in the released driver by the release script */
		static function jushModule(): string {
			return ""; // the repository and the source archive load adminer/static/jush/modules/jush-simpledb.js
		}

		static function jushAutocomplete(array $tables, ?array $statements): string {
			return ""; // the queries are only a select expression and the columns are not known
		}

		private function chunkRequest(array $ids, string $action, array $params, array $expand = array()): bool {
			foreach (array_chunk($ids, 25) as $chunk) {
				$params2 = $params;
				foreach ($chunk as $i => $id) {
					$params2["Item.$i.ItemName"] = $id;
					foreach ($expand as $key => $val) {
						$params2["Item.$i.$key"] = $val;
					}
				}
				if (!sdb_request($action, $params2)) {
					return false;
				}
			}
			connection()->affected_rows = count($ids);
			return true;
		}

		private function extractIds(string $table, string $queryWhere, int $limit): array {
			$return = array();
			if (preg_match_all("~itemName\(\) = (('[^']*+')+)~", $queryWhere, $matches)) {
				$return = array_map('Adminer\idf_unescape', $matches[1]);
			} else {
				foreach (sdb_request_all('Select', 'Item', array('SelectExpression' => 'SELECT itemName() FROM ' . table($table) . $queryWhere . ($limit ? " LIMIT 1" : ""))) as $item) {
					$return[] = $item->Name;
				}
			}
			return $return;
		}

		function select(string $table, array $select, array $where, array $group, array $order = array(), int $limit = 1, ?int $page = 0, bool $print = false) {
			connection()->next = $_GET["next"];
			$_GET["next"] = ""; // set by sdb_request_all() if there is a following page
			$return = parent::select($table, $select, $where, $group, $order, $limit, $page, $print);
			connection()->next = 0;
			return $return;
		}

		function delete(string $table, string $queryWhere, int $limit = 0) {
			return $this->chunkRequest(
				$this->extractIds($table, $queryWhere, $limit),
				'BatchDeleteAttributes',
				array('DomainName' => $table)
			);
		}

		function update(string $table, array $set, string $queryWhere, int $limit = 0, string $separator = "\n") {
			$delete = array();
			$insert = array();
			$i = 0;
			$ids = $this->extractIds($table, $queryWhere, $limit);
			$id = idf_unescape($set["`itemName()`"]);
			unset($set["`itemName()`"]);
			foreach ($set as $key => $val) {
				$key = idf_unescape($key);
				if ($val == "NULL" || ($id != "" && $ids != array($id))) {
					$delete["Attribute." . count($delete) . ".Name"] = $key;
				}
				if ($val != "NULL") {
					foreach ((array) $val as $k => $v) {
						$insert["Attribute.$i.Name"] = $key;
						$insert["Attribute.$i.Value"] = (is_array($val) ? $v : idf_unescape($v));
						if (!$k) {
							$insert["Attribute.$i.Replace"] = "true";
						}
						$i++;
					}
				}
			}
			$params = array('DomainName' => $table);
			return (!$insert || $this->chunkRequest(($id != "" ? array($id) : $ids), 'BatchPutAttributes', $params, $insert))
				&& (!$delete || $this->chunkRequest($ids, 'BatchDeleteAttributes', $params, $delete))
			;
		}

		function insert(string $table, array $set) {
			$params = array("DomainName" => $table);
			$i = 0;
			foreach ($set as $name => $value) {
				if ($value != "NULL") {
					$name = idf_unescape($name);
					if ($name == "itemName()") {
						$params["ItemName"] = idf_unescape($value);
					} else {
						foreach ((array) $value as $val) {
							$params["Attribute.$i.Name"] = $name;
							$params["Attribute.$i.Value"] = (is_array($value) ? $val : idf_unescape($value));
							$i++;
						}
					}
				}
			}
			return sdb_request('PutAttributes', $params);
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			//! use one batch request
			foreach ($rows as $set) {
				if (!$this->update($table, $set, "WHERE `itemName()` = " . q($set["`itemName()`"]))) {
					return false;
				}
			}
			return true;
		}

		function begin() {
			return false;
		}

		function commit() {
			return false;
		}

		function rollback() {
			return false;
		}

		function slowQuery(string $query, int $timeout) {
			$this->conn->timeout = $timeout;
			return $query;
		}
	}



	function support(string $feature): bool {
		return preg_match('~^(cursor|sql)$~', $feature);
	}

	function logged_user(): string {
		$credentials = adminer()->credentials();
		return $credentials[1];
	}

	function get_databases(bool $flush): array {
		return array("domain");
	}

	function collations(): array {
		return array();
	}

	function db_collation(string $db, array $collations) {
	}

	function tables_list(): array {
		$return = array();
		foreach (sdb_request_all('ListDomains', 'DomainName') as $table) {
			$return[(string) $table] = 'table';
		}
		if (connection()->error && defined('Adminer\PAGE_HEADER')) {
			echo "<p class='error'>" . adminer()->error() . "\n";
		}
		return $return;
	}

	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		foreach (($name != "" ? array($name => true) : tables_list()) as $table => $type) {
			$row = array("Name" => $table, "Auto_increment" => "");
			if (!$fast) {
				$meta = sdb_request('DomainMetadata', array('DomainName' => $table));
				if ($meta) {
					foreach (
						array(
							"Rows" => "ItemCount",
							"Data_length" => "ItemNamesSizeBytes",
							"Index_length" => "AttributeValuesSizeBytes",
							"Data_free" => "AttributeNamesSizeBytes",
						) as $key => $val
					) {
						$row[$key] = (string) $meta->$val;
					}
				}
			}
			$return[$table] = $row;
		}
		return $return;
	}

	function explain(Db $connection, string $query) {
	}

	function error(): string {
		return h(connection()->error);
	}

	function information_schema(string $db) {
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		return array(
			array("type" => "PRIMARY", "columns" => array("itemName()")),
		);
	}

	function fields(string $table): array {
		return fields_from_edit();
	}

	function foreign_keys(string $table): array {
		return array();
	}

	function table(string $idf): string {
		return idf_escape($idf);
	}

	function idf_escape(string $idf): string {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" : "");
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return limit($query, $where, 1, 0, $separator);
	}

	function convert_field(array $field) {
	}

	function unconvert_field(array $field, string $return): string {
		return $return;
	}

	function is_view(array $table_status): bool {
		return false;
	}

	function fk_support(array $table_status) {
	}

	function alter_table(string $table, string $name, array $fields, array $foreign, ?string $comment, string $engine, string $collation, string $auto_increment, ?array $partitioning) {
		return ($table == "" && sdb_request('CreateDomain', array('DomainName' => $name)));
	}

	function drop_tables(array $tables) {
		foreach ($tables as $table) {
			if (!sdb_request('DeleteDomain', array('DomainName' => $table))) {
				return false;
			}
		}
		return true;
	}

	function count_tables(array $databases) {
		foreach ($databases as $db) {
			return array($db => count(tables_list()));
		}
	}

	function found_rows(array $table_status, array $where) {
		return ($where ? null : $table_status["Rows"]);
	}

	function last_id($result) {
	}

	function sdb_request(string $action, array $params = array()) {
		list($host, $params['AWSAccessKeyId'], $secret) = adminer()->credentials();
		if ($host == "") {
			$host = "sdb.amazonaws.com";
		}
		$params['Action'] = $action;
		$params['Timestamp'] = gmdate('Y-m-d\TH:i:s+00:00');
		$params['Version'] = '2009-04-15';
		$params['SignatureVersion'] = 2;
		$params['SignatureMethod'] = 'HmacSHA1';
		ksort($params);
		$query = '';
		foreach ($params as $key => $val) {
			$query .= '&' . rawurlencode($key) . '=' . rawurlencode($val);
		}
		$query = str_replace('%7E', '~', substr($query, 1));
		$query .= "&Signature=" . urlencode(base64_encode(hash_hmac('sha1', "POST\n" . preg_replace('~^https?://~', '', $host) . "\n/\n$query", $secret, true)));
		list($file) = get_url((preg_match('~^https?://~', $host) ? $host : "http://$host"), stream_context_create(array('http' => array(
			'method' => 'POST', // may not fit in URL with GET
			'content' => $query,
			'ignore_errors' => 1,
			'follow_location' => 0,
			'max_redirects' => 0,
		))));
		if (!$file) {
			connection()->error = lang('Invalid credentials.');
			return false;
		}
		libxml_use_internal_errors(true);
		libxml_disable_entity_loader();
		$xml = simplexml_load_string($file);
		if (!$xml) {
			$error = libxml_get_last_error();
			connection()->error = $error->message;
			return false;
		}
		if ($xml->Errors) {
			$error = $xml->Errors->Error;
			connection()->error = "$error->Message ($error->Code)";
			return false;
		}
		connection()->error = '';
		$tag = $action . "Result";
		return ($xml->$tag ?: true);
	}

	function sdb_request_all(string $action, string $tag, array $params = array(), $timeout = 0) {
		$return = array();
		$start = ($timeout ? microtime(true) : 0);
		$limit = (preg_match('~LIMIT\s+(\d+)\s*$~i', $params['SelectExpression'], $match) ? $match[1] : 0);
		do {
			$xml = sdb_request($action, $params);
			if (!$xml) {
				break;
			}
			foreach ($xml->$tag as $element) {
				$return[] = $element;
			}
			if ($limit && count($return) >= $limit) {
				$_GET["next"] = (string) $xml->NextToken;
				break;
			}
			if ($timeout && microtime(true) - $start > $timeout) {
				return false;
			}
			$params['NextToken'] = $xml->NextToken;
			if ($limit) {
				$params['SelectExpression'] = preg_replace('~\d+\s*$~', $limit - count($return), $params['SelectExpression']);
			}
		} while ($xml->NextToken);
		return $return;
	}
}
