<?php
$drivers["simpledb"] = "SimpleDB";

if (isset($_GET["simpledb"])) {
	$possible_drivers = array("SimpleXML + allow_url_fopen");
	define("DRIVER", "simpledb");

	if (class_exists('SimpleXMLElement') && ini_bool('allow_url_fopen')) {
		class Min_DB {
			var $extension = "SimpleXML", $server_info = '2009-04-15', $error, $timeout, $next, $affected_rows, $_result;

			function select_db($database) {
				return ($database == "domain");
			}

			function query($query, $unbuffered = false) {
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
				return new Min_Result($result);
			}

			function multi_query($query) {
				return $this->_result = $this->query($query);
			}

			function store_result() {
				return $this->_result;
			}

			function next_result() {
				return false;
			}

			function quote($string) {
				return "'" . str_replace("'", "''", $string) . "'";
			}

		}

		class Min_Result {
			var $num_rows, $_rows = array(), $_offset = 0;

			function __construct($result) {
				foreach ($result as $item) {
					$row = array();
					if ($item->Name != '') { // SELECT COUNT(*)
						$row['itemName()'] = (string) $item->Name;
					}
					foreach ($item->Attribute as $attribute) {
						$name = $this->_processValue($attribute->Name);
						$value = $this->_processValue($attribute->Value);
						if (isset($row[$name])) {
							$row[$name] = (array) $row[$name];
							$row[$name][] = $value;
						} else {
							$row[$name] = $value;
						}
					}
					$this->_rows[] = $row;
					foreach ($row as $key => $val) {
						if (!isset($this->_rows[0][$key])) {
							$this->_rows[0][$key] = null;
						}
					}
				}
				$this->num_rows = count($this->_rows);
			}

			function _processValue($element) {
				return (is_object($element) && $element['encoding'] == 'base64' ? base64_decode($element) : (string) $element);
			}

			function fetch_assoc() {
				$row = current($this->_rows);
				if (!$row) {
					return $row;
				}
				$return = array();
				foreach ($this->_rows[0] as $key => $val) {
					$return[$key] = $row[$key];
				}
				next($this->_rows);
				return $return;
			}

			function fetch_row() {
				$return = $this->fetch_assoc();
				if (!$return) {
					return $return;
				}
				return array_values($return);
			}

			function fetch_field() {
				$keys = array_keys($this->_rows[0]);
				return (object) array('name' => $keys[$this->_offset++]);
			}

		}
	}



	class Min_Driver extends Min_SQL {
		public $primary = "itemName()";

		function _chunkRequest($ids, $action, $params, $expand = array()) {
			global $connection;
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
			$connection->affected_rows = count($ids);
			return true;
		}

		function _extractIds($table, $queryWhere, $limit) {
			$return = array();
			if (preg_match_all("~itemName\(\) = (('[^']*+')+)~", $queryWhere, $matches)) {
				$return = array_map('idf_unescape', $matches[1]);
			} else {
				foreach (sdb_request_all('Select', 'Item', array('SelectExpression' => 'SELECT itemName() FROM ' . table($table) . $queryWhere . ($limit ? " LIMIT 1" : ""))) as $item) {
					$return[] = $item->Name;
				}
			}
			return $return;
		}

		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
			global $connection;
			$connection->next = $_GET["next"];
			$return = parent::select($table, $select, $where, $group, $order, $limit, $page, $print);
			$connection->next = 0;
			return $return;
		}

		function delete($table, $queryWhere, $limit = 0) {
			return $this->_chunkRequest(
				$this->_extractIds($table, $queryWhere, $limit),
				'BatchDeleteAttributes',
				array('DomainName' => $table)
			);
		}

		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
			$delete = array();
			$insert = array();
			$i = 0;
			$ids = $this->_extractIds($table, $queryWhere, $limit);
			$id = idf_unescape($set["`itemName()`"]);
			unset($set["`itemName()`"]);
			foreach ($set as $key => $val) {
				$key = idf_unescape($key);
				if ($val == "NULL" || ($id != "" && array($id) != $ids)) {
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
			return (!$insert || $this->_chunkRequest(($id != "" ? array($id) : $ids), 'BatchPutAttributes', $params, $insert))
				&& (!$delete || $this->_chunkRequest($ids, 'BatchDeleteAttributes', $params, $delete))
			;
		}

		function insert($table, $set) {
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

		function insertUpdate($table, $rows, $primary) {
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
		
		function slowQuery($query, $timeout) {
			$this->_conn->timeout = $timeout;
			return $query;
		}

	}



	function connect() {
		global $adminer;
		list(, , $password) = $adminer->credentials();
		if ($password != "") {
			return lang('Database does not support password.');
		}
		return new Min_DB;
	}

	function support($feature) {
		return preg_match('~sql~', $feature);
	}

	function logged_user() {
		global $adminer;
		$credentials = $adminer->credentials();
		return $credentials[1];
	}

	function get_databases() {
		return array("domain");
	}

	function collations() {
		return array();
	}

	function db_collation($db, $collations) {
	}

	function tables_list() {
		global $connection;
		$return = array();
		foreach (sdb_request_all('ListDomains', 'DomainName') as $table) {
			$return[(string) $table] = 'table';
		}
		if ($connection->error && defined("PAGE_HEADER")) {
			echo "<p class='error'>" . error() . "\n";
		}
		return $return;
	}

	function table_status($name = "", $fast = false) {
		$return = array();
		foreach (($name != "" ? array($name => true) : tables_list()) as $table => $type) {
			$row = array("Name" => $table, "Auto_increment" => "");
			if (!$fast) {
				$meta = sdb_request('DomainMetadata', array('DomainName' => $table));
				if ($meta) {
					foreach (array(
						"Rows" => "ItemCount",
						"Data_length" => "ItemNamesSizeBytes",
						"Index_length" => "AttributeValuesSizeBytes",
						"Data_free" => "AttributeNamesSizeBytes",
					) as $key => $val) {
						$row[$key] = (string) $meta->$val;
					}
				}
			}
			if ($name != "") {
				return $row;
			}
			$return[$table] = $row;
		}
		return $return;
	}

	function explain($connection, $query) {
	}

	function error() {
		global $connection;
		return h($connection->error);
	}

	function information_schema() {
	}

	function is_view($table_status) {
	}

	function indexes($table, $connection2 = null) {
		return array(
			array("type" => "PRIMARY", "columns" => array("itemName()")),
		);
	}

	function fields($table) {
		return fields_from_edit();
	}

	function foreign_keys($table) {
		return array();
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" : "");
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function fk_support($table_status) {
	}

	function engines() {
		return array();
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		return ($table == "" && sdb_request('CreateDomain', array('DomainName' => $name)));
	}

	function drop_tables($tables) {
		foreach ($tables as $table) {
			if (!sdb_request('DeleteDomain', array('DomainName' => $table))) {
				return false;
			}
		}
		return true;
	}

	function count_tables($databases) {
		foreach ($databases as $db) {
			return array($db => count(tables_list()));
		}
	}

	function found_rows($table_status, $where) {
		return ($where ? null : $table_status["Rows"]);
	}

	function last_id() {
	}

	function hmac($algo, $data, $key, $raw_output = false) {
		// can use hash_hmac() since PHP 5.1.2
		$blocksize = 64;
		if (strlen($key) > $blocksize) {
			$key = pack("H*", $algo($key));
		}
		$key = str_pad($key, $blocksize, "\0");
		$k_ipad = $key ^ str_repeat("\x36", $blocksize);
		$k_opad = $key ^ str_repeat("\x5C", $blocksize);
		$return = $algo($k_opad . pack("H*", $algo($k_ipad . $data)));
		if ($raw_output) {
			$return = pack("H*", $return);
		}
		return $return;
	}

	function sdb_request($action, $params = array()) {
		global $adminer, $connection;
		list($host, $params['AWSAccessKeyId'], $secret) = $adminer->credentials();
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
		$query .= "&Signature=" . urlencode(base64_encode(hmac('sha1', "POST\n" . preg_replace('~^https?://~', '', $host) . "\n/\n$query", $secret, true)));
		@ini_set('track_errors', 1); // @ - may be disabled
		$file = @file_get_contents((preg_match('~^https?://~', $host) ? $host : "http://$host"), false, stream_context_create(array('http' => array(
			'method' => 'POST', // may not fit in URL with GET
			'content' => $query,
			'ignore_errors' => 1, // available since PHP 5.2.10
		))));
		if (!$file) {
			$connection->error = $php_errormsg;
			return false;
		}
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($file);
		if (!$xml) {
			$error = libxml_get_last_error();
			$connection->error = $error->message;
			return false;
		}
		if ($xml->Errors) {
			$error = $xml->Errors->Error;
			$connection->error = "$error->Message ($error->Code)";
			return false;
		}
		$connection->error = '';
		$tag = $action . "Result";
		return ($xml->$tag ? $xml->$tag : true);
	}

	function sdb_request_all($action, $tag, $params = array(), $timeout = 0) {
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
				$_GET["next"] = $xml->NextToken;
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

	$jush = "simpledb";
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "IS NOT NULL");
	$functions = array();
	$grouping = array("count");
	$edit_functions = array(array("json"));
}
