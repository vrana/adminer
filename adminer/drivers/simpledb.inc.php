<?php
/* //!
invalid user or password
report API calls instead of queries
multi-value attributes
select: clone
update: delete + insert when changing itemName()
*/

$drivers["simpledb"] = "SimpleDB";

if (isset($_GET["simpledb"])) {
	$possible_drivers = array("SimpleXML");
	define("DRIVER", "simpledb");
	
	if (class_exists('SimpleXMLElement')) {
		class Min_DB {
			var $extension = "SimpleXML", $server_info = '2009-04-15', $error, $timeout, $next, $_result;
			
			function select_db($database) {
				return ($database == "domain");
			}
			
			function query($query, $unbuffered = false) {
				$params = array('SelectExpression' => $query, 'ConsistentRead' => 'true');
				if ($this->next) {
					$params['NextToken'] = $this->next;
				}
				$result = sdb_request_all('Select', 'Item', $params, $this->timeout); //! respect $unbuffered
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
			
			function Min_Result($result) {
				foreach ($result as $item) {
					$row = array();
					if ($item->Name != '') { // SELECT COUNT(*)
						$row['itemName()'] = (string) $item->Name;
					}
					foreach ($item->Attribute as $attribute) {
						$name = $this->_processValue($attribute->Name);
						$row[$name] .= ($row[$name] != '' ? ',' : '') . $this->_processValue($attribute->Value);
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
	
	
	
	class Min_Driver {
		
		function _chunkRequest($ids, $action, $params, $expand = array()) {
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
			return true;
		}
		
		function _extractIds($queryWhere, $limit) {
			$return = array();
			if (preg_match_all("~itemName\(\) = ('[^']*+')+~", $queryWhere, $matches)) {
				$return = array_map('idf_unescape', $matches[1]);
			} else {
				foreach (sdb_request_all('Select', 'Item', array('SelectExpression' => 'SELECT itemName() FROM ' . table($table) . $queryWhere . ($limit ? " LIMIT 1" : ""))) as $item) {
					$return[] = $item->Name;
				}
			}
			return $return;
		}
		
		function delete($table, $queryWhere, $limit = 0) {
			return $this->_chunkRequest(
				$this->_extractIds($queryWhere, $limit),
				'BatchDeleteAttributes',
				array('DomainName' => $table)
			);
		}
		
		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
			$delete = array();
			$insert = array();
			$i = 0;
			foreach ($set as $key => $val) {
				$key = idf_unescape($key);
				if ($val == "NULL") {
					$delete["Attribute." . count($delete) . ".Name"] = $key;
				} elseif ($key != "itemName()") {
					$insert["Attribute.$i.Name"] = $key;
					$insert["Attribute.$i.Value"] = idf_unescape($val);
					$insert["Attribute.$i.Replace"] = "true";
					$i++;
				}
			}
			$ids = $this->_extractIds($queryWhere, $limit);
			$params = array('DomainName' => $table);
			return (!$insert || $this->_chunkRequest($ids, 'BatchPutAttributes', $params, $insert))
				&& (!$delete || $this->_chunkRequest($ids, 'BatchDeleteAttributes', $params, $delete))
			;
		}
		
		function insert($table, $set) {
			$params = array("DomainName" => $table);
			$i = 0;
			foreach ($set as $name => $value) {
				if ($value != "NULL") {
					$name = idf_unescape($name);
					$value = idf_unescape($value);
					if ($name == "itemName()") {
						$params["ItemName"] = $value;
					} else {
						$params["Attribute.$i.Name"] = $name;
						$params["Attribute.$i.Value"] = $value;
						$i++;
					}
				}
			}
			return sdb_request('PutAttributes', $params);
		}
		
	}
	
	
	
	function connect() {
		return new Min_DB;
	}
	
	function support($feature) {
		return false;
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
		return array();
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
		return false;
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
		$query .= "&Signature=" . urlencode(base64_encode(hmac('sha1', "POST\n" . ereg_replace('^https?://', '', $host) . "\n/\n$query", $secret, true)));
		@ini_set('track_errors', 1); // @ - may be disabled
		$file = @file_get_contents((ereg('^https?://', $host) ? $host : "http://$host"), false, stream_context_create(array('http' => array(
			'method' => 'POST', // may not fit in URL with GET
			'content' => $query,
			'ignore_errors' => 1, // available since PHP 5.2.10
		))));
		if (!$file || !($xml = simplexml_load_string($file))) {
			$connection->error = $php_errormsg;
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
	$edit_functions = array();
}
