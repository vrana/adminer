<?php
namespace Adminer;

add_driver("sqlite", "SQLite");

if (isset($_GET["sqlite"])) {
	define('Adminer\DRIVER', "sqlite");

	if (class_exists("SQLite3") && $_GET["ext"] != "pdo") {
		abstract class SqliteDb extends SqlDb {
			public $extension = "SQLite3";
			private $link;

			function attach(array $server, string $username, string $password): string {
				$this->link = new \SQLite3($server["path"]);
				$version = \SQLite3::version();
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
					: "x'" . bin2hex($string) . "'"
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
				$types = array(1 => "integer", "real", "text", "blob", "null"); // SQLITE3_INTEGER, SQLITE3_FLOAT, SQLITE3_TEXT, SQLITE3_BLOB, SQLITE3_NULL
				$column = $this->offset++;
				return (object) array(
					"name" => $this->result->columnName($column),
					"native_type" => $types[$this->result->columnType($column)],
				);
			}
		}

	} elseif (extension_loaded("pdo_sqlite")) {
		abstract class SqliteDb extends PdoDb {
			public $extension = "PDO_SQLite";

			function attach(array $server, string $username, string $password): string {
				return $this->dsn(DRIVER . ":" . $server["path"], "", "");
			}

			function quote(string $string): string {
				return (is_utf8($string)
					? parent::quote($string)
					: "x'" . bin2hex($string) . "'" // PDO::quote() mangles binary data
				);
			}
		}

	}

	if (class_exists('Adminer\SqliteDb')) {
		class Db extends SqliteDb {
			function attach(array $server, string $username, string $password): string {
				parent::attach($server, $username, $password);
				$this->query("PRAGMA foreign_keys = 1");
				$this->query("PRAGMA busy_timeout = 500");
				return '';
			}

			function select_db(string $filename): bool {
				$query = "ATTACH " . $this->quote(preg_match("~(^[/\\\\]|:)~", $filename) ? $filename : dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a";
				if (is_readable($filename) && $this->query($query)) {
					return !self::attach(server_parts(array("path" => $filename)), '', '');
				}
				return false;
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("SQLite3", "PDO_SQLite");
		static $jush = "sqlite";
		static $passwords = false;

		static $serverFile = true; // the server name is not used, the file is selected as the database

		protected $types = array(array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0));

		public $insertFunctions = array(); // "text" => "date('now')/time('now')/datetime('now')",
		public $editFunctions = array(
			"integer|real|numeric" => "+/-",
			// "text" => "date/time/datetime",
			"text" => "||",
		);

		public $fulltextOperator = "MATCH";
		public $functions = array("hex", "length", "lower", "round", "unixepoch", "upper");
		public $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");

		function operators(?array $tableStatus): array {
			$return = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL"); // REGEXP can be user defined function
			if (preg_match('~^fts\d+$~i', (string) idx($tableStatus, "Engine"))) { // table_status() puts the module of a virtual table in Engine
				$return[] = "MATCH"; // FTS accepts it on a single column and on the whole table
			}
			$return[] = "SQL";
			return $return;
		}

		static function connect(string $server, string $username, string $password) {
			return parent::connect(":memory:", "", ""); // the password is refused by Adminer::login()
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			if (min_version(3.31, 0, $connection)) {
				$this->generated = array("STORED", "VIRTUAL");
			}
			if (min_version(3.37, 0, $connection)) {
				$this->types[0]["any"] = 0;
			}
		}

		function structuredTypes(): array {
			return array_keys($this->types[0]);
		}

		function quoteBinary(string $s): string {
			return "x" . q(bin2hex($s));
		}

		function typeName(\stdClass $field): string {
			// PDO_SQLite reports the declared type of the column in sqlite:decl_type, the expressions only the PHP type of the value
			$return = strtolower(idx((array) $field, 'sqlite:decl_type', parent::typeName($field)));
			return idx(array("string" => "text", "double" => "real"), $return, $return);
		}

		function engines(): array {
			$return = array("table");
			if (min_version("3.8.2")) {
				if (min_version(3.37)) {
					$return[] = "STRICT";
					$return[] = "STRICT, WITHOUT ROWID";
				}
				$return[] = "WITHOUT ROWID";
			}
			return $return;
		}

		/** Check whether the table is a virtual table
		* @param TableStatus $table_status
		*/
		private function isVirtual(array $table_status): bool {
			$engine = $table_status["Engine"]; // table_status() puts the module of a virtual table there
			return $engine != "" && !in_array($engine, array_merge(array("view"), $this->engines()));
		}

		function supportsIndex(array $table_status): bool {
			return !is_view($table_status) && !$this->isVirtual($table_status); // CREATE INDEX is rejected on a virtual table
		}

		function supportsAlterIndex(array $table_status): bool {
			return $this->supportsIndex($table_status);
		}

		function supportsAlterTable(array $tableStatus): bool {
			return !$this->isVirtual($tableStatus); // a virtual table can be only renamed and dropped
		}

		function shadowTables(string $table): array {
			$return = array();
			if (min_version(3.37)) {
				foreach (get_vals("SELECT name FROM pragma_table_list WHERE schema = 'main' AND type = 'shadow' ORDER BY name") as $name) {
					// a shadow table is named after the virtual table using it and a single word, e.g. posts_fts_data
					if (preg_match('(^' . preg_quote($table) . '_[^_]*$)', $name)) {
						$return[] = array("table" => $name, "ns" => "");
					}
				}
			}
			return $return;
		}

		function fulltextSql(string $name, array $index, string $query, bool $boolean): string {
			return idf_escape($name) . " MATCH " . q($query); // the index is named after the FTS table
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			return queries("REPLACE INTO " . table($table) . " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
		}

		function tableHelp(string $name, bool $is_view = false) {
			if (preg_match('~^sqlite_(seq|stat.)~', $name, $match)) {
				return "fileformat2.html#$match[1]tab";
			}
			if (preg_match('~^sqlite(_temp)?_(master|schema)$~', $name)) {
				return "schematab.html";
			}
		}

		function checkConstraints(string $table): array {
			//! could be inside a comment
			preg_match_all('~ CHECK *(\( *(((?>[^()]*[^() ])|(?1))*) *\))~', get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $this->conn), $matches);
			return array_combine($matches[2], $matches[2]);
		}

		function allFields(): array {
			$return = array();
			if (min_version(3.16)) { // table-valued pragma functions, they get all columns in a single query
				$rows = get_rows('SELECT m.name AS tab, p.name AS field, p.type, p."notnull", p.pk AS ' . idf_escape("primary") . "
FROM sqlite_master m, pragma_table_" . (min_version(3.31) ? "x" : "") . "info(m.name) p
WHERE m.type IN ('table', 'view')" . (min_version(3.31) ? "
AND p.hidden != 1" : "") . (min_version(3.37) ? "
AND m.name NOT IN (SELECT name FROM pragma_table_list WHERE type = 'shadow')" : "") . "
ORDER BY (m.name LIKE 'sqlite_%'), m.name, p.cid", $this->conn);
				foreach ($rows as $row) {
					$row["type"] = type_affinity($row["type"]);
					$row["null"] = !$row["notnull"];
					$return[$row["tab"]][] = $row;
				}
			} else {
				foreach (tables_list() as $table => $type) {
					foreach (fields($table) as $field) {
						$return[$table][] = $field;
					}
				}
			}
			return $return;
		}
	}



	function idf_escape(string $idf): string {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table(string $idf): string {
		return idf_escape($idf);
	}

	function get_databases(bool $flush): array {
		return array();
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return (preg_match('~^INTO~', $query) || get_val("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')")
			? limit($query, $where, 1, 0, $separator)
			: " $query WHERE rowid = (SELECT rowid FROM " . table($table) . $where . $separator . "LIMIT 1)" //! use primary key in tables with WITHOUT rowid
		);
	}

	function db_collation(string $db, array $collations): string {
		return get_val("PRAGMA encoding"); // there is no database list so $db == DB
	}

	function logged_user(): string {
		return get_current_user(); // should return effective user
	}

	/** Get the module of a virtual table, empty string for a regular table */
	function virtual_module(string $sql): string {
		return (preg_match('~^CREATE\s+VIRTUAL\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"[^"]*+"|`[^`]*+`|\[[^\]]*+\]|[^\s(]+)\s+USING\s+([a-z0-9_]+)~i', $sql, $match) ? $match[1] : "");
	}

	function tables_list(): array {
		// the shadow tables of virtual tables are listed only by table_status()
		return get_key_vals("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view')"
			. (min_version(3.37) ? " AND name NOT IN (SELECT name FROM pragma_table_list WHERE type = 'shadow')" : "") . "
ORDER BY (name LIKE 'sqlite_%'), name");
	}

	function count_tables(array $databases): array {
		return array();
	}

	/** Get sizes of the whole database
	* @return array<string, int> [$key => $bytes] with keys of table_status()
	*/
	function db_status(): array {
		// sizes of single tables are available only through the dbstat virtual table which reads all pages
		$page_size = get_val("PRAGMA page_size");
		$free = get_val("PRAGMA freelist_count") * $page_size;
		return array(
			"Data_length" => get_val("PRAGMA page_count") * $page_size - $free,
			"Index_length" => 0, // pages of tables and indexes are counted together
			"Data_free" => $free,
		);
	}

	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		$rows = array();
		if (!$fast && $name == "") {
			connection()->query("PRAGMA optimize = 0x10002"); // update sqlite_stat1; the 0x10000 bit (all tables, not only queried ones) works since SQLite 3.46
			$rows = get_key_vals("SELECT tbl, MAX(CAST(stat AS integer)) FROM sqlite_stat1 GROUP BY tbl");
		}
		foreach (
			get_rows(
				"SELECT name AS Name, type AS Engine, sql, 'rowid' AS Oid, '' AS Auto_increment"
				. (min_version(3.37) ? ", name IN (SELECT name FROM pragma_table_list WHERE type = 'shadow') AS dependent" : "")
				. " FROM sqlite_master WHERE type IN ('table', 'view') "
				. ($name != "" ? "AND name = " . q($name) : "ORDER BY (name LIKE 'sqlite_%'), name")
			) as $row
		) {
			if ($row["Engine"] == "table") {
				$sql = preg_replace('~(?:\s|--[^\n]*|/\*.*?\*/)+$~s', '', $row["sql"]); // the definition can end with a comment
				$suffix = preg_replace('~.*\)~s', '', $sql); // table options are after the last parenthesis
				$row["Engine"] = virtual_module($row["sql"]) ?: (implode(", ", array_filter(array(
					(preg_match('~\bSTRICT\b~i', $suffix) ? "STRICT" : 0),
					(preg_match('~\bWITHOUT\s+ROWID\b~i', $suffix) ? "WITHOUT ROWID" : 0),
				))) ?: "table");
			}
			unset($row["sql"]);
			$row["Rows"] = idx($rows, $row["Name"], 0);
			$return[$row["Name"]] = $row;
		}
		if (!$fast) {
			foreach (get_rows("SELECT * FROM sqlite_sequence" . ($name != "" ? " WHERE name = " . q($name) : ""), null, "") as $row) {
				$return[$row["name"]]["Auto_increment"] = $row["seq"];
			}
		}
		return $return;
	}

	function is_view(array $table_status): bool {
		return $table_status["Engine"] == "view";
	}

	function fk_support(array $table_status): bool {
		return !get_val("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
	}

	/** Get the type affinity of a declared column type */
	function type_affinity(string $type): string {
		$type = strtolower($type);
		return (preg_match('~int~i', $type) ? "integer"
			: (preg_match('~char|clob|text~i', $type) ? "text"
			: (preg_match('~blob~i', $type) ? "blob"
			: (preg_match('~real|floa|doub~i', $type) ? "real"
			: (preg_match('~any~i', $type) ? "any"
			: "numeric"
		)))));
	}

	function fields(string $table): array {
		$return = array();
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table));
		$privileges = array("select" => 1, "where" => 1, "order" => 1);
		if (!preg_match('~^sqlite(_temp)?_(master|schema)$~', $table)) {
			$privileges += array("insert" => 1, "update" => 1);
		}
		$fts = preg_match('~^fts\d+$~i', virtual_module($sql)); // FTS declares the columns without a type but stores strings
		foreach (get_rows("PRAGMA table_" . (min_version(3.31) ? "x" : "") . "info(" . table($table) . ")") as $row) {
			if ($row["hidden"] == 1) { // the columns of a virtual table usable only in a condition; generated columns have 2 and 3
				continue;
			}
			$name = $row["name"];
			$type = strtolower($row["type"]);
			$default = $row["dflt_value"];
			$return[$name] = array(
				"field" => $name,
				"type" => ($fts ? "text" : type_affinity($type)),
				"full_type" => $type,
				"default" => (preg_match("~^'(.*)'$~", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default)),
				"null" => !$row["notnull"],
				"privileges" => $privileges,
				"primary" => $row["pk"],
			);
			if ($row["pk"] && preg_match('~\bAUTOINCREMENT\b~i', $sql)) {
				$return[$name]["auto_increment"] = true;
			}
		}
		$idf = '[(,]\s*(("[^"]*+")+|[a-z0-9_]+)'; // column name at the beginning of a column definition
		$rest = '(?:[^,()\']|\'[^\']*+\'|\([^)]*+\))*?'; // rest of the column definition, never crossing to the next column
		preg_match_all('~' . $idf . '\s+text\b' . $rest . 'COLLATE\s+(\'[^\']+\'|[a-z0-9_]+)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			if ($return[$name]) {
				$return[$name]["collation"] = trim($match[3], "'");
			}
		}
		preg_match_all('~' . $idf . '\s' . $rest . 'GENERATED\s+ALWAYS\s+AS\s*\((.+?)\)\s+(STORED|VIRTUAL)~i', $sql, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
			if ($return[$name]) {
				$return[$name]["default"] = $match[3];
				$return[$name]["generated"] = strtoupper($match[4]);
			}
		}
		return $return;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		$connection2 = connection($connection2);
		$return = array();
		$sql = get_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table), 0, $connection2);
		if (preg_match('~^fts\d+$~i', virtual_module($sql))) {
			// the index is named after the table because the column of this name searches all columns at once
			return array($table => array("type" => "FULLTEXT", "columns" => array_keys(fields($table)), "lengths" => array(), "descs" => array()));
		}
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

	function foreign_keys(string $table): array {
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

	function view(string $name): array {
		//! identifiers may be inside []
		return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '', get_val("SELECT sql FROM sqlite_master WHERE type = 'view' AND name = " . q($name))));
	}

	function collations(): array {
		return (isset($_GET["create"]) ? get_vals("PRAGMA collation_list", 1) : array());
	}

	function information_schema(string $db, string $schema = ""): bool {
		return false;
	}

	function error(): string {
		return h(connection()->error);
	}

	function check_sqlite_name(string $name): bool {
		// avoid creating PHP files on unsecured servers
		$extensions = "db|sdb|sqlite";
		if (!preg_match("~^[^\\0]*\\.($extensions)\$~", $name)) {
			connection()->error = lang('Please use one of these file extensions: %s.', str_replace("|", ", ", $extensions));
			return false;
		}
		return true;
	}

	function create_database(string $db, string $collation) {
		if (file_exists($db)) {
			connection()->error = lang('File exists.');
			return false;
		}
		if (!check_sqlite_name($db)) {
			return false;
		}
		try {
			$link = new Db();
			$link->attach(server_parts(array("path" => $db)), '', '');
		} catch (\Exception $ex) {
			connection()->error = $ex->getMessage();
			return false;
		}
		$link->query('PRAGMA encoding = "UTF-8"');
		$link->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
		$link->query('DROP TABLE adminer');
		return true;
	}

	function drop_databases(array $databases): bool {
		connection()->attach(server_parts(array("path" => ":memory:")), '', ''); // to unlock file, doesn't work in PDO on Windows
		foreach ($databases as $db) {
			if (!check_sqlite_name($db)) {
				return false;
			}
			if (!@unlink($db)) {
				connection()->error = lang('File exists.');
				return false;
			}
		}
		return true;
	}

	function rename_database(string $name, string $collation): bool {
		if (!check_sqlite_name($name)) {
			return false;
		}
		connection()->attach(server_parts(array("path" => ":memory:")), '', '');
		connection()->error = lang('File exists.');
		return @rename(DB, $name);
	}

	function auto_increment(): string {
		return " PRIMARY KEY AUTOINCREMENT";
	}

	function alter_table(string $table, string $name, array $fields, array $foreign, ?string $comment, string $engine, string $collation, string $auto_increment, ?array $partitioning) {
		$use_all_fields = ($table == "" || $foreign || $engine);
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
		} elseif (!recreate_table($table, $name, $alter, $originals, $foreign, $auto_increment, array(), "", "", $engine)) {
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
	* @param numeric-string|'' $auto_increment set auto_increment to this value, "" to preserve
	* @param list<array{string, string, list<string>|'DROP'}> $indexes [[$type, $name, $columns]], empty to preserve
	* @param string $drop_check CHECK constraint to drop
	* @param string $add_check CHECK constraint to add
	*/
	function recreate_table(
		string $table,
		string $name,
		array $fields,
		array $originals,
		array $foreign,
		string $auto_increment = "",
		$indexes = array(),
		string $drop_check = "",
		string $add_check = "",
		string $engine = ""
	): bool {
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
		$temp_name = ($table != "" && $table == $name ? "adminer_$name" : $name);
		if (!$engine && $table != "") {
			$engine = idx(table_status1($table), "Engine");
		}
		if (!queries("CREATE TABLE " . table($temp_name) . " (\n" . implode(",\n", $changes) . "\n)" . ($engine != "table" && in_array($engine, driver()->engines()) ? " $engine" : ""))) {
			// implicit ROLLBACK to not overwrite connection()->error
			return false;
		}
		if ($table != "") {
			if (
				$originals && !queries("INSERT INTO " . table($temp_name) . " (" . implode(", ", $originals) . ") SELECT "
					. implode(", ", array_map('Adminer\idf_escape', array_keys($originals))) . " FROM " . table($table))
			) {
				return false;
			}
			$triggers = array();
			foreach (triggers($table) as $trigger_name => $timing_event) {
				$trigger = trigger($trigger_name, $table);
				$triggers[] = "CREATE TRIGGER " . idf_escape($trigger_name) . " " . implode(" ", $timing_event) . " ON " . table($name) . "\n$trigger[Statement]";
			}
			$auto_increment = $auto_increment ? "" : get_val("SELECT seq FROM sqlite_sequence WHERE name = " . q($table)); // if $auto_increment is set then it will be updated later
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

	function index_sql(string $table, string $type, string $name, string $columns): string {
		return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "")
			. idf_escape($name != "" ? $name : uniqid($table . "_"))
			. " ON " . table($table)
			. " $columns"
		;
	}

	function alter_indexes(string $table, $alter) {
		foreach ($alter as $primary) {
			if ($primary[0] == "PRIMARY") {
				return recreate_table($table, $table, array(), array(), array(), "", $alter);
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

	function truncate_tables(array $tables): bool {
		return apply_queries("DELETE FROM", $tables);
	}

	function drop_views(array $views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables(array $tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function move_tables(array $tables, array $views, string $target): bool {
		return false;
	}

	function trigger(string $name, string $table): array {
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
		if (!$match) {
			return array();
		}
		$of = $match[3];
		return array(
			"Timing" => strtoupper($match[1]),
			"Event" => strtoupper($match[2]) . ($of ? " OF" : ""),
			"Of" => idf_unescape($of),
			"Trigger" => $name,
			"Statement" => $match[4],
		);
	}

	function triggers(string $table): array {
		$return = array();
		$trigger_options = trigger_options();
		foreach (get_rows("SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)) as $row) {
			preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' . implode("|", $trigger_options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
			$return[$row["name"]] = array($match[1], $match[2]);
		}
		return $return;
	}

	function trigger_options(): array {
		return array(
			"Timing" => array("BEFORE", "AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "UPDATE OF", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	function last_id($result): string {
		return get_val("SELECT LAST_INSERT_ROWID()");
	}

	function explain(Db $connection, string $query) {
		return $connection->query("EXPLAIN QUERY PLAN $query");
	}

	function found_rows(array $table_status, array $where) {
	}

	function types(bool $extensions = false): array {
		return array();
	}

	function create_sql(string $table, ?bool $auto_increment, string $style): string {
		$return = get_val("SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . q($table));
		foreach (indexes($table) as $name => $index) {
			if ($name == '' || $index['type'] == 'FULLTEXT') { // FULLTEXT is the pseudo-index of an FTS table, it is a part of CREATE VIRTUAL TABLE
				continue;
			}
			$return .= ";\n\n" . index_sql($table, $index['type'], $name, "(" . implode(", ", array_map('Adminer\idf_escape', $index['columns'])) . ")");
		}
		return $return;
	}

	function truncate_sql(string $table): string {
		return "DELETE FROM " . table($table);
	}

	function use_sql(string $database, string $style = ""): string {
		return "";
	}

	function trigger_sql(string $table): string {
		return implode(get_vals("SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)));
	}

	function show_variables(): array {
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

	function show_status(): array {
		$return = array();
		foreach (get_vals("PRAGMA compile_options") as $option) {
			$return[] = explode("=", $option, 2) + array('', '');
		}
		return $return;
	}

	function convert_field(array $field) {
	}

	function unconvert_field(array $field, string $return): string {
		return $return;
	}

	function support(string $feature): bool {
		return preg_match('~^(check|columns|database|drop_col|dump|fast_status|indexes|descidx|move_col|sql|status|table|transaction_ddl|trigger|variables|view|view_trigger)$~', $feature);
	}
}
