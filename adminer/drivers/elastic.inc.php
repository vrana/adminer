<?php
$drivers["elastic"] = "Elasticsearch (beta)";

if (isset($_GET["elastic"])) {
	$possible_drivers = array("json");
	define("DRIVER", "elastic");

	if (function_exists('json_decode')) {
		class Min_DB {
			var $extension = "JSON", $server_info, $errno, $error, $_url;

			function query($path, $content = array(), $method = 'GET') {
				@ini_set('track_errors', 1); // @ - may be disabled
				$file = @file_get_contents($this->_url . ($this->_db != "" ? "$this->_db/" : "") . $path, false, stream_context_create(array('http' => array(
					'method' => $method,
					'content' => json_encode($content),
					'ignore_errors' => 1, // available since PHP 5.2.10
				))));
				if (!$file) {
					$this->error = $php_errormsg;
					return $file;
				}
				if (!preg_match('~^HTTP/[0-9.]+ 2~i', $http_response_header[0])) {
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
							if ($value == $this->errno && preg_match('~^JSON_ERROR_~', $name)) {
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
			var $num_rows, $_rows;

			function Min_Result($rows) {
				$this->num_rows = count($this->_rows);
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

		function select($table, $select, $where, $group, $order, $limit, $page, $print = false) {
			global $adminer;
			$data = array();
			$query = "$table/_search";
			if ($select != array("*")) {
				$data["fields"] = $select;
			}
			if ($order) {
				$sort = array();
				foreach ($order as $col) {
					$col = preg_replace('~ DESC$~', '', $col, 1, $count);
					$sort[] = ($count ? array($col => "desc") : $col);
				}
				$data["sort"] = $sort;
			}
			if ($limit) {
				$data["size"] = +$limit;
				if ($page) {
					$data["from"] = ($page * $limit);
				}
			}
			foreach ((array) $_GET["where"] as $val) {
				if ("$val[col]$val[val]" != "") {
					$term = array("match" => array(($val["col"] != "" ? $val["col"] : "_all") => $val["val"]));
					if ($val["op"] == "=") {
						$data["query"]["filtered"]["filter"]["and"][] = $term;
					} else {
						$data["query"]["filtered"]["query"]["bool"]["must"][] = $term;
					}
				}
			}
			if ($data["query"] && !$data["query"]["filtered"]["query"]) {
				$data["query"]["filtered"]["query"] = array("match_all" => array());
			}
			if ($print) {
				echo $adminer->selectQuery("$query: " . print_r($data, true));
			}
			$search = $this->_conn->query($query, $data);
			if (!$search) {
				return false;
			}
			$return = array();
			foreach ($search['hits']['hits'] as $hit) {
				$row = array();
				$fields = $hit['_source'];
				if ($select != array("*")) {
					$fields = array();
					foreach ($select as $key) {
						$fields[$key] = $hit['fields'][$key];
					}
				}
				foreach ($fields as $key => $val) {
					$row[$key] = (is_array($val) ? json_encode($val) : $val); //! display JSON and others differently
				}
				$return[] = $row;
			}
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
		return preg_match("~database|table|columns~", $feature);
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
			}
			if ($name != "") {
				return $return[$name];
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

	/** Create database
	* @param string
	* @return mixed
	*/
	function create_database($db) {
		global $connection;
		return $connection->query(urlencode($db), array(), 'PUT');
	}

	/** Drop databases
	* @param array
	* @return mixed
	*/
	function drop_databases($databases) {
		global $connection;
		return $connection->query(urlencode(implode(',', $databases)), array(), 'DELETE');
	}

	/** Drop tables
	* @param array
	* @return bool
	*/
	function drop_tables($tables) {
		global $connection;
		$return = true;
		foreach ($tables as $table) { //! convert to bulk api
			$return = $return && $connection->query(urlencode($table), array(), 'DELETE');
		}
		return $return;
	}

	$jush = "elastic";
	$operators = array("=", "query");
	$functions = array();
	$grouping = array();
	$edit_functions = array(array("json"));
}
