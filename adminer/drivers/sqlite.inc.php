<?php
$drivers["sqlite"] = "SQLite 3";
$drivers["sqlite2"] = "SQLite 2";

if (isset($_GET["sqlite"]) || isset($_GET["sqlite2"])) {
	$possible_drivers = array((isset($_GET["sqlite"]) ? "SQLite3" : "SQLite"), "PDO_SQLite");
	define("DRIVER", (isset($_GET["sqlite"]) ? "sqlite" : "sqlite2"));
	if (extension_loaded(isset($_GET["sqlite"]) ? "sqlite3" : "sqlite")) {
		if (isset($_GET["sqlite"])) {
			
			class Min_SQLite {
				var $extension = "SQLite3", $server_info, $affected_rows, $error, $_link;
				
				function Min_SQLite($filename) {
					$this->_link = new SQLite3($filename);
					$version = $this->_link->version();
					$this->server_info = $version["versionString"];
				}
				
				function query($query) {
					$result = @$this->_link->query($query);
					$this->error = "";
					if (!$result) {
						$this->error = $this->_link->lastErrorMsg();
						return false;
					} elseif ($result->numColumns()) {
						return new Min_Result($result);
					}
					$this->affected_rows = $this->_link->changes();
					return true;
				}
				
				function quote($string) {
					return (is_utf8($string)
						? "'" . $this->_link->escapeString($string) . "'"
						: "x'" . reset(unpack('H*', $string)) . "'"
					);
				}
				
				function store_result() {
					return $this->_result;
				}
				
				function result($query, $field = 0) {
					$result = $this->query($query);
					if (!is_object($result)) {
						return false;
					}
					$row = $result->_result->fetchArray();
					return $row[$field];
				}
			}
			
			class Min_Result {
				var $_result, $_offset = 0, $num_rows;
				
				function Min_Result($result) {
					$this->_result = $result;
				}
				
				function fetch_assoc() {
					return $this->_result->fetchArray(SQLITE3_ASSOC);
				}
				
				function fetch_row() {
					return $this->_result->fetchArray(SQLITE3_NUM);
				}
				
				function fetch_field() {
					$column = $this->_offset++;
					$type = $this->_result->columnType($column);
					return (object) array(
						"name" => $this->_result->columnName($column),
						"type" => $type,
						"charsetnr" => ($type == SQLITE3_BLOB ? 63 : 0), // 63 - binary
					);
				}
				
				function __desctruct() {
					return $this->_result->finalize();
				}
			}
			
		} else {
			
			class Min_SQLite {
				var $extension = "SQLite", $server_info, $affected_rows, $error, $_link;
				
				function Min_SQLite($filename) {
					$this->server_info = sqlite_libversion();
					$this->_link = new SQLiteDatabase($filename);
				}
				
				function query($query, $unbuffered = false) {
					$method = ($unbuffered ? "unbufferedQuery" : "query");
					$result = @$this->_link->$method($query, SQLITE_BOTH, $error);
					$this->error = "";
					if (!$result) {
						$this->error = $error;
						return false;
					} elseif ($result === true) {
						$this->affected_rows = $this->changes();
						return true;
					}
					return new Min_Result($result);
				}
				
				function quote($string) {
					return "'" . sqlite_escape_string($string) . "'";
				}
				
				function store_result() {
					return $this->_result;
				}
				
				function result($query, $field = 0) {
					$result = $this->query($query);
					if (!is_object($result)) {
						return false;
					}
					$row = $result->_result->fetch();
					return $row[$field];
				}
			}
			
			class Min_Result {
				var $_result, $_offset = 0, $num_rows;
				
				function Min_Result($result) {
					$this->_result = $result;
					if (method_exists($result, 'numRows')) { // not available in unbuffered query
						$this->num_rows = $result->numRows();
					}
				}
				
				function fetch_assoc() {
					$row = $this->_result->fetch(SQLITE_ASSOC);
					if (!$row) {
						return false;
					}
					$return = array();
					foreach ($row as $key => $val) {
						$return[($key[0] == '"' ? idf_unescape($key) : $key)] = $val;
					}
					return $return;
				}
				
				function fetch_row() {
					return $this->_result->fetch(SQLITE_NUM);
				}
				
				function fetch_field() {
					$name = $this->_result->fieldName($this->_offset++);
					$pattern = '(\\[.*]|"(?:[^"]|"")*"|(.+))';
					if (preg_match("~^($pattern\\.)?$pattern\$~", $name, $match)) {
						$table = ($match[3] != "" ? $match[3] : idf_unescape($match[2]));
						$name = ($match[5] != "" ? $match[5] : idf_unescape($match[4]));
					}
					return (object) array(
						"name" => $name,
						"orgname" => $name,
						"orgtable" => $table,
					);
				}
				
			}
			
		}
		
	} elseif (extension_loaded("pdo_sqlite")) {
		class Min_SQLite extends Min_PDO {
			var $extension = "PDO_SQLite";
			
			function Min_SQLite($filename) {
				$this->dsn(DRIVER . ":$filename", "", "");
			}
		}
		
	}

	if (class_exists("Min_SQLite")) {
		class Min_DB extends Min_SQLite {
			
			function Min_DB() {
				$this->Min_SQLite(":memory:");
			}
			
			function select_db($filename) {
				if (is_readable($filename) && $this->query("ATTACH " . $this->quote(ereg("(^[/\\\\]|:)", $filename) ? $filename : dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a")) { // is_readable - SQLite 3
					$this->Min_SQLite($filename);
					return true;
				}
				return false;
			}
			
			function multi_query($query) {
				return $this->_result = $this->query($query);
			}
			
			function next_result() {
				return false;
			}
		}
	}
	
	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function connect() {
		return new Min_DB;
	}

	function get_databases() {
		return array();
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($query, $where) {
		global $connection;
		return ($connection->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ? limit($query, $where, 1) : " $query$where");
	}

	function db_collation($db, $collations) {
		global $connection;
		return $connection->result("PRAGMA encoding"); // there is no database list so $db == DB
	}

	function engines() {
		return array();
	}

	function logged_user() {
		return get_current_user(); // should return effective user
	}

	function tables_list() {
		return get_key_vals("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name", 1);
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "") {
		global $connection;
		$return = array();
		foreach (get_rows("SELECT name AS Name, type AS Engine FROM sqlite_master WHERE type IN ('table', 'view')" . ($name != "" ? " AND name = " . q($name) : "")) as $row) {
			$row["Oid"] = "t";
			$row["Auto_increment"] = "";
			$row["Rows"] = $connection->result("SELECT COUNT(*) FROM " . idf_escape($row["Name"]));
			$return[$row["Name"]] = $row;
		}
		foreach (get_rows("SELECT * FROM sqlite_sequence", null, "") as $row) {
			$return[$row["name"]]["Auto_increment"] = $row["seq"];
		}
		return ($name != "" ? $return[$name] : $return);
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}
	
	function fk_support($table_status) {
		global $connection;
		return !$connection->result("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
	}

	function fields($table) {
		$return = array();
		foreach (get_rows("PRAGMA table_info(" . table($table) . ")") as $row) {
			$type = strtolower($row["type"]);
			$default = $row["dflt_value"];
			$return[$row["name"]] = array(
				"field" => $row["name"],
				"type" => (eregi("int", $type) ? "integer" : (eregi("char|clob|text", $type) ? "text" : (eregi("blob", $type) ? "blob" : (eregi("real|floa|doub", $type) ? "real" : "numeric")))),
				"full_type" => $type,
				"default" => (ereg("'(.*)'", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default)),
				"null" => !$row["notnull"],
				"auto_increment" => eregi('^integer$', $type) && $row["pk"], //! possible false positive
				"privileges" => array("select" => 1, "insert" => 1, "update" => 1),
				"primary" => $row["pk"],
			);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		$primary = array();
		foreach (fields($table) as $field) {
			if ($field["primary"]) {
				$primary[] = $field["field"];
			}
		}
		if ($primary) {
			$return[""] = array("type" => "PRIMARY", "columns" => $primary, "lengths" => array());
		}
		foreach (get_rows("PRAGMA index_list(" . table($table) . ")") as $row) {
			if (!ereg("^sqlite_", $row["name"])) {
				$return[$row["name"]]["type"] = ($row["unique"] ? "UNIQUE" : "INDEX");
				$return[$row["name"]]["lengths"] = array();
				foreach (get_rows("PRAGMA index_info(" . idf_escape($row["name"]) . ")") as $row1) {
					$return[$row["name"]]["columns"][] = $row1["name"];
				}
			}
		}
		return $return;
	}

	function foreign_keys($table) {
		$return = array();
		foreach (get_rows("PRAGMA foreign_key_list(" . table($table) . ")") as $row) {
			$foreign_key = &$return[$row["id"]];
			//! idf_unescape in SQLite2
			if (!$foreign_key) {
				$foreign_key = $row;
			}
			$foreign_key["source"][] = $row["from"];
			$foreign_key["target"][] = $row["to"];
		}
		return $return;
	}

	function view($name) {
		global $connection;
		return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\\s+~iU', '', $connection->result("SELECT sql FROM sqlite_master WHERE name = " . q($name)))); //! identifiers may be inside []
	}

	function collations() {
		return (isset($_GET["create"]) ? get_vals("PRAGMA collation_list", 1) : array());
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		global $connection;
		return h($connection->error);
	}
	
	function exact_value($val) {
		return q($val);
	}
	
	function check_sqlite_name($name) {
		// avoid creating PHP files on unsecured servers
		global $connection;
		$extensions = "db|sdb|sqlite";
		if (!preg_match("~^[^\\0]*\\.($extensions)\$~", $name)) {
			$connection->error = lang('Please use one of the extensions %s.', str_replace("|", ", ", $extensions));
			return false;
		}
		return true;
	}
	
	function create_database($db, $collation) {
		global $connection;
		if (file_exists($db)) {
			$connection->error = lang('File exists.');
			return false;
		}
		if (!check_sqlite_name($db)) {
			return false;
		}
		$link = new Min_SQLite($db); //! exception handler
		$link->query('PRAGMA encoding = "UTF-8"');
		$link->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
		$link->query('DROP TABLE adminer');
		return true;
	}
	
	function drop_databases($databases) {
		global $connection;
		$connection->Min_SQLite(":memory:"); // to unlock file, doesn't work in PDO on Windows
		foreach ($databases as $db) {
			if (!@unlink($db)) {
				$connection->error = lang('File exists.');
				return false;
			}
		}
		return true;
	}
	
	function rename_database($name, $collation) {
		global $connection;
		if (!check_sqlite_name($name)) {
			return false;
		}
		$connection->Min_SQLite(":memory:");
		$connection->error = lang('File exists.');
		return @rename(DB, $name);
	}
	
	function auto_increment() {
		return " PRIMARY KEY" . (DRIVER == "sqlite" ? " AUTOINCREMENT" : "");
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
		$primary_key = false;
		foreach ($fields as $field) {
			if ($field[1]) {
				if ($field[1][6]) {
					$primary_key = true;
				}
				$alter[] = ($use_all_fields ? "  " : "ADD ") . implode($field[1]);
				if ($field[0] != "") {
					$originals[$field[0]] = $field[1][0];
				}
			}
		}
		if ($use_all_fields) {
			if ($table != "") {
				queries("BEGIN");
				foreach (foreign_keys($table) as $foreign_key) {
					$columns = array();
					foreach ($foreign_key["source"] as $column) {
						if (!$originals[$column]) {
							continue 2;
						}
						$columns[] = $originals[$column];
					}
					$foreign[] = "  FOREIGN KEY (" . implode(", ", $columns) . ") REFERENCES "
						. table($foreign_key["table"])
						. " (" . implode(", ", array_map('idf_escape', $foreign_key["target"]))
						. ") ON DELETE $foreign_key[on_delete] ON UPDATE $foreign_key[on_update]"
					;
				}
				$indexes = array();
				foreach (indexes($table) as $key_name => $index) {
					$columns = array();
					foreach ($index["columns"] as $column) {
						if (!$originals[$column]) {
							continue 2;
						}
						$columns[] = $originals[$column];
					}
					$columns = "(" . implode(", ", $columns) . ")";
					if ($index["type"] != "PRIMARY") {
						$indexes[] = array($index["type"], $key_name, $columns);
					} elseif (!$primary_key) {
						$foreign[] = "  PRIMARY KEY $columns";
					}
				}
			}
			$alter = array_merge($alter, $foreign);
			if (!queries("CREATE TABLE " . table($table != "" ? "adminer_$name" : $name) . " (\n" . implode(",\n", $alter) . "\n)")) {
				// implicit ROLLBACK to not overwrite $connection->error
				return false;
			}
			if ($table != "") {
				if ($originals && !queries("INSERT INTO " . table("adminer_$name") . " (" . implode(", ", $originals) . ") SELECT " . implode(", ", array_map('idf_escape', array_keys($originals))) . " FROM " . table($table))) {
					return false;
				}
				$triggers = array();
				foreach (triggers($table) as $trigger_name => $timing_event) {
					$trigger = trigger($trigger_name);
					$triggers[] = "CREATE TRIGGER " . idf_escape($trigger_name) . " " . implode(" ", $timing_event) . " ON " . table($name) . "\n$trigger[Statement]";
				}
				if (!queries("DROP TABLE " . table($table))) { // drop before creating indexes and triggers to allow using old names
					return false;
				}
				queries("ALTER TABLE " . table("adminer_$name") . " RENAME TO " . table($name));
				if (!alter_indexes($name, $indexes)) {
					return false;
				}
				foreach ($triggers as $trigger) {
					if (!queries($trigger)) {
						return false;
					}
				}
				queries("COMMIT");
			}
		} else {
			foreach ($alter as $val) {
				if (!queries("ALTER TABLE " . table($table) . " $val")) {
					return false;
				}
			}
			if ($table != $name && !queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name))) {
				return false;
			}
		}
		if ($auto_increment) {
			queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . q($name)); // ignores error
		}
		return true;
	}
	
	function alter_indexes($table, $alter) {
		foreach ($alter as $val) {
			if (!queries($val[2] == "DROP"
				? "DROP INDEX " . idf_escape($val[1])
				: "CREATE $val[0] " . ($val[0] != "INDEX" ? "INDEX " : "") . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table) . " $val[2]"
			)) {
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
	
	function trigger($name) {
		global $connection;
		if ($name == "") {
			return array("Statement" => "BEGIN\n\t;\nEND");
		}
		preg_match('~^CREATE\\s+TRIGGER\\s*(?:[^`"\\s]+|`[^`]*`|"[^"]*")+\\s*([a-z]+)\\s+([a-z]+)\\s+ON\\s*(?:[^`"\\s]+|`[^`]*`|"[^"]*")+\\s*(?:FOR\\s*EACH\\s*ROW\\s)?(.*)~is', $connection->result("SELECT sql FROM sqlite_master WHERE name = " . q($name)), $match);
		return array("Timing" => strtoupper($match[1]), "Event" => strtoupper($match[2]), "Trigger" => $name, "Statement" => $match[3]);
	}
	
	function triggers($table) {
		$return = array();
		foreach (get_rows("SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)) as $row) {
			preg_match('~^CREATE\\s+TRIGGER\\s*(?:[^`"\\s]+|`[^`]*`|"[^"]*")+\\s*([a-z]+)\\s*([a-z]+)~i', $row["sql"], $match);
			$return[$row["name"]] = array($match[1], $match[2]);
		}
		return $return;
	}
	
	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER", "INSTEAD OF"),
			"Type" => array("FOR EACH ROW"),
		);
	}
	
	function routine($name, $type) {
		// not supported by SQLite
	}
	
	function routines() {
		// not supported by SQLite
	}
	
	function routine_languages() {
		// not supported by SQLite
	}
	
	function begin() {
		return queries("BEGIN");
	}
	
	function insert_into($table, $set) {
		return queries("INSERT INTO " . table($table) . ($set ? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")" : "DEFAULT VALUES"));
	}
	
	function insert_update($table, $set, $primary) {
		return queries("REPLACE INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES (" . implode(", ", $set) . ")");
	}
	
	function last_id() {
		global $connection;
		return $connection->result("SELECT LAST_INSERT_ROWID()");
	}
	
	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}
	
	function found_rows($table_status, $where) {
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
	
	function set_schema($scheme) {
		return true;
	}
	
	function create_sql($table, $auto_increment) {
		global $connection;
		return $connection->result("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . q($table));
	}
	
	function truncate_sql($table) {
		return "DELETE FROM " . table($table);
	}
	
	function use_sql($database) {
	}
	
	function trigger_sql($table, $style) {
		return implode(get_vals("SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . q($table)));
	}
	
	function show_variables() {
		global $connection;
		$return = array();
		foreach (array("auto_vacuum", "cache_size", "count_changes", "default_cache_size", "empty_result_callbacks", "encoding", "foreign_keys", "full_column_names", "fullfsync", "journal_mode", "journal_size_limit", "legacy_file_format", "locking_mode", "page_size", "max_page_count", "read_uncommitted", "recursive_triggers", "reverse_unordered_selects", "secure_delete", "short_column_names", "synchronous", "temp_store", "temp_store_directory", "schema_version", "integrity_check", "quick_check") as $key) {
			$return[$key] = $connection->result("PRAGMA $key");
		}
		return $return;
	}
	
	function show_status() {
		$return = array();
		foreach (get_vals("PRAGMA compile_options") as $option) {
			list($key, $val) = explode("=", $option, 2);
			$return[$key] = $val;
		}
		return $return;
	}
	
	function convert_field($field) {
	}
	
	function unconvert_field($field, $return) {
		return $return;
	}
	
	function support($feature) {
		return ereg('^(view|trigger|variables|status|dump|move_col|drop_col)$', $feature);
	}
	
	$jush = "sqlite";
	$types = array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0);
	$structured_types = array_keys($types);
	$unsigned = array();
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", ""); // REGEXP can be user defined function
	$functions = array("hex", "length", "lower", "round", "unixepoch", "upper");
	$grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");
	$edit_functions = array(
		array(
			// "text" => "date('now')/time('now')/datetime('now')",
		), array(
			"integer|real|numeric" => "+/-",
			// "text" => "date/time/datetime",
			"text" => "||",
		)
	);
}
