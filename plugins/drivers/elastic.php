<?php
namespace Adminer;

add_driver("elastic", "Elasticsearch 7 (beta)");

if (isset($_GET["elastic"])) {
	define('Adminer\DRIVER', "elastic");

	if (ini_bool('allow_url_fopen')) {

		class Db extends SqlDb {
			public $extension = "JSON";
			private $url;

			/**
			 * @return array|false
			 */
			function rootQuery(string $path, ?array $content = null, string $method = 'GET') {
				$file = @file_get_contents("$this->url/" . ltrim($path, '/'), false, stream_context_create(array('http' => array(
					'method' => $method,
					'content' => $content !== null ? json_encode($content) : null,
					'header' => $content !== null ? 'Content-Type: application/json' : array(),
					'ignore_errors' => 1,
					'follow_location' => 0,
					'max_redirects' => 0,
				))));

				if ($file === false) {
					$this->error = lang('Invalid server or credentials.');
					return false;
				}

				$return = json_decode($file, true);
				if ($return === null) {
					$this->error = lang('Invalid server or credentials.');
					return false;
				}

				if (!preg_match('~^HTTP/[0-9.]+ 2~i', $http_response_header[0])) {
					if (isset($return['error']['root_cause'][0]['type'])) {
						$this->error = $return['error']['root_cause'][0]['type'] . ": " . $return['error']['root_cause'][0]['reason'];
					} elseif (isset($return['status']) && isset($return['error']) && is_string($return['error'])) {
						$this->error = $return['error'];
					}

					return false;
				}

				return $return;
			}

			/** Perform query relative to actual selected DB
			 * @return array|false
			 */
			function query(string $path, ?array $content = null, string $method = 'GET') {
				// Support for global search through all tables
				if ($path != "" && $path[0] == "S" && preg_match('/SELECT 1 FROM ([^ ]+) WHERE (.+) LIMIT ([0-9]+)/', $path, $matches)) {
					$driver = driver();

					$where = explode(" AND ", $matches[2]);

					return $driver->select($matches[1], array("*"), $where, array(), array(), $matches[3]);
				}

				return $this->rootQuery($path, $content, $method);
			}

			function connect(string $server, string $username, string $password): bool {
				preg_match('~^(https?://)?(.*)~', $server, $match);
				$this->url = ($match[1] ?: "http://") . urlencode($username) . ":" . urlencode($password) . "@$match[2]";
				$return = $this->query('');
				if ($return) {
					$this->server_info = $return['version']['number'];
				}
				return (bool) $return;
			}

			function select_db($database) {
				return true;
			}

			function quote($string) {
				return $string;
			}
		}

		class Result {
			public $num_rows;
			private $rows;

			function __construct($rows) {
				$this->num_rows = count($rows);
				$this->rows = $rows;

				reset($this->rows);
			}

			function fetch_assoc() {
				$return = current($this->rows);
				next($this->rows);

				return $return;
			}

			function fetch_row() {
				$row = $this->fetch_assoc();

				return $row ? array_values($row) : false;
			}
		}
	}

	class Driver extends SqlDriver {
		static $possibleDrivers = array("json + allow_url_fopen");
		static $jush = "elastic";

		public $editFunctions = array(array("json"));
		public $operators = array("=", "must", "should", "must_not");

		function __construct($connection) {
			parent::__construct($connection);
			$this->types = array(
				lang('Numbers') => array("long" => 3, "integer" => 5, "short" => 8, "byte" => 10, "double" => 20, "float" => 66, "half_float" => 12, "scaled_float" => 21),
				lang('Date and time') => array("date" => 10),
				lang('Strings') => array("string" => 65535, "text" => 65535),
				lang('Binary') => array("binary" => 255),
			);
		}

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

			$fields = null;
			foreach ($where as $val) {
				if (preg_match('~^\((.+ OR .+)\)$~', $val, $matches)) {
					$parts = explode(" OR ", $matches[1]);
					$terms = array();

					if ($fields === null) {
						$fields = fields($table);
					}
					foreach ($parts as $part) {
						list($col, $op, $val) = explode(" ", $part, 3);
						$term = array($col => $val);
						if (isset($fields[$col]) && $fields[$col]['full_type'] == 'boolean' && $val !== 'true' && $val !== 'false') {
							continue;
						}
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

			$query = "$table/_search";
			$start = microtime(true);
			$search = $this->conn->rootQuery($query, $data);

			if ($print) {
				echo adminer()->selectQuery("$query: " . json_encode($data), $start, !$search);
			}
			if (empty($search)) {
				return false;
			}
			$tableFields = ($select == array("*") ? array_keys(fields($table)) : array());

			$return = array();
			foreach ($search["hits"]["hits"] as $hit) {
				$row = array();
				if ($select == array("*")) {
					$row["_id"] = $hit["_id"];
				}

				if ($select != array("*")) {
					$fields = array();
					foreach ($select as $key) {
						$fields[$key] = $key == "_id" ? $hit["_id"] : $hit["_source"][$key];
					}
				} else {
					foreach ($tableFields as $key) {
						$fields[$key] = $key == "_id" ? $hit["_id"] : $hit["_source"][$key];
					}
				}
				foreach ($fields as $key => $val) {
					$row[$key] = (is_array($val) ? json_encode($val) : $val);
				}

				$return[] = $row;
			}

			return new Result($return);
		}

		function update($type, $record, $queryWhere, $limit = 0, $separator = "\n") {
			//! use $limit
			$parts = preg_split('~ *= *~', $queryWhere);
			if (count($parts) == 2) {
				$id = trim($parts[1]);
				$query = "$type/$id";

				return $this->conn->query($query, $record, 'POST');
			}

			return false;
		}

		function insert($type, $record) {
			$query = "$type/_doc/";
			if (isset($record["_id"]) && $record["_id"] != "NULL") {
				$query .= $record["_id"];
				unset($record["_id"]);
			}
			foreach ($record as $key => $value) {
				if ($value == "NULL") {
					unset($record[$key]);
				}
			}
			$response = $this->conn->query($query, $record, 'POST');
			if ($response == false) {
				return false;
			}
			$this->conn->last_id = $response['_id'];

			return $response['result'];
		}

		function delete($table, $queryWhere, $limit = 0) {
			//! use $limit
			$ids = array();
			if (idx($_GET["where"], "_id")) {
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

			$this->conn->affected_rows = 0;

			foreach ($ids as $id) {
				$query = "$table/_doc/$id";
				$response = $this->conn->query($query, null, 'DELETE');
				if (isset($response['result']) && $response['result'] == 'deleted') {
					$this->conn->affected_rows++;
				}
			}

			return !!$this->conn->affected_rows;
		}

		function convertOperator($operator) {
			return $operator == "LIKE %%" ? "should" : $operator;
		}
	}

	function connect($credentials) {
		$connection = new Db;

		list($server, $username, $password) = $credentials;
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
		return preg_match("~table|columns~", $feature);
	}

	function logged_user() {
		$credentials = adminer()->credentials();

		return $credentials[1];
	}

	function get_databases($flush) {
		return array("elastic");
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

	function count_tables($databases) {
		$return = connection()->rootQuery('_aliases');
		return array("elastic" => ($return ? count($return) : 0));
	}

	function tables_list() {
		$aliases = connection()->rootQuery('_aliases');
		if (empty($aliases)) {
			return array();
		}

		ksort($aliases);

		$tables = array();
		foreach ($aliases as $name => $index) {
			$tables[$name] = "table";

			ksort($index["aliases"]);
			$tables += array_fill_keys(array_keys($index["aliases"]), "view");
		}

		return $tables;
	}

	function table_status($name = "", $fast = false) {
		$stats = connection()->rootQuery('_stats');
		$aliases = connection()->rootQuery('_aliases');

		if (empty($stats) || empty($aliases)) {
			return array();
		}

		$result = array();

		if ($name != "") {
			if (isset($stats["indices"][$name])) {
				return array(format_index_status($name, $stats["indices"][$name]));
			} else {
				foreach ($aliases as $index_name => $index) {
					foreach ($index["aliases"] as $alias_name => $alias) {
						if ($alias_name == $name) {
							return array(format_alias_status($alias_name, $stats["indices"][$index_name]));
						}
					}
				}
			}
			return array();
		}

		ksort($stats["indices"]);
		foreach ($stats["indices"] as $name => $index) {
			if ($name[0] == ".") {
				continue;
			}

			$result[$name] = format_index_status($name, $index);

			if (!empty($aliases[$name]["aliases"])) {
				ksort($aliases[$name]["aliases"]);
				foreach ($aliases[$name]["aliases"] as $alias_name => $alias) {
					$result[$alias_name] = format_alias_status($alias_name, $stats["indices"][$name]);
				}
			}
		}

		return $result;
	}

	function format_index_status($name, $index) {
		return array(
			"Name" => $name,
			"Engine" => "Lucene",
			"Oid" => $index["uuid"],
			"Rows" => $index["total"]["docs"]["count"],
			"Auto_increment" => 0,
			"Data_length" => $index["total"]["store"]["size_in_bytes"],
			"Index_length" => 0,
			"Data_free" => $index["total"]["store"]["reserved_in_bytes"],
		);
	}

	function format_alias_status($name, $index) {
		return array(
			"Name" => $name,
			"Engine" => "view",
			"Rows" => $index["total"]["docs"]["count"],
		);
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function error() {
		return h(connection()->error);
	}

	function information_schema($db) {
		//
	}

	function indexes($table, $connection2 = null) {
		return array(
			array("type" => "PRIMARY", "columns" => array("_id")),
		);
	}

	function fields($table) {
		$mappings = array();
		$mapping = connection()->rootQuery("_mapping");

		if (!isset($mapping[$table])) {
			$aliases = connection()->rootQuery('_aliases');

			foreach ($aliases as $index_name => $index) {
				foreach ($index["aliases"] as $alias_name => $alias) {
					if ($alias_name == $table) {
						$table = $index_name;
						break;
					}
				}
			}
		}

		if (!empty($mapping)) {
			$mappings = $mapping[$table]["mappings"]["properties"];
		}

		$result = array(
			"_id" => array(
				"field" => "_id",
				"full_type" => "text",
				"type" => "text",
				"null" => true,
				"privileges" => array("insert" => 1, "select" => 1, "where" => 1, "order" => 1),
			)
		);

		foreach ($mappings as $name => $field) {
			$result[$name] = array(
				"field" => $name,
				"full_type" => $field["type"],
				"type" => $field["type"],
				"null" => true,
				"privileges" => array(
					"insert" => 1,
					"select" => 1,
					"update" => 1,
					"where" => !isset($field["index"]) || $field["index"] ?: null,
					"order" => $field["type"] != "text" ?: null
				),
			);
		}

		return $result;
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
	}

	/** Alter type
	 * @return mixed
	 */
	function alter_table(array $table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$properties = array();
		foreach ($fields as $f) {
			$field_name = trim($f[1][0]);
			$field_type = trim($f[1][1] ?: "text");
			$properties[$field_name] = array(
				'type' => $field_type
			);
		}

		if (!empty($properties)) {
			$properties = array('properties' => $properties);
		}

		return connection()->query("_mapping/$name", $properties, 'PUT');
	}

	/** Drop types
	 * @param list<string> $tables
	 */
	function drop_tables(array $tables): bool {
		$return = true;
		foreach ($tables as $table) { //! convert to bulk api
			$return = $return && connection()->query(urlencode($table), null, 'DELETE');
		}

		return $return;
	}

	function last_id($result) {
		return connection()->last_id;
	}
}
