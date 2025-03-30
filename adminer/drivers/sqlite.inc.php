<?php
namespace Adminer;

add_driver("sqlite", "SQLite");

if (isset($_GET["sqlite"])) {
	define('Adminer\DRIVER', "sqlite");
	if (class_exists("SQLite3") && $_GET["ext"] != "pdo") {

		abstract class SqliteDb extends SqlDb {
			public string $extension = "SQLite3";
			private $link;

			function attach(?string $filename, string $username, string $password): string {
				$this->link = new \SQLite3($filename);
				$version = $this->link->version();
				$this->server_info = $version["versionString"];
				return '';
			}

			function query(string $query, bool $unbuffered = false) {
				$result = @$this->link->query($query);
				$this->error = "";
				if (!$result) {
					$this->errno = $this->link->lastErrorCode();
					$this->error = $this->link->lastErrorMsg();
					return false;
				} elseif ($result->numColumns()) {
					return new Result($result);
				}
				$this->affected_rows = $this->link->changes();
				return true;
			}

			function quote(string $string): string {
				return (is_utf8($string)
					? "'" . $this->link->escapeString($string) . "'"
					: "x'" . first(unpack('H*', $string)) . "'"
				);
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
			}

			function fetch_assoc() {
				return $this->result->fetchArray(SQLITE3_ASSOC);
			}

			function fetch_row() {
				return $this->result->fetchArray(SQLITE3_NUM);
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$type = $this->result->columnType($column);
				return (object) array(
					"name" => $this->result->columnName($column),
					"type" => ($type == SQLITE3_TEXT ? 15 : 0),
					"charsetnr" => ($type == SQLITE3_BLOB ? 63 : 0), // 63 - binary
				);
			}

			function __destruct() {
				$this->result->finalize();
			}
		}

	} elseif (extension_loaded("pdo_sqlite")) {
		abstract class SqliteDb extends PdoDb {
			public string $extension = "PDO_SQLite";

			function attach(?string $filename, string $username, string $password): string {
				$this->dsn(DRIVER . ":$filename", "", "");
				$this->query("PRAGMA foreign_keys = 1");
				$this->query("PRAGMA busy_timeout = 500");
				return '';
			}
		}

	}

	if (class_exists('Adminer\SqliteDb')) {
		class Db extends SqliteDb {
			function attach(?string $filename, string $username, string $password): string {
				parent::attach($filename, $username, $password);
				$this->query("PRAGMA foreign_keys = 1");
				$this->query("PRAGMA busy_timeout = 500");
				return '';
			}

			function select_db(string $filename): bool {
				if (is_readable($filename) && $this->query("ATTACH " . $this->quote(preg_match("~(^[/\\\\]|:)~", $filename) ? $filename : dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a")) {
					return !self::attach($filename, '', '');
				}
				return false;
			}
		}
	}



	class Driver extends SqlDriver {
		static array $extensions = array("SQLite3", "PDO_SQLite");
		static string $jush = "sqlite";

		protected array $types = array(array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0));

		public array $insertFunctions = array(); // "text" => "date('now')/time('now')/datetime('now')",
		public array $editFunctions = array(
			"integer|real|numeric" => "+/-",
			// "text" => "date/time/datetime",
			"text" => "||",
		);

		public array $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"); // REGEXP can be user defined function
		public array $functions = array("hex", "length", "lower", "round", "unixepoch", "upper");
		public array $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");

		static function connect(?string $server, string $username, string $password) {
			if ($password != "") {
				return lang('Database does not support password.');
			}
			return parent::connect(":memory:", "", "");
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			if (min_version(3.31, 0, $connection)) {
				$this->generated = array("STORED", "VIRTUAL");
			}
		}

		function structuredTypes(): array {
			return array_keys($this->types[0]);
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			return queries("REPLACE INTO " . table($table) . " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
		}

		function tableHelp(string $name, bool $is_view = false) {
			if ($name == "sqlite_sequence") {
				return "fileformat2.html#seqtab";
			}
			if ($name == "sqlite_master") {
				return "fileformat2.html#$name";
			}
		}

		function checkConstraints(string $table): array {
			preg_match_all('~ CHECK *(\( *(((?>[^()]*[^() ])|(?1))*) *\))~', get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $this->conn), $matches); //! could be inside a comment
			return array_combine($matches[2], $matches[2]);
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function get_databases($flush) {
		return array();
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return (preg_match('~^INTO~', $query) || get_val("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')")
			? limit($query, $where, 1, 0, $separator)
			: " $query WHERE rowid = (SELECT rowid FROM " . table($table) . $where . $separator . "LIMIT 1)" //! use primary key in tables with WITHOUT rowid
		);
	}

	function db_collation($db, $collations) {
		return get_val("PRAGMA encoding"); // there is no database list so $db == DB
	}

	function logged_user() {
		return get_current_user(); // should return effective user
	}

	function tables_list() {
		return get_key_vals("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "") {
		$return = array();
		foreach (get_rows("SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS Auto_increment FROM sqlite_master WHERE type IN ('table', 'view') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row) {
			$row["Rows"] = get_val("SELECT COUNT(*) FROM " . idf_escape($row["Name"]));
			$return[$row["Name"]] = $row;
		}
		foreach (get_rows("SELECT * FROM sqlite_sequence", null, "") as $row) {
			$return[$row["name"]]["Auto_increment"] = $row["seq"];
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function fk_support($table_status) {
		return !get_val("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
	}

	function fields($table) {
		$return = array();
		$primary = "";
		foreach (get_rows("PRAGMA table_" . (min_version(3.31) ? "x" : "") . "info(" . table($table) . ")") as $row) {
			$name = $row["name"];
			$type = strtolower($row["type"]);
			$default = $row["dflt_value"];
			$return[$name] = array(
				"field" => $name,
				"type" => (preg_match('~int~i', $type) ? "integer" : (preg_match('~char|clob|text~i', $type) ? "text" : (preg_match('~blob~i', $type) ? "blob" : (preg_match('~real|floa|doub~i', $type) ? "real" : "numeric")))),
				"full_type" => $type,
				"default" => (preg_match("~^'(.*)'$~", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default)),
				"null" => !$row["notnull"],
				"privileges" => array("select" => 1, "insert" => 1, "update" => 1, "where" => 1, "order" => 1),
				"primary" => $row["pk"],
			);
			if ($row["pk"]) {
				if ($primary != "") {
					$return[$primary]["auto_increment"] = false;
				} elseif (preg_match('~^integer$~i', $type)) {
					$return[$name]["auto_increment"] = true;
				}
				$primary = $name;
			}
		}
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table));
		$idf = '(("[^"]*+")+|[a-z0-9_]+)';
		preg_match_all('~' . $idf . '\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			if ($return[$name]) {
				$return[$name]["collation"] = trim($match[3], "'");
			}
		}
		preg_match_all('~' . $idf . '\s.*GENERATED ALWAYS AS \((.+)\) (STORED|VIRTUAL)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			$return[$name]["default"] = $match[3];
			$return[$name]["generated"] = strtoupper($match[4]);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$connection2 = connection($connection2);
		$return = array();
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $connection2);
		if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $sql, $match)) {
			$return[""] = array("type" => "PRIMARY", "columns" => array(), "lengths" => array(), "descs" => array());
			preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i', $match[1], $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$return[""]["columns"][] = idf_unescape($match[2]) . $match[4];
				$return[""]["descs"][] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
			}
		}
		if (!$return) {
			foreach (fields($table) as $name => $field) {
				if ($field["primary"]) {
					$return[""] = array("type" => "PRIMARY", "columns" => array($name), "lengths" => array(), "descs" => array(null));
				}
			}
		}
		$sqls = get_key_vals("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . q($table), $connection2);
		foreach (get_rows("PRAGMA index_list(" . table($table) . ")", $connection2) as $row) {
			$name = $row["name"];
			$index = array("type" => ($row["unique"] ? "UNIQUE" : "INDEX"));
			$index["lengths"] = array();
			$index["descs"] = array();
			foreach (get_rows("PRAGMA index_info(" . idf_escape($name) . ")", $connection2) as $row1) {
				$index["columns"][] = $row1["name"];
				$index["descs"][] = null;
			}
			if (preg_match('~^CREATE( UNIQUE)? INDEX ' . preg_quote(idf_escape($name) . ' ON ' . idf_escape($table), '~') . ' \((.*)\)$~i', $sqls[$name], $regs)) {
				preg_match_all('/("[^"]*+")+( DESC)?/', $regs[2], $matches);
				foreach ($matches[2] as $key => $val) {
					if ($val) {
						$index["descs"][$key] = '1';
					}
				}
			}
			if (!$return[""] || $index["type"] != "UNIQUE" || $index["columns"] != $return[""]["columns"] || $index["descs"] != $return[""]["descs"] || !preg_match("~^sqlite_~", $name)) {
				$return[$name] = $index;
			}
		}
		return $return;
	}

	function foreign_keys($table) {
		$return = array();
		foreach (get_rows("PRAGMA foreign_key_list(" . table($table) . ")") as $row) {
			$foreign_key = &$return[$row["id"]];
			if (!$foreign_key) {
				$foreign_key = $row;
			}
			$foreign_key["source"][] = $row["from"];
			$foreign_key["target"][] = $row["to"];
		}
		return $return;
	}

	function view($name) {
		return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '', get_val("SELECT sql FROM sqlite_master WHERE type = 'view' AND name = " . q($name)))); //! identifiers may be inside []
	}

	function collations() {
		return (isset($_GET["create"]) ? get_vals("PRAGMA collation_list", 1) : array());
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		return h(connection()->error);
	}

	function check_sqlite_name($name) {
		// avoid creating PHP files on unsecured servers
		$extensions = "db|sdb|sqlite";
		if (!preg_match("~^[^\\0]*\\.($extensions)\$~", $name)) {
			connection()->error = lang('Please use one of the extensions %s.', str_replace("|", ", ", $extensions));
			return false;
		}
		return true;
	}

	function create_database($db, $collation) {
		if (file_exists($db)) {
			connection()->error = lang('File exists.');
			return false;
		}
		if (!check_sqlite_name($db)) {
			return false;
		}
		try {
			$link = new Db();
			$link->attach($db, '', '');
		} catch (\Exception $ex) {
			connection()->error = $ex->getMessage();
			return false;
		}
		$link->query('PRAGMA encoding = "UTF-8"');
		$link->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
		$link->query('DROP TABLE adminer');
		return true;
	}

	function drop_databases($databases) {
		connection()->attach(":memory:", '', ''); // to unlock file, doesn't work in PDO on Windows
		foreach ($databases as $db) {
			if (!@unlink($db)) {
				connection()->error = lang('File exists.');
				return false;
			}
		}
		return true;
	}

	function rename_database($name, $collation) {
		if (!check_sqlite_name($name)) {
			return false;
		}
		connection()->attach(":memory:", '', '');
		connection()->error = lang('File exists.');
		return @rename(DB, $name);
	}

	function auto_increment() {
		return " PRIMARY KEY AUTOINCREMENT";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$use_all_fields = ($table == "" || $foreign);
		foreach ($fields as $field) {
			if ($field[0] != "" || !$field[1] || $field[2]) {
				$use_all_fields = true;
				break;
			}
		}
		$alter = array();
		$originals = array();
		foreach ($fields as $field) {
			if ($field[1]) {
				$alter[] = ($use_all_fields ? $field[1] : "ADD " . implode($field[1]));
				if ($field[0] != "") {
					$originals[$field[0]] = $field[1][0];
				}
			}
		}
		if (!$use_all_fields) {
			foreach ($alter as $val) {
				if (!queries("ALTER TABLE " . table($table) . " $val")) {
					return false;
				}
			}
			if ($table != $name && !queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name))) {
				return false;
			}
		} elseif (!recreate_table($table, $name, $alter, $originals, $foreign, $auto_increment)) {
			return false;
		}
		if ($auto_increment) {
			queries("BEGIN");
			queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
			if (!connection()->affected_rows) {
				queries("INSERT INTO sqlite_sequence (name, seq) VALUES (" . q($name) . ", $auto_increment)");
			}
			queries("COMMIT");
		}
		return true;
	}

	/** Recreate table
	* @param string $table original name
	* @param string $name new name
	* @param list<list<string>> $fields [process_field()], empty to preserve
	* @param string[] $originals [$original => idf_escape($new_column)], empty to preserve
	* @param string[] $foreign [format_foreign_key()], empty to preserve
	* @param int $auto_increment set auto_increment to this value, 0 to preserve
	* @param list<array{string, string, list<string>|'DROP'}> $indexes [[$type, $name, $columns]], empty to preserve
	* @param string $drop_check CHECK constraint to drop
	* @param string $add_check CHECK constraint to add
	*/
	function recreate_table(string $table, string $name, array $fields, array $originals, array $foreign, int $auto_increment = 0, $indexes = array(), string $drop_check = "", string $add_check = ""): bool {
		if ($table != "") {
			if (!$fields) {
				foreach (fields($table) as $key => $field) {
					if ($indexes) {
						$field["auto_increment"] = 0;
					}
					$fields[] = process_field($field, $field);
					$originals[$key] = idf_escape($key);
				}
			}
			$primary_key = false;
			foreach ($fields as $field) {
				if ($field[6]) {
					$primary_key = true;
				}
			}
			$drop_indexes = array();
			foreach ($indexes as $key => $val) {
				if ($val[2] == "DROP") {
					$drop_indexes[$val[1]] = true;
					unset($indexes[$key]);
				}
			}
			foreach (indexes($table) as $key_name => $index) {
				$columns = array();
				foreach ($index["columns"] as $key => $column) {
					if (!$originals[$column]) {
						continue 2;
					}
					$columns[] = $originals[$column] . ($index["descs"][$key] ? " DESC" : "");
				}
				if (!$drop_indexes[$key_name]) {
					if ($index["type"] != "PRIMARY" || !$primary_key) {
						$indexes[] = array($index["type"], $key_name, $columns);
					}
				}
			}
			foreach ($indexes as $key => $val) {
				if ($val[0] == "PRIMARY") {
					unset($indexes[$key]);
					$foreign[] = "  PRIMARY KEY (" . implode(", ", $val[2]) . ")";
				}
			}
			foreach (foreign_keys($table) as $key_name => $foreign_key) {
				foreach ($foreign_key["source"] as $key => $column) {
					if (!$originals[$column]) {
						continue 2;
					}
					$foreign_key["source"][$key] = idf_unescape($originals[$column]);
				}
				if (!isset($foreign[" $key_name"])) {
					$foreign[] = " " . format_foreign_key($foreign_key);
				}
			}
			queries("BEGIN");
		}
		$changes = array();
		foreach ($fields as $field) {
			if (preg_match('~GENERATED~', $field[3])) {
				unset($originals[array_search($field[0], $originals)]);
			}
			$changes[] = "  " . implode($field);
		}
		$changes = array_merge($changes, array_filter($foreign));
		foreach (driver()->checkConstraints($table) as $check) {
			if ($check != $drop_check) {
				$changes[] = "  CHECK ($check)";
			}
		}
		if ($add_check) {
			$changes[] = "  CHECK ($add_check)";
		}
		$temp_name = ($table == $name ? "adminer_$name" : $name);
		if (!queries("CREATE TABLE " . table($temp_name) . " (\n" . implode(",\n", $changes) . "\n)")) {
			// implicit ROLLBACK to not overwrite connection()->error
			return false;
		}
		if ($table != "") {
			if ($originals && !queries("INSERT INTO " . table($temp_name) . " (" . implode(", ", $originals) . ") SELECT " . implode(", ", array_map('Adminer\idf_escape', array_keys($originals))) . " FROM " . table($table))) {
				return false;
			}
			$triggers = array();
			foreach (triggers($table) as $trigger_name => $timing_event) {
				$trigger = trigger($trigger_name, $table);
				$triggers[] = "CREATE TRIGGER " . idf_escape($trigger_name) . " " . implode(" ", $timing_event) . " ON " . table($name) . "\n$trigger[Statement]";
			}
			$auto_increment = $auto_increment ? 0 : get_val("SELECT seq FROM sqlite_sequence WHERE name = " . q($table)); // if $auto_increment is set then it will be updated later
			if (
				!queries("DROP TABLE " . table($table)) // drop before creating indexes and triggers to allow using old names
				|| ($table == $name && !queries("ALTER TABLE " . table($temp_name) . " RENAME TO " . table($name)))
				|| !alter_indexes($name, $indexes)
			) {
				return false;
			}
			if ($auto_increment) {
				queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
			}
			foreach ($triggers as $trigger) {
				if (!queries($trigger)) {
					return false;
				}
			}
			queries("COMMIT");
		}
		return true;
	}

	function index_sql($table, $type, $name, $columns) {
		return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "")
			. idf_escape($name != "" ? $name : uniqid($table . "_"))
			. " ON " . table($table)
			. " $columns"
		;
	}

	function alter_indexes($table, $alter) {
		foreach ($alter as $primary) {
			if ($primary[0] == "PRIMARY") {
				return recreate_table($table, $table, array(), array(), array(), 0, $alter);
			}
		}
		foreach (array_reverse($alter) as $val) {
			if (
				!queries($val[2] == "DROP"
				? "DROP INDEX " . idf_escape($val[1])
				: index_sql($table, $val[0], $val[1], "(" . implode(", ", $val[2]) . ")"))
			) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		return apply_queries("DELETE FROM", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function move_tables($tables, $views, $target) {
		return false;
	}

	function trigger($name, $table) {
		if ($name == "") {
			return array("Statement" => "BEGIN\n\t;\nEND");
		}
		$idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
		$trigger_options = trigger_options();
		preg_match(
			"~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $trigger_options["Timing"]) . ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
			get_val("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " . q($name)),
			$match
		);
		$of = $match[3];
		return array(
			"Timing" => strtoupper($match[1]),
			"Event" => strtoupper($match[2]) . ($of ? " OF" : ""),
			"Of" => idf_unescape($of),
			"Trigger" => $name,
			"Statement" => $match[4],
		);
	}

	function triggers($table) {
		$return = array();
		$trigger_options = trigger_options();
		foreach (get_rows("SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)) as $row) {
			preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' . implode("|", $trigger_options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
			$return[$row["name"]] = array($match[1], $match[2]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "UPDATE OF", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	function begin() {
		return queries("BEGIN");
	}

	function last_id($result) {
		return get_val("SELECT LAST_INSERT_ROWID()");
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN QUERY PLAN $query");
	}

	function found_rows($table_status, $where) {
	}

	function types(): array {
		return array();
	}

	function create_sql($table, $auto_increment, $style) {
		$return = get_val("SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . q($table));
		foreach (indexes($table) as $name => $index) {
			if ($name == '') {
				continue;
			}
			$return .= ";\n\n" . index_sql($table, $index['type'], $name, "(" . implode(", ", array_map('Adminer\idf_escape', $index['columns'])) . ")");
		}
		return $return;
	}

	function truncate_sql($table) {
		return "DELETE FROM " . table($table);
	}

	function use_sql($database) {
	}

	function trigger_sql($table) {
		return implode(get_vals("SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)));
	}

	function show_variables() {
		$return = array();
		foreach (get_rows("PRAGMA pragma_list") as $row) {
			$name = $row["name"];
			if ($name != "pragma_list" && $name != "compile_options") {
				$return[$name] = array($name, '');
				foreach (get_rows("PRAGMA $name") as $row) {
					$return[$name][1] .= implode(", ", $row) . "\n";
				}
			}
		}
		return $return;
	}

	function show_status() {
		$return = array();
		foreach (get_vals("PRAGMA compile_options") as $option) {
			$return[] = explode("=", $option, 2);
		}
		return $return;
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(check|columns|database|drop_col|dump|indexes|descidx|move_col|sql|status|table|trigger|variables|view|view_trigger)$~', $feature);
	}
}
