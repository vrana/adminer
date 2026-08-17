<?php
namespace Adminer;

add_driver("clickhouse", "ClickHouse");

if (isset($_GET["clickhouse"])) {
	define('Adminer\DRIVER', "clickhouse");

	if (ini_bool('allow_url_fopen')) {
		class Db extends SqlDb {
			public $extension = "JSON";
			public $_db = 'default';
			private $url;
			private $authorization;

			function rootQuery($db, $query) {
				$this->error = '';
				$this->errno = 0;
				$this->affected_rows = 0;
				list($file, $status, $headers, $error) = get_url($this->url . "/?database=" . rawurlencode($db), stream_context_create(array('http' => array(
					'method' => 'POST',
					'content' => $query,
					'header' => array(
						'Authorization: Basic ' . $this->authorization,
						'Content-Type: text/plain; charset=UTF-8',
						'X-ClickHouse-Format: JSONCompact',
					),
					'ignore_errors' => 1,
					'follow_location' => 0,
					'max_redirects' => 0,
				))));

				if ($status == 401 || $status == 403) {
					$this->error = lang('Invalid credentials.');
					$this->errno = $status;
					return false;
				}
				if ($file === false) {
					$this->error = ($error ?: 'Unable to connect to the ClickHouse server.');
					return false;
				}

				$exception = null;
				foreach ($headers as $header) {
					if (preg_match('~^X-ClickHouse-Exception-Code:\s*(\d+)~i', $header, $match)) { // ClickHouse sends this header in the errors created by itself
						$exception = (int) $match[1];
					}
					// the header repeats with progress, the last one is final; it is missing for some commands
					if (preg_match('~^X-ClickHouse-Summary:\s*(.+)~i', $header, $match)) {
						$this->affected_rows = (int) idx((array) json_decode($match[1], true), 'written_rows', 0);
					}
				}

				if ($status < 200 || $status >= 300) {
					$this->errno = ($exception ?: (int) $status);
					$this->error = ($exception === null // the response of a server which is not ClickHouse is never printed
						? lang('Invalid server or credentials.') . " HTTP $status"
						: (trim($file) ?: "ClickHouse HTTP error $status.")
					);
					return false;
				}

				if (trim($file) === '') {
					return true;
				}

				$return = json_decode($file, true);
				if (!is_array($return) || !isset($return['data']) || !isset($return['meta'])) {
					$this->errno = json_last_error();
					$this->error = ($this->errno && function_exists('json_last_error_msg')
						? json_last_error_msg()
						: 'Unexpected response returned by ClickHouse.'
					);
					return false;
				}
				return new Result($return);
			}

			function query($query, $unbuffered = false) {
				if (preg_match('~^\s*USE\s+(?:`((?:``|[^`])+)`|([A-Za-z_][A-Za-z0-9_]*))\s*;?\s*$~i', $query, $match)) {
					$this->_db = str_replace("``", "`", ($match[1] !== '' ? $match[1] : $match[2]));
					return true;
				}
				return $this->rootQuery($this->_db, $query);
			}

			function attach($server, $username, $password): string {
				$this->url = rtrim((preg_match('~^https?://~i', $server) ? $server : "http://$server"), '/');
				if (!preg_match('~:\d+$~', $this->url)) { // connect() allows no path so this can be only the port
					$this->url .= (preg_match('~^https://~i', $this->url) ? ":8443" : ":8123");
				}
				$this->authorization = base64_encode("$username:$password");
				$return = $this->query('SELECT version()');
				if (!$return) {
					return $this->error;
				}
				$row = $return->fetch_row();
				$this->server_info = ($row ? $row[0] : '');
				return '';
			}

			function select_db($database) {
				$this->_db = $database;
				return true;
			}

			function quote($string): string {
				return "'" . strtr($string, array(
					"\\" => "\\\\",
					"'" => "\\'",
					"\0" => "\\0",
					"\b" => "\\b",
					"\f" => "\\f",
					"\n" => "\\n",
					"\r" => "\\r",
					"\t" => "\\t",
				)) . "'";
			}
		}

		class Result {
			public $num_rows, $columns, $meta;
			private $rows = array(), $rowOffset = 0, $fieldOffset = 0;

			function __construct($result) {
				$this->meta = (array) $result['meta'];
				foreach ((array) $result['data'] as $item) {
					$row = array();
					foreach ((array) $item as $key => $val) {
						$type = (isset($this->meta[$key]['type']) ? $this->meta[$key]['type'] : '');
						$row[$key] = ($val === null || is_scalar($val)
							? $this->normalizeValue($val, $type)
							: json_encode($val, 256 | 64) // JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES available since PHP 5.4
						);
					}
					$this->rows[] = $row;
				}
				$this->num_rows = (isset($result['rows']) ? $result['rows'] : count($this->rows));
				$this->columns = array_map(function ($column) {
					return $column['name'];
				}, $this->meta); // array_column() is available since PHP 5.5
			}

			private function normalizeValue($value, $type) {
				// FixedString is NUL-padded to its declared width. The padding is
				// storage detail rather than user data and breaks Adminer links and
				// form controls if it is allowed through to the HTML response.
				if (is_string($value) && preg_match('~(?:^|\()FixedString\(\d+\)~', $type)) {
					return rtrim($value, "\0");
				}
				return $value;
			}

			function fetch_assoc() {
				if (!isset($this->rows[$this->rowOffset])) {
					return false;
				}
				return array_combine($this->columns, $this->rows[$this->rowOffset++]);
			}

			function fetch_row() {
				return (isset($this->rows[$this->rowOffset]) ? $this->rows[$this->rowOffset++] : false);
			}

			function fetch_field(): \stdClass {
				$column = $this->fieldOffset++;
				$return = new \stdClass;
				if ($column < count($this->columns)) {
					$return->name = $this->meta[$column]['name'];
					$return->type = $this->meta[$column]['type'];
					$return->charsetnr = 0;
				}
				return $return;
			}

			function seek($offset) {
				$this->rowOffset = max(0, (int) $offset);
			}
		}
	}

	class Driver extends SqlDriver {
		static $extensions = array("allow_url_fopen");
		static $jush = "clickhouse";

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "ILIKE", "ILIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT ILIKE", "NOT IN", "IS NOT NULL", "SQL");
		public $functions = array("length", "lower", "round", "toDate", "toDateTime", "toString", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");
		public $insertFunctions = array("Date|DateTime" => "now");
		public $editFunctions = array(
			"Int|UInt|Float|Decimal" => "+/-",
			"String|FixedString" => "concat",
		);
		public $generated = array("MATERIALIZED", "ALIAS", "EPHEMERAL");

		/** Get the JUSH module inlined in the released driver by the release script */
		static function jushModule(): string {
			return ""; // the repository and the source archive load adminer/static/jush/modules/jush-clickhouse.js
		}

		static function connect($server, $username, $password) {
			if (!preg_match('~^(https?://)?(\[[\da-f:.]+\]|[-\w.]+)(:\d+)?/?$~i', $server)) {
				return lang('Invalid server.');
			}
			return parent::connect($server, $username, $password);
		}

		function hasCStyleEscapes(): bool {
			return true;
		}

		function supportsAlterIndex(array $table_status): bool {
			return false; // indexes are listed for information only, altering them requires ClickHouse specific syntax
		}

		function slowQuery(string $query, int $timeout) {
			return "$query SETTINGS max_execution_time = $timeout";
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array(
				lang('Numbers') => array(
					"Int8" => 3, "Int16" => 5, "Int32" => 10, "Int64" => 19,
					"UInt8" => 3, "UInt16" => 5, "UInt32" => 10, "UInt64" => 20,
					"Int128" => 39, "Int256" => 78, "UInt128" => 39, "UInt256" => 78,
					"Float32" => 14, "Float64" => 23, "BFloat16" => 7, "Bool" => 1,
					"Decimal" => 76, "Decimal32" => 9, "Decimal64" => 18,
					"Decimal128" => 38, "Decimal256" => 76,
				),
				lang('Date and time') => array(
					"Date" => 10, "Date32" => 10, "DateTime" => 19, "DateTime64" => 29,
				),
				lang('Strings') => array("String" => 0, "FixedString" => 0),
				lang('Other') => array(
					"UUID" => 36, "IPv4" => 15, "IPv6" => 39,
					"Enum8" => 0, "Enum16" => 0, "Array" => 0, "Map" => 0,
					"Tuple" => 0, "Nested" => 0, "LowCardinality" => 0,
					"AggregateFunction" => 0, "SimpleAggregateFunction" => 0,
					"Variant" => 0, "Dynamic" => 0, "JSON" => 0,
				),
			);
		}

		function engines(): array {
			$engines = get_vals("SELECT name FROM system.table_engines ORDER BY name");
			return ($engines ?: array("MergeTree", "ReplacingMergeTree", "Memory", "Log", "TinyLog"));
		}

		function allFields(): array {
			$return = array();
			$rows = get_rows(
				"SELECT c." . idf_escape('table') . " AS " . idf_escape('table')
				. ", c.name, c.type, c.default_kind, c.default_expression, c.comment, "
				. "c.is_in_primary_key, c.is_in_sorting_key, t.engine AS table_engine "
				. "FROM system.columns AS c LEFT JOIN system.tables AS t "
				. "ON c.database = t.database AND c." . idf_escape('table') . " = t.name "
				. "WHERE c.database = " . q($this->conn->_db)
				. " ORDER BY c." . idf_escape('table') . ", c.position"
			);
			foreach ($rows as $row) {
				$return[$row['table']][] = clickhouse_field($row);
			}
			return $return;
		}

		function delete($table, $queryWhere, $limit = 0) {
			if ($queryWhere === '') {
				$queryWhere = 'WHERE 1=1';
			}
			return queries("ALTER TABLE " . table($table) . " DELETE $queryWhere");
		}

		function update($table, array $set, $queryWhere, $limit = 0, $separator = "\n") {
			$values = array();
			foreach ($set as $key => $val) {
				$values[] = "$key = $val";
			}
			$query = $separator . implode(",$separator", $values);
			return queries("ALTER TABLE " . table($table) . " UPDATE $query$queryWhere");
		}

		function insert($table, array $set) {
			if (!$set) {
				$this->conn->error = 'ClickHouse does not support DEFAULT VALUES without an explicit column list.';
				return false;
			}
			return parent::insert($table, $set);
		}
	}

	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function clickhouse_qualified($database, $name) {
		return idf_escape($database) . "." . idf_escape($name);
	}

	function clickhouse_type_info($fullType) {
		$fullType = trim($fullType);
		$type = $fullType;
		$nullable = false;
		if (preg_match('~^Nullable\((.*)\)$~s', $type, $match)) {
			$nullable = true;
			$type = $match[1];
		}
		if (preg_match('~^([A-Za-z][A-Za-z0-9_]*)(?:\((.*)\))?$~s', $type, $match)) {
			return array($match[1], isset($match[2]) ? $match[2] : '', $nullable);
		}
		return array($type, '', $nullable);
	}

	function clickhouse_default_value($expression) {
		if (preg_match("~^'(.*)'$~s", $expression, $match)) {
			return stripcslashes(str_replace("''", "'", $match[1]));
		}
		return $expression;
	}

	function clickhouse_field($row) {
		list($type, $length, $nullable) = clickhouse_type_info($row['type']);
		$defaultKind = strtoupper(trim($row['default_kind']));
		$generated = (in_array($defaultKind, array("MATERIALIZED", "ALIAS", "EPHEMERAL"), true)
			? $defaultKind
			: ''
		);
		$engine = (isset($row['table_engine']) ? $row['table_engine'] : '');
		$isView = (bool) preg_match('~View$~', $engine);
		$privileges = array("select" => 1, "where" => 1, "order" => 1);
		if (!$generated && !$isView) {
			$privileges["insert"] = 1;
		}
		if (!$generated && preg_match('~MergeTree$~', $engine)) {
			$privileges["update"] = 1;
		}
		return array(
			"field" => trim($row['name']),
			"full_type" => trim($row['type']),
			"type" => $type,
			"length" => $length,
			"default" => ($defaultKind ? clickhouse_default_value(trim($row['default_expression'])) : null),
			"null" => $nullable,
			"auto_increment" => false,
			"on_update" => "",
			"collation" => "",
			"privileges" => $privileges,
			"comment" => trim($row['comment']),
			"primary" => false, // ClickHouse primary keys do not guarantee uniqueness.
			"generated" => $generated,
		);
	}

	function clickhouse_field_definition($parts) {
		$name = $parts[0];
		$type = trim($parts[1]);
		if (isset($parts[2]) && trim($parts[2]) === "NULL" && strpos($type, 'Nullable(') !== 0) {
			$type = "Nullable($type)";
		}
		$default = (isset($parts[3]) ? $parts[3] : '');
		if (preg_match('~^\s*GENERATED ALWAYS AS \((.*)\)\s+(MATERIALIZED|ALIAS|EPHEMERAL)\s*$~s', $default, $match)) {
			$default = " $match[2] $match[1]";
		}
		$comment = (isset($parts[5]) && preg_match('~^\s*COMMENT\b~i', $parts[5])
			? $parts[5]
			: (isset($parts[4]) && preg_match('~^\s*COMMENT\b~i', $parts[4]) ? $parts[4] : '')
		);
		return "$name $type$default$comment";
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}

	function found_rows($table_status, $where) {
		return get_val("SELECT count() FROM " . table($table_status["Name"]) . ($where ? " WHERE " . implode(" AND ", $where) : ""));
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		if ($table === "") {
			$definitions = array();
			foreach ($fields as $field) {
				if (!empty($field[1])) {
					$definitions[] = clickhouse_field_definition($field[1]);
				}
			}
			$engine = ($engine ?: "MergeTree");
			if (!preg_match('~^[A-Za-z][A-Za-z0-9_]*$~', $engine)) {
				connection()->error = 'Invalid ClickHouse table engine.';
				return false;
			}
			$status = " ENGINE = $engine";
			if (preg_match('~MergeTree$~', $engine)) {
				$status .= " ORDER BY tuple()";
			}
			$result = queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $definitions) . "\n)$status");
			if ($result && $comment !== null && $comment !== '') {
				$result = queries("ALTER TABLE " . table($name) . " MODIFY COMMENT " . q($comment));
			}
			return $result;
		}

		if ($foreign) {
			connection()->error = 'ClickHouse does not support foreign keys.';
			return false;
		}
		if ($engine !== '') {
			connection()->error = 'ClickHouse cannot change a table engine with ALTER TABLE.';
			return false;
		}
		if ($collation !== '' || $auto_increment !== '') {
			connection()->error = 'ClickHouse does not support table collations or auto-increment values.';
			return false;
		}

		if ($table !== $name) {
			if (!queries("RENAME TABLE " . table($table) . " TO " . table($name))) {
				return false;
			}
			$table = $name;
		}

		$result = true;
		foreach ($fields as $field) {
			if (empty($field[1])) {
				$result = queries("ALTER TABLE " . table($table) . " DROP COLUMN " . idf_escape($field[0]));
				if (!$result) {
					return false;
				}
				continue;
			}

			$newName = $field[1][0];
			if ($field[0] !== "" && idf_escape($field[0]) !== $newName) {
				if (!queries("ALTER TABLE " . table($table) . " RENAME COLUMN " . idf_escape($field[0]) . " TO $newName")) {
					return false;
				}
			}
			$operation = ($field[0] === "" ? "ADD COLUMN" : "MODIFY COLUMN");
			$order = (isset($field[2]) ? $field[2] : '');
			$result = queries("ALTER TABLE " . table($table) . " $operation " . clickhouse_field_definition($field[1]) . $order);
			if (!$result) {
				return false;
			}

			if ($field[0] != "" && $field[1][3] == "" && min_version("20.10")) {
				// MODIFY COLUMN without DEFAULT keeps the original default value, $newName is used because the column is already renamed
				if (!queries("ALTER TABLE " . table($table) . " MODIFY COLUMN $newName REMOVE DEFAULT")) {
					return false;
				}
			}
		}

		if ($comment !== null) {
			$result = queries("ALTER TABLE " . table($table) . " MODIFY COMMENT " . q($comment));
		}
		return $result;
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function get_databases($flush) {
		$return = get_session("dbs");
		if ($flush || $return === null) {
			$return = get_vals("SELECT name FROM system.databases ORDER BY name");
			restart_session();
			set_session("dbs", $return);
			stop_session();
		}
		return $return;
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation($db, $collations) {
		return null;
	}

	function logged_user() {
		return get_val("SELECT currentUser()");
	}

	function tables_list() {
		$result = get_rows(
			"SELECT name, engine FROM system.tables WHERE database = " . q(connection()->_db) . " ORDER BY name"
		);
		$return = array();
		foreach ($result as $row) {
			$return[$row['name']] = ($row['engine'] === 'View' ? 'VIEW' : 'TABLE');
		}
		return $return;
	}

	function count_tables($databases) {
		$return = array_fill_keys($databases, 0);
		if (!$databases) {
			return $return;
		}
		$quoted = array_map('Adminer\q', $databases);
		foreach (
			get_rows(
				"SELECT database, count() AS tables FROM system.tables "
				. "WHERE database IN (" . implode(", ", $quoted) . ") GROUP BY database"
			) as $row
		) {
			$return[$row['database']] = $row['tables'];
		}
		return $return;
	}

	function table_status($name = "", $fast = false) {
		$return = array();
		$tables = get_rows(
			"SELECT name, engine, total_rows, total_bytes, comment, sorting_key "
			. "FROM system.tables WHERE database = " . q(connection()->_db)
			. ($name != "" ? " AND name = " . q($name) : " ORDER BY name")
		);
		foreach ($tables as $row) {
			$return[$row['name']] = array(
				'Name' => $row['name'],
				'Engine' => $row['engine'],
				'Comment' => $row['comment'],
				'Rows' => $row['total_rows'],
				'Data_length' => $row['total_bytes'],
				'Index_length' => 0,
				'Data_free' => 0,
				'Auto_increment' => '',
				'Collation' => '',
				'Create_options' => ($row['sorting_key'] ? "ORDER BY $row[sorting_key]" : ''),
			);
		}
		return $return;
	}

	function is_view($table_status) {
		// Adminer's generic editor can safely replace ordinary views. Materialized
		// views need ClickHouse-specific ENGINE/TO clauses, so expose them as tables.
		return $table_status['Engine'] === 'View';
	}

	function fk_support($table_status) {
		return false;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		if ($return !== "NULL" && in_array($field['type'], array("Array", "Map", "Tuple"), true)) {
			return "JSONExtract($return, " . q($field['full_type']) . ")";
		}
		if ($return !== "NULL" && $field['full_type'] !== "String") {
			return "CAST($return AS $field[full_type])";
		}
		return $return;
	}

	function fields($table) {
		$return = array();
		$result = get_rows(
			"SELECT c.name, c.type, c.default_kind, c.default_expression, c.comment, "
			. "c.is_in_primary_key, c.is_in_sorting_key, t.engine AS table_engine "
			. "FROM system.columns AS c LEFT JOIN system.tables AS t "
			. "ON c.database = t.database AND c." . idf_escape('table') . " = t.name "
			. "WHERE c.database = " . q(connection()->_db)
			. " AND c." . idf_escape('table') . " = " . q($table)
			. " ORDER BY c.position"
		);
		foreach ($result as $row) {
			$return[trim($row['name'])] = clickhouse_field($row);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$conn = connection($connection2);
		$return = array();
		$rows = get_rows(
			"SELECT primary_key, sorting_key FROM system.tables "
			. "WHERE database = " . q($conn->_db) . " AND name = " . q($table),
			$connection2
		);
		if ($rows) {
			$row = $rows[0];
			// the types are not PRIMARY so that unique_array() doesn't identify rows by them, ClickHouse keys are not unique
			if ($row["primary_key"] !== "") {
				$return["PRIMARY KEY"] = clickhouse_index("PRIMARY KEY", $row["primary_key"]);
			}
			if ($row["sorting_key"] !== "" && $row["sorting_key"] !== $row["primary_key"]) {
				$return["SORTING KEY"] = clickhouse_index("SORTING KEY", $row["sorting_key"]);
			}
		}

		static $skipping; // the answer is the same for the whole request, indexes() is called for each table in the schema
		if ($skipping === null) {
			$skipping = (bool) get_val("EXISTS TABLE system.data_skipping_indices", 0, $connection2);
		}
		if ($skipping) {
			$rows = get_rows(
				"SELECT name, expr, type_full, granularity FROM system.data_skipping_indices "
				. "WHERE database = " . q($conn->_db) . " AND " . idf_escape('table') . " = " . q($table)
				. " ORDER BY name",
				$connection2
			);
			foreach ($rows as $row) {
				$definition = "$row[expr] TYPE $row[type_full] GRANULARITY $row[granularity]";
				$return[$row["name"]] = clickhouse_index("INDEX", $definition);
			}
		}
		return $return;
	}

	function clickhouse_index($type, $definition) {
		return array(
			"type" => $type,
			"columns" => array($definition),
			"lengths" => array(null),
			"descs" => array(null),
			"algorithm" => "",
			"partial" => "",
		);
	}

	function alter_indexes($table, $alter) {
		// the page is not linked anywhere, see Driver::supportsAlterIndex()
		connection()->error = 'ClickHouse indexes cannot be altered by Adminer. Use the SQL command page.';
		return false;
	}

	function foreign_keys($table) {
		return array();
	}

	function view($name) {
		$create = get_val(
			"SELECT create_table_query FROM system.tables WHERE database = " . q(connection()->_db)
			. " AND name = " . q($name)
		);
		if (preg_match('~\s+AS\s+(?=(?:SELECT|WITH)\b)~i', $create, $match, PREG_OFFSET_CAPTURE)) {
			$offset = $match[0][1] + strlen($match[0][0]);
			return array("select" => substr($create, $offset));
		}
		return array("select" => "");
	}

	function collations() {
		return array();
	}

	function information_schema($db) {
		return in_array($db, array("system", "information_schema", "INFORMATION_SCHEMA"), true);
	}

	function error() {
		return h(connection()->error);
	}

	function create_database($db, $collation) {
		$return = queries("CREATE DATABASE " . idf_escape($db));
		if ($return) {
			restart_session();
			set_session("dbs", null);
		}
		return $return;
	}

	function drop_databases($databases) {
		$return = apply_queries("DROP DATABASE", $databases, 'Adminer\idf_escape');
		restart_session();
		set_session("dbs", null);
		return $return;
	}

	function rename_database($name, $collation) {
		$return = queries("RENAME DATABASE " . idf_escape(connection()->_db) . " TO " . idf_escape($name));
		if ($return) {
			connection()->_db = $name;
			restart_session();
			set_session("dbs", null);
		}
		return (bool) $return;
	}

	function move_tables($tables, $views, $target) {
		$source = connection()->_db;
		foreach (array_merge($tables, $views) as $name) {
			if (
				!queries(
					"RENAME TABLE " . clickhouse_qualified($source, $name)
					. " TO " . clickhouse_qualified($target, $name)
				)
			) {
				return false;
			}
		}
		return true;
	}

	function copy_tables($tables, $views, $target) {
		$source = connection()->_db;
		$overwrite = !empty($_POST["overwrite"]);
		foreach ($tables as $name) {
			$destination = clickhouse_qualified($target, $name);
			if (
				($overwrite && !queries("DROP TABLE IF EXISTS $destination"))
				|| !queries("CREATE TABLE $destination AS " . clickhouse_qualified($source, $name))
				|| !queries("INSERT INTO $destination SELECT * FROM " . clickhouse_qualified($source, $name))
			) {
				return false;
			}
		}
		foreach ($views as $name) {
			$destination = clickhouse_qualified($target, $name);
			$definition = view($name);
			if (
				($overwrite && !queries("DROP VIEW IF EXISTS $destination"))
				|| !$definition["select"]
				|| !queries("CREATE VIEW $destination AS $definition[select]")
			) {
				return false;
			}
		}
		return true;
	}

	function create_sql($table, $auto_increment, $style) {
		return get_val(
			"SELECT create_table_query FROM system.tables WHERE database = " . q(connection()->_db)
			. " AND name = " . q($table)
		);
	}

	function truncate_sql($table) {
		return "TRUNCATE TABLE " . table($table);
	}

	function use_sql($database, $style = "") {
		$name = idf_escape($database);
		$return = "";
		if (preg_match('~CREATE~', $style)) {
			if ($style === "DROP+CREATE") {
				$return .= "DROP DATABASE IF EXISTS $name;\n";
			}
			$return .= "CREATE DATABASE IF NOT EXISTS $name;\n";
		}
		return $return . "USE $name";
	}

	function show_variables() {
		return get_rows(
			"SELECT name, value, changed, description FROM system.settings ORDER BY name"
		);
	}

	function show_status() {
		return get_rows(
			"SELECT metric AS name, toString(value) AS value, description "
			. "FROM system.metrics ORDER BY metric"
		);
	}

	function process_list() {
		return get_rows(
			"SELECT query_id AS pid, user, address, elapsed, read_rows, read_bytes, "
			. "written_rows, written_bytes, memory_usage, query "
			. "FROM system.processes ORDER BY elapsed DESC"
		);
	}

	function kill_process($id) {
		return queries("KILL QUERY WHERE query_id = " . q($id) . " SYNC");
	}

	function max_connections() {
		$value = get_val("SELECT value FROM system.settings WHERE name = 'max_concurrent_queries'");
		return ($value === false ? "0" : $value);
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
		return (bool) preg_match(
			"~^(columns|comment|copy|database|drop_col|dump|indexes|kill|move_col|processlist|sql|status|table|variables|view)$~",
			$feature
		);
	}
}
