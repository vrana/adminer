<?php
namespace Adminer;

add_driver("clickhouse", "ClickHouse (alpha)");

if (isset($_GET["clickhouse"])) {
	define('Adminer\DRIVER', "clickhouse");

	if (ini_bool('allow_url_fopen')) {
		class Db extends SqlDb {
			public string $extension = "JSON";
			public $_db = 'default';
			private $url;

			function rootQuery($db, $query) {
				$file = @file_get_contents("$this->url/?database=$db", false, stream_context_create(array('http' => array(
					'method' => 'POST',
					'content' => $this->isQuerySelectLike($query) ? "$query FORMAT JSONCompact" : $query,
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'ignore_errors' => 1,
					'follow_location' => 0,
					'max_redirects' => 0,
				))));

				if ($file === false || !preg_match('~^HTTP/[0-9.]+ 2~i', $http_response_header[0])) {
					$this->error = lang('Invalid credentials.');
					return false;
				}
				$return = json_decode($file, true);
				if ($return === null) {
					if (!$this->isQuerySelectLike($query) && $file === '') {
						return true;
					}

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
				return new Result($return);
			}

			function isQuerySelectLike($query) {
				return (bool) preg_match('~^(select|show)~i', $query);
			}

			function query(string $query, bool $unbuffered = false) {
				return $this->rootQuery($this->_db, $query);
			}

			function attach(?string $server, string $username, string $password): string {
				preg_match('~^(https?://)?(.*)~', $server, $match);
				$this->url = ($match[1] ?: "http://") . urlencode($username) . ":" . urlencode($password) . "@$match[2]";
				$return = $this->query('SELECT 1');
				return ($return ? '' : $this->error);
			}

			function select_db(string $database): bool {
				$this->_db = $database;
				return true;
			}

			function quote(string $string): string {
				return "'" . addcslashes($string, "\\'") . "'";
			}
		}

		class Result {
			public $num_rows, $columns, $meta;
			private $rows, $offset = 0;

			function __construct($result) {
				foreach ($result['data'] as $item) {
					$row = array();
					foreach ($item as $key => $val) {
						$row[$key] = is_scalar($val) ? $val : json_encode($val, 256); // 256 - JSON_UNESCAPED_UNICODE
					}
					$this->rows[] = $row;
				}
				$this->num_rows = $result['rows'];
				$this->meta = $result['meta'];
				$this->columns = array_column($this->meta, 'name');
				reset($this->rows);
			}

			function fetch_assoc() {
				$row = current($this->rows);
				next($this->rows);
				return $row === false ? false : array_combine($this->columns, $row);
			}

			function fetch_row() {
				$row = current($this->rows);
				next($this->rows);
				return $row;
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$return = new \stdClass;
				if ($column < count($this->columns)) {
					$return->name = $this->meta[$column]['name'];
					$return->type = $this->meta[$column]['type']; //! map to MySQL numbers
					$return->charsetnr = 0;
				}
				return $return;
			}
		}
	}

	class Driver extends SqlDriver {
		static array $extensions = array("allow_url_fopen");
		static string $jush = "clickhouse";

		public array $operators = array("=", "<", ">", "<=", ">=", "!=", "~", "!~", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL");
		public array $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		static function connect(?string $server, string $username, string $password) {
			if (!preg_match('~^(https?://)?[-a-z\d.]+(:\d+)?$~', $server)) {
				return lang('Invalid server.');
			}
			return parent::connect($server, $username, $password);
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! arrays
				lang('Numbers') => array(
					"Int8" => 3, "Int16" => 5, "Int32" => 10, "Int64" => 19,
					"UInt8" => 3, "UInt16" => 5, "UInt32" => 10, "UInt64" => 20,
					"Float32" => 7, "Float64" => 16,
					'Decimal' => 38, 'Decimal32' => 9, 'Decimal64' => 18, 'Decimal128' => 38,
				),
				lang('Date and time') => array("Date" => 13, "DateTime" => 20),
				lang('Strings') => array("String" => 0),
				lang('Binary') => array("FixedString" => 0),
			);
		}

		function delete(string $table, string $queryWhere, int $limit = 0) {
			if ($queryWhere === '') {
				$queryWhere = 'WHERE 1=1';
			}
			return queries("ALTER TABLE " . table($table) . " DELETE $queryWhere");
		}

		function update(string $table, array $set, string $queryWhere, int $limit = 0, string $separator = "\n") {
			$values = array();
			foreach ($set as $key => $val) {
				$values[] = "$key = $val";
			}
			$query = $separator . implode(",$separator", $values);
			return queries("ALTER TABLE " . table($table) . " UPDATE $query$queryWhere");
		}
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
		$alter = $order = array();
		foreach ($fields as $field) {
			if ($field[1][2] === " NULL") {
				$field[1][1] = " Nullable({$field[1][1]})";
			} elseif ($field[1][2] === ' NOT NULL') {
				$field[1][2] = '';
			}

			if ($field[1][3]) {
				$field[1][3] = '';
			}

			$alter[] = ($field[1]
				? ($table != "" ? ($field[0] != "" ? "MODIFY COLUMN " : "ADD COLUMN ") : " ") . implode($field[1])
				: "DROP COLUMN " . idf_escape($field[0])
			);

			$order[] = $field[1][0];
		}

		$alter = array_merge($alter, $foreign);
		$status = ($engine ? " ENGINE " . $engine : "");
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status$partitioning" . ' ORDER BY (' . implode(',', $order) . ')');
		}
		if ($table != $name) {
			$result = queries("RENAME TABLE " . table($table) . " TO " . table($name));
			if ($alter) {
				$table = $name;
			} else {
				return $result;
			}
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
		return drop_tables($views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function get_databases($flush) {
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

	function logged_user() {
		$credentials = adminer()->credentials();
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
		$return = array();
		$tables = get_rows("SELECT name, engine FROM system.tables WHERE database = " . q(connection()->_db));
		foreach ($tables as $table) {
			$return[$table['name']] = array(
				'Name' => $table['name'],
				'Engine' => $table['engine'],
			);
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
		if (in_array($field['type'], array("Int8", "Int16", "Int32", "Int64", "UInt8", "UInt16", "UInt32", "UInt64", "Float32", "Float64"))) {
			return "to$field[type]($return)";
		}
		return $return;
	}

	function fields($table) {
		$return = array();
		$result = get_rows("SELECT name, type, default_expression FROM system.columns WHERE " . idf_escape('table') . " = " . q($table));
		foreach ($result as $row) {
			$type = trim($row['type']);
			$nullable = strpos($type, 'Nullable(') === 0;
			$return[trim($row['name'])] = array(
				"field" => trim($row['name']),
				"full_type" => $type,
				"type" => $type,
				"default" => trim($row['default_expression']),
				"null" => $nullable,
				"auto_increment" => '0',
				"privileges" => array("insert" => 1, "select" => 1, "update" => 0, "where" => 1, "order" => 1),
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
		return h(connection()->error);
	}

	function types(): array {
		return array();
	}

	function auto_increment() {
		return '';
	}

	function last_id($result) {
		return 0; // ClickHouse doesn't have it
	}

	function support($feature) {
		return preg_match("~^(columns|sql|status|table|drop_col)$~", $feature);
	}
}
