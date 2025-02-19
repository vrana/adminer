<?php
add_driver("elastic5", "Elasticsearch 5 (beta)");

if (isset($_GET["elastic5"])) {
	define("DRIVER", "elastic5");

	if (ini_bool('allow_url_fopen')) {
		class Min_DB {
			var $extension = "JSON", $server_info, $errno, $error, $_url, $_db;

			/** Performs query
			 * @param string
			 * @param array
			 * @param string
			 * @return mixed
			 */
			function rootQuery($path, $content = array(), $method = 'GET') {
				$file = @file_get_contents("$this->_url/" . ltrim($path, '/'), false, stream_context_create(array('http' => array(
					'method' => $method,
					'content' => $content !== null ? json_encode($content) : $content,
					'header' => $content !== null ? 'Content-Type: application/json' : array(),
					'ignore_errors' => 1, // available since PHP 5.2.10
					'follow_location' => 0,
					'max_redirects' => 0,
				))));
				if (!$file || !preg_match('~^HTTP/[0-9.]+ 2~i', $http_response_header[0])) {
					$this->error = lang('Invalid credentials.');
					return false;
				}
				$return = json_decode($file, true);
				if ($return === null) {
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

			/** Performs query relative to actual selected DB
			 * @param string
			 * @param array
			 * @param string
			 * @return mixed
			 */
			function query($path, $content = array(), $method = 'GET') {
				// Support for global search through all tables
				if ($path != "" && $path[0] == "S" && preg_match('/SELECT 1 FROM ([^ ]+) WHERE (.+) LIMIT ([0-9]+)/', $path, $matches)) {
					$driver = get_driver();

					$where = explode(" AND ", $matches[2]);

					return $driver->select($matches[1], array("*"), $where, null, array(), $matches[3]);
				}

				return $this->rootQuery(($this->_db != "" ? "$this->_db/" : "/") . ltrim($path, '/'), $content, $method);
			}

			function connect($server, $username, $password) {
				preg_match('~^(https?://)?(.*)~', $server, $match);
				$this->_url = ($match[1] ? $match[1] : "http://") . urlencode($username) . ":" . urlencode($password) . "@$match[2]";
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

			function __construct($rows) {
				$this->num_rows = count($rows);
				$this->_rows = $rows;

				reset($this->_rows);
			}

			function fetch_assoc() {
				$return = current($this->_rows);
				next($this->_rows);

				return $return;
			}

			function fetch_row() {
				$row = $this->fetch_assoc();

				return $row ? array_values($row) : false;
			}
		}
	}

	class Min_Driver extends Min_SQL {

		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
			$data = array();
			if ($select != array("*")) {
				$data["fields"] = array_values($select);
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

			foreach ($where as $val) {
				if (preg_match('~^\((.+ OR .+)\)$~', $val, $matches)) {
					$parts = explode(" OR ", $matches[1]);
					$terms = array();
					foreach ($parts as $part) {
						list($col, $op, $val) = explode(" ", $part, 3);
						$term = array($col => $val);
						if ($op == "=") {
							$terms[] = array("term" => $term);
						} elseif (in_array($op, array("must", "should", "must_not"))) {
							$data["query"]["bool"][$op][]["match"] = $term;
						}
					}

					if (!empty($terms)) {
						$data["query"]["bool"]["filter"][]["bool"]["should"] = $terms;
					}
				} else {
					list($col, $op, $val) = explode(" ", $val, 3);
					$term = array($col => $val);
					if ($op == "=") {
						$data["query"]["bool"]["filter"][] = array("term" => $term);
					} elseif (in_array($op, array("must", "should", "must_not"))) {
						$data["query"]["bool"][$op][]["match"] = $term;
					}
				}
			}

			$query = (min_version(7) ? "" : "$table/") . "_search";
			$start = microtime(true);
			$search = $this->_conn->query($query, $data);

			if ($print) {
				echo adminer()->selectQuery("$query: " . json_encode($data), $start, !$search);
			}
			if (!$search) {
				return false;
			}

			$return = array();
			foreach ($search['hits']['hits'] as $hit) {
				$row = array();
				if ($select == array("*")) {
					$row["_id"] = $hit["_id"];
				}

				$fields = $hit['_source'];
				if ($select != array("*")) {
					$fields = array();
					foreach ($select as $key) {
						$fields[$key] = $key == "_id" ? array($hit["_id"]) : $hit['fields'][$key];
					}
				}

				foreach ($fields as $key => $val) {
					if ($data["fields"]) {
						$val = $val[0];
					}
					$row[$key] = (is_array($val) ? json_encode($val) : $val); //! display JSON and others differently
				}

				$return[] = $row;
			}

			return new Min_Result($return);
		}

		function update($type, $record, $queryWhere, $limit = 0, $separator = "\n") {
			//! use $limit
			$parts = preg_split('~ *= *~', $queryWhere);
			if (count($parts) == 2) {
				$id = trim($parts[1]);
				$query = "$type/$id";

				return $this->_conn->query($query, $record, 'POST');
			}

			return false;
		}

		function insert($type, $record) {
			$id = ""; //! user should be able to inform _id
			$query = "$type/$id";
			$response = $this->_conn->query($query, $record, 'POST');
			$this->_conn->last_id = $response['_id'];

			return $response['created'];
		}

		function delete($type, $queryWhere, $limit = 0) {
			//! use $limit
			$ids = array();
			if (isset($_GET["where"]["_id"]) && $_GET["where"]["_id"]) {
				$ids[] = $_GET["where"]["_id"];
			}
			if (isset($_POST['check'])) {
				foreach ($_POST['check'] as $check) {
					$parts = preg_split('~ *= *~', $check);
					if (count($parts) == 2) {
						$ids[] = trim($parts[1]);
					}
				}
			}

			$this->_conn->affected_rows = 0;

			foreach ($ids as $id) {
				$query = "{$type}/{$id}";
				$response = $this->_conn->query($query, null, 'DELETE');
				if ((isset($response['found']) && $response['found']) || (isset($response['result']) && $response['result'] == 'deleted')) {
					$this->_conn->affected_rows++;
				}
			}

			return $this->_conn->affected_rows;
		}

		function convertOperator($operator) {
			return $operator == "LIKE %%" ? "should" : $operator;
		}
	}

	function connect() {
		$connection = new Min_DB;

		list($server, $username, $password) = adminer()->credentials();
		if (!preg_match('~^(https?://)?[-a-z\d.]+(:\d+)?$~', $server)) {
			return lang('Invalid server.');
		}
		if ($password != "" && $connection->connect($server, $username, "")) {
			return lang('Database does not support password.');
		}

		if ($connection->connect($server, $username, $password)) {
			return $connection;
		}

		return $connection->error;
	}

	function support($feature) {
		return preg_match("~database|table|columns~", $feature);
	}

	function logged_user() {
		$credentials = adminer()->credentials();

		return $credentials[1];
	}

	function get_databases() {
		$return = connection()->rootQuery('_aliases');
		if ($return) {
			$return = array_keys($return);
			sort($return, SORT_STRING);
		}

		return $return;
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function collations() {
		return array();
	}

	function db_collation($db, $collations) {
		//
	}
	
	function engines() {
		return array();
	}

	function count_tables($databases) {
		$return = array();

		$result = connection()->query('_stats');
		if ($result && $result['indices']) {
			$indices = $result['indices'];
			foreach ($indices as $indice => $stats) {
				$indexing = $stats['total']['indexing'];
				$return[$indice] = $indexing['index_total'];
			}
		}

		return $return;
	}

	function tables_list() {
		if (min_version(7)) {
			return array('_doc' => 'table');
		}

		$return = connection()->query('_mapping');
		if ($return) {
			$return = array_fill_keys(array_keys($return[connection()->_db]["mappings"]), 'table');
		}

		return $return;
	}

	function table_status($name = "", $fast = false) {
		$search = connection()->query("_search", array(
			"size" => 0,
			"aggregations" => array(
				"count_by_type" => array(
					"terms" => array(
						"field" => "_type"
					)
				)
			)
		), "POST");

		$return = array();

		if ($search) {
			$tables = $search["aggregations"]["count_by_type"]["buckets"];

			foreach ($tables as $table) {
				$return[$table["key"]] = array(
					"Name" => $table["key"],
					"Engine" => "table",
					"Rows" => $table["doc_count"],
				);

				if ($name != "" && $name == $table["key"]) {
					return $return[$name];
				}
			}
		}

		return $return;
	}

	function error() {
		return h(connection()->error);
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
		$mappings = array();

		if (min_version(7)) {
			$result = connection()->query("_mapping");
			if ($result) {
				$mappings = $result[connection()->_db]['mappings']['properties'];
			}
		} else {
			$result = connection()->query("$table/_mapping");
			if ($result) {
				$mappings = $result[$table]['properties'];
				if (!$mappings) {
					$mappings = $result[connection()->_db]['mappings'][$table]['properties'];
				}
			}
		}

		$return = array(
			"_id" => array(
				"field" => "_id",
				"full_type" => "text",
				"type" => "text",
				"privileges" => array("insert" => 1, "select" => 1),
			)
		);

		foreach ($mappings as $name => $field) {
			if (!isset($field["index"]) || $field["index"]) {
				$return[$name] = array(
					"field" => $name,
					"full_type" => $field["type"],
					"type" => $field["type"],
					"privileges" => array(
						"insert" => 1,
						"select" => 1,
						"update" => 1,
					),
				);
				if ($field["properties"]) { // only leaf fields can be edited
					unset($return[$name]["privileges"]["insert"]);
					unset($return[$name]["privileges"]["update"]);
				}
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
		//
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function fk_support($table_status) {
		//
	}

	function found_rows($table_status, $where) {
		return null;
	}

	/** Create index
	 * @param string
	 * @return mixed
	 */
	function create_database($db) {
		return connection()->rootQuery(urlencode($db), null, 'PUT');
	}

	/** Remove index
	 * @param array
	 * @return mixed
	 */
	function drop_databases($databases) {
		return connection()->rootQuery(urlencode(implode(',', $databases)), null, 'DELETE');
	}

	/** Alter type
	 * @param array
	 * @return mixed
	 */
	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$properties = array();
		foreach ($fields as $f) {
			$field_name = trim($f[1][0]);
			$field_type = trim($f[1][1] ? $f[1][1] : "text");
			$properties[$field_name] = array(
				'type' => $field_type
			);
		}

		if (!empty($properties)) {
			$properties = array('properties' => $properties);
		}
		return connection()->query("_mapping/{$name}", $properties, 'PUT');
	}

	/** Drop types
	 * @param array
	 * @return bool
	 */
	function drop_tables($tables) {
		$return = true;
		foreach ($tables as $table) { //! convert to bulk api
			$return = $return && connection()->query(urlencode($table), null, 'DELETE');
		}

		return $return;
	}

	function last_id() {
		return connection()->last_id;
	}

	function driver_config() {
		$types = array();
		$structured_types = array();

		foreach (array(
			lang('Numbers') => array("long" => 3, "integer" => 5, "short" => 8, "byte" => 10, "double" => 20, "float" => 66, "half_float" => 12, "scaled_float" => 21),
			lang('Date and time') => array("date" => 10),
			lang('Strings') => array("string" => 65535, "text" => 65535),
			lang('Binary') => array("binary" => 255),
		) as $key => $val) {
			$types += $val;
			$structured_types[$key] = array_keys($val);
		}

		return array(
			'possible_drivers' => array("json + allow_url_fopen"),
			'jush' => "elastic",
			'operators' => array("=", "must", "should", "must_not"),
			'functions' => array(),
			'grouping' => array(),
			'edit_functions' => array(array("json")),
			'types' => $types,
			'structured_types' => $structured_types,
		);
	}
}
