<?php
$drivers["clickhouse"] = "ClickHouse (alpha)";

if (isset($_GET["clickhouse"])) {
	define("DRIVER", "clickhouse");

	class Min_DB {
		var $extension = "JSON", $server_info, $errno, $_result, $error, $_url;
		var $_db = 'default';

		function rootQuery($db, $query) {
			@ini_set('track_errors', 1); // @ - may be disabled
			$file = @file_get_contents("$this->_url/?database=$db", false, stream_context_create(array('http' => array(
				'method' => 'POST',
				'content' => stripos($query, 'insert') === 0 ? $query : "$query FORMAT JSONCompact",
				'header' => 'Content-type: application/x-www-form-urlencoded',
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
			return new Min_Result($return);
		}

		function query($query) {
			return $this->rootQuery($this->_db, $query);
		}

		function connect($server, $username, $password) {
			preg_match('~^(https?://)?(.*)~', $server, $match);
			$this->_url = ($match[1] ? $match[1] : "http://") . "$username:$password@$match[2]";
			$return = $this->query('SELECT 1');
			return (bool) $return;
		}

		function select_db($database) {
			$this->_db = $database;
			return true;
		}

		function quote($string) {
			return "'" . addcslashes($string, "\\'") . "'";
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

		function result($query, $field = 0) {
			$result = $this->query($query);
			return $result['data'];
		}
	}

	class Min_Result {
		var $num_rows, $_rows, $columns, $meta, $_offset = 0;

		function __construct($result) {
			$this->num_rows = $result['rows'];
			$this->_rows = $result['data'];
			$this->meta = $result['meta'];
			$this->columns = array_column($this->meta, 'name');
			reset($this->_rows);
		}

		function fetch_assoc() {
			$row = current($this->_rows);
			next($this->_rows);
			return $row === false ? false : array_combine($this->columns, $row);
		}

		function fetch_row() {
			$row = current($this->_rows);
			next($this->_rows);
			return $row;
		}

		function fetch_field() {
			$column = $this->_offset++;
			$return = new stdClass;
			if ($column < count($this->columns)) {
				$return->name = $this->meta[$column]['name'];
				$return->orgname = $return->name;
				$return->type = $this->meta[$column]['type'];
			}
			return $return;
		}
	}


	class Min_Driver extends Min_SQL {
	}

	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function explain($connection, $query) {
		return '';
	}

	function found_rows($table_status, $where) {
		$rows = get_vals("SELECT COUNT(*) FROM " . idf_escape($table_status["Name"]) . ($where ? " WHERE " . implode(" AND ", $where) : ""));
		return empty($rows) ? false : $rows[0];
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		foreach ($fields as $field) {
			if ($field[1][2] === " NULL") {
				$field[1][1] = " Nullable({$field[1][1]})";
			}
			unset($field[1][2]);
			$alter[] = ($field[1]
				? ($table != "" ? ($field[0] != "" ? "CHANGE " . idf_escape($field[0]) : "ADD") : " ") . " " . implode($field[1])
				: "DROP " . idf_escape($field[0])
			);
		}
		$alter = array_merge($alter, $foreign);
		$status = ($comment !== null ? " COMMENT=" . q($comment) : "")
			. ($engine ? " ENGINE=" . q($engine) : "")
			. ($collation ? " COLLATE " . q($collation) : "")
			. ($auto_increment != "" ? " AUTO_INCREMENT=$auto_increment" : "")
		;
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status$partitioning");
		}
		if ($table != $name) {
			$alter[] = "RENAME TO " . table($name);
		}
		if ($status) {
			$alter[] = ltrim($status);
		}
		return ($alter || $partitioning ? queries("ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter) . $partitioning) : true);
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return queries("DROP VIEW " . implode(", ", array_map('table', $views)));
	}

	function drop_tables($tables) {
		return queries("DROP TABLE " . implode(", ", array_map('table', $tables)));
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

	function get_databases($flush) {
		global $connection;
		$result = get_rows('SHOW DATABASES');

		$return = array();
		foreach ($result as $row) {
			$return[] = $row['name'];
		}
		sort($return);
		return $return;
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? ", $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation($db, $collations) {
	}

	function engines() {
		return array('MergeTree');
	}

	function logged_user() {
		global $adminer;
		$credentials = $adminer->credentials();
		return $credentials[1];
	}

	function tables_list() {
		$result = get_rows('SHOW TABLES');
		$return = array();
		foreach ($result as $row) {
			$return[$row['name']] = 'table';
		}
		ksort($return);
		return $return;
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "", $fast = false) {
		global $connection;
		$return = array();
		$tables = get_rows("SELECT name, engine FROM system.tables WHERE database = " . q($connection->_db));
		foreach ($tables as $table) {
			$return[$table['name']] = array(
				'Name' => $table['name'],
				'Engine' => $table['engine'],
			);
			if ($name === $table['name']) {
				return $return[$table['name']];
			}
		}
		return $return;
	}

	function is_view($table_status) {
		return false;
	}

	function fk_support($table_status) {
		return false;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		if (in_array($field['type'], ["Int8", "Int16", "Int32", "Int64", "UInt8", "UInt16", "UInt32", "UInt64", "Float32", "Float64"])) {
			return "to$field[type]($return)";
		}
		return $return;
	}

	function fields($table) {
		$return = array();
		$result = get_rows("SELECT name, type, default_expression FROM system.columns WHERE " . idf_escape('table') . " = " . q($table));
		foreach($result as $row) {
			$type = trim($row['type']);
			$nullable = strpos($type, 'Nullable(') === 0;
			$return[trim($row['name'])] = array(
				"field" => trim($row['name']),
				"full_type" => $type,
				"type" => $type,
				"default" => trim($row['default_expression']),
				"null" => $nullable,
				"auto_increment" => '0',
				"privileges" => array("insert" => 1, "select" => 1, "update" => 0),
			);
		}

		return $return;
	}

	function indexes($table, $connection2 = null) {
		return array();
	}

	function foreign_keys($table) {
		return array();
	}

	function collations() {
		return array();
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		global $connection;
		return h($connection->error);
	}

	function types() {
		return array();
	}

	function schemas() {
		return array();
	}

	function get_schema() {
		return "";
	}

	function set_schema($schema) {
		return true;
	}

	function auto_increment() {
		return '';
	}

	function support($feature) {
		return preg_match("~^(columns|sql|status|table)$~", $feature);
	}

	$jush = "clickhouse";
	$types = array();
	$structured_types = array();
	foreach (array( //! arrays
		lang('Numbers') => array("Int8" => 3, "Int16" => 5, "Int32" => 10, "Int64" => 19, "UInt8" => 3, "UInt16" => 5, "UInt32" => 10, "UInt64" => 20, "Float32" => 7, "Float64" => 16),
		lang('Date and time') => array("Date" => 13, "DateTime" => 20),
		lang('Strings') => array("String" => 0),
		lang('Binary') => array("FixedString" => 0),
	) as $key => $val) {
		$types += $val;
		$structured_types[$key] = array_keys($val);
	}
	$unsigned = array();
	$operators = array("=", "<", ">", "<=", ">=", "!=", "~", "!~", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL");
	$functions = array();
	$grouping = array("avg", "count", "count distinct", "max", "min", "sum");
	$edit_functions = array();
}
