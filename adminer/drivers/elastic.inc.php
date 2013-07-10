<?php
$drivers["elastic"] = "Elasticsearch";

if (isset($_GET["elastic"])) {
	$possible_drivers = array("json");
	define("DRIVER", "elastic");
	
	if (function_exists('json_decode')) {
		class Min_DB {
			var $extension = "JSON", $server_info, $errno, $error, $_url;
			
			function query($path) {
				$file = @file_get_contents($this->_url . ($this->_db != "" ? "$this->_db/" : "") . $path, false, stream_context_create(array('http' => array(
					'ignore_errors' => 1, // available since PHP 5.2.10
				))));
				@ini_set('track_errors', 1); // @ - may be disabled
				if (!$file) {
					$this->error = $php_errormsg;
					return $file;
				}
				if (!eregi('^HTTP/[0-9.]+ 2', $http_response_header[0])) {
					$this->error = $file;
					return false;
				}
				$return = json_decode($file, true);
				if (!$return) {
					$this->errno = json_last_error();
					if (function_exists('json_last_error_msg')) {
						$this->error = json_last_error_msg();
					} else {
						$constants = get_defined_constants(true);
						foreach ($constants['json'] as $name => $value) {
							if ($value == $this->errno && ereg('^JSON_ERROR_', $name)) {
								$this->error = $name;
								break;
							}
						}
					}
				}
				return $return;
			}
			
			function connect($server, $username, $password) {
				$this->_url = "http://$username:$password@$server/";
				$return = $this->query('');
				if ($return) {
					$this->server_info = $return['version']['number'];
				}
				return (bool) $return;
			}
			
			function select_db($database) {
				$this->_db = $database;
				return true;
			}
			
			function quote($string) {
				return $string;
			}
			
		}
		
		class Min_Result {
			var $_rows;
			
			function Min_Result($rows) {
				$this->_rows = $rows;
				reset($this->_rows);
			}
			
			function fetch_assoc() {
				$return = current($this->_rows);
				next($this->_rows);
				return $return;
			}
			
			function fetch_row() {
				return array_values($this->fetch_assoc());
			}
			
		}
		
	}
	
	
	
	class Min_Driver extends Min_SQL {
		
		function select($table, $select, $where, $group, $order, $limit, $page) {
			global $adminer;
			$query = $adminer->selectQueryBuild($select, $where, $group, $order, $limit, $page);
			if (!$query) {
				$query = "$table/_search";
			}
			$search = $this->_conn->query($query);
			if (!$search) {
				return false;
			}
			$return = array();
			foreach ($search['hits']['hits'] as $hit) {
				$row = array();
				foreach ($hit['_source'] as $key => $val) {
					$row[$key] = (is_array($val) ? implode(", ", $val) : $val);
				}
				$return[] = $row;
			}
			echo $adminer->selectQuery($query);
			return new Min_Result($return);
		}
		
	}
	
	
	
	function connect() {
		global $adminer;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			return $connection;
		}
		return $connection->error;
	}
	
	function support($feature) {
		return ereg("database|table", $feature);
	}
	
	function logged_user() {
		global $adminer;
		$credentials = $adminer->credentials();
		return $credentials[1];
	}
	
	function get_databases() {
		global $connection;
		$return = $connection->query('_aliases');
		if ($return) {
			$return = array_keys($return);
		}
		return $return;
	}
	
	function collations() {
		return array();
	}
	
	function db_collation($db, $collations) {
	}
	
	function count_tables($databases) {
		global $connection;
		$return = $connection->query('_mapping');
		if ($return) {
			$return = array_map('count', $return);
		}
		return $return;
	}
	
	function tables_list() {
		global $connection;
		$return = $connection->query('_mapping');
		if ($return) {
			$return = array_fill_keys(array_keys(reset($return)), 'table');
		}
		return $return;
	}
	
	function table_status($name = "", $fast = false) {
		$return = tables_list();
		if ($return) {
			foreach ($return as $key => $type) { // _stats have just info about database
				$return[$key] = array("Name" => $key, "Engine" => $type);
				if ($name != "") {
					return $return[$name];
				}
			}
		}
		return $return;
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
			array("type" => "PRIMARY", "columns" => array("_id")),
		);
	}
	
	function fields($table) {
		global $connection;
		$mapping = $connection->query("$table/_mapping");
		$return = array();
		if ($mapping) {
			foreach ($mapping[$table]['properties'] as $name => $field) {
				$return[$name] = array(
					"field" => $name,
					"full_type" => $field["type"],
					"type" => $field["type"],
					"privileges" => array("insert" => 1, "select" => 1, "update" => 1),
				);
			}
		}
		return $return;
	}
	
	function foreign_keys($table) {
		return array();
	}
	
	function table($idf) {
		return $idf;
	}
	
	function idf_escape($idf) {
		return $idf;
	}
	
	function convert_field($field) {
	}
		
	function unconvert_field($field, $return) {
		return $return;
	}
	
	function fk_support($table_status) {
	}
	
	function found_rows($table_status, $where) {
		return null;
	}
	
	$jush = "elastic";
	$operators = array("=");
	$functions = array();
	$grouping = array();
	$edit_functions = array();
}
