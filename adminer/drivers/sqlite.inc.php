<?php
$possible_drivers[] = "SQLite";
$possible_drivers[] = "SQLite3";
$possible_drivers[] = "PDO_SQLite";
if (extension_loaded("sqlite3") || extension_loaded("pdo_sqlite")) {
	$drivers["sqlite"] = "SQLite 3";
}
if (extension_loaded("sqlite") || extension_loaded("pdo_sqlite")) {
	$drivers["sqlite2"] = "SQLite 2";
}

if (isset($_GET["sqlite"]) || isset($_GET["sqlite2"])) {
	define("DRIVER", (isset($_GET["sqlite"]) ? "sqlite" : "sqlite2"));
	if (extension_loaded(isset($_GET["sqlite2"]) ? "sqlite" : "sqlite3")) {
		if (isset($_GET["sqlite2"])) {
			
			class Min_SQLite {
				var $extension = "SQLite", $server_info, $affected_rows, $error, $_link;
				
				function Min_SQLite($filename) {
					$this->server_info = sqlite_libversion();
					$this->_link = new SQLiteDatabase($filename);
				}
				
				function query($query, $unbuffered = false) {
					$method = ($unbuffered ? "unbufferedQuery" : "query");
					$result = @$this->_link->$method($query, SQLITE_BOTH, $error);
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
			
		} else {
			
			class Min_SQLite {
				var $extension = "SQLite3", $server_info, $affected_rows, $error, $_link;
				
				function Min_SQLite($filename) {
					$this->_link = new SQLite3($filename);
					$version = $this->_link->version();
					$this->server_info = $version["versionString"];
				}
				
				function query($query) {
					$result = @$this->_link->query($query);
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
					return "'" . $this->_link->escapeString($string) . "'";
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
					$this->num_rows = 1; //!
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
			
		}
		
	} elseif (extension_loaded("pdo_sqlite")) {
		class Min_SQLite extends Min_PDO {
			var $extension = "PDO_SQLite";
			
			function Min_SQLite($filename) {
				$this->dsn(DRIVER . ":$filename", "", "");
			}
		}
		
	}

	class Min_DB extends Min_SQLite {
		
		function Min_DB() {
			$this->Min_SQLite(":memory:");
		}
		
		function select_db($filename) {
			if (is_readable($filename) && $this->query("ATTACH " . $this->quote(ereg("(^[/\\]|:)", $filename) ? $filename : dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a")) { // is_readable - SQLite 3
				$this->Min_SQLite($filename);
				return true;
			}
			return false;
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
	}
	
	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function connect() {
		return new Min_DB;
	}

	function get_databases() {
		return array();
	}

	function limit($query, $limit, $offset = 0) {
		return " $query" . (isset($limit) ? "\nLIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($query) {
		global $connection;
		return ($connection->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ? limit($query, 1) : " $query");
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
		return get_key_vals("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view')", 1);
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "") {
		global $connection;
		$return = array();
		$result = $connection->query("SELECT name AS Name, type AS Engine FROM sqlite_master WHERE type IN ('table', 'view')" . ($name != "" ? " AND name = " . $connection->quote($name) : ""));
		while ($row = $result->fetch_assoc()) {
			$row["Auto_increment"] = "";
			$return[$row["Name"]] = $row;
		}
		$result = $connection->query("SELECT * FROM sqlite_sequence");
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$return[$row["name"]]["Auto_increment"] = $row["seq"];
			}
		}
		return ($name != "" ? $return[$name] : $return);
	}

	function fk_support($table_status) {
		global $connection;
		return !$connection->result("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
	}

	function fields($table) {
		global $connection;
		$return = array();
		$result = $connection->query("PRAGMA table_info(" . idf_escape($table) . ")");
		if (is_object($result)) {
			while ($row = $result->fetch_assoc()) {
				$type = strtolower($row["type"]);
				$return[$row["name"]] = array(
					"field" => $row["name"],
					"type" => (eregi("int", $type) ? "integer" : (eregi("char|clob|text", $type) ? "text" : (eregi("blob", $type) ? "blob" : (eregi("real|floa|doub", $type) ? "real" : "numeric")))),
					"full_type" => $type,
					"default" => $row["dflt_value"],
					"null" => !$row["notnull"],
					"auto_increment" => eregi('^integer$', $type) && $row["pk"], //! possible false positive
					"collation" => null, //!
					"privileges" => array("select" => 1, "insert" => 1, "update" => 1),
					"primary" => $row["pk"],
				);
			}
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		global $connection;
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
		$result = $connection->query("PRAGMA index_list(" . idf_escape($table) . ")");
		if (is_object($result)) {
			while ($row = $result->fetch_assoc()) {
				$return[$row["name"]]["type"] = ($row["unique"] ? "UNIQUE" : "INDEX");
				$return[$row["name"]]["lengths"] = array();
				$result1 = $connection->query("PRAGMA index_info(" . idf_escape($row["name"]) . ")");
				while ($row1 = $result1->fetch_assoc()) {
					$return[$row["name"]]["columns"][] = $row1["name"];
				}
			}
		}
		return $return;
	}

	function foreign_keys($table) {
		global $connection;
		$return = array();
		$result = $connection->query("PRAGMA foreign_key_list(" . idf_escape($table) . ")");
		if (is_object($result)) {
			while ($row = $result->fetch_assoc()) {
				$foreign_key = &$return[$row["id"]];
				//! idf_unescape in SQLite2
				if (!$foreign_key) {
					$foreign_key = $row;
				}
				$foreign_key["source"][] = $row["from"];
				$foreign_key["target"][] = $row["to"];
			}
		}
		return $return;
	}

	function view($name) {
		global $connection;
		return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\\s+~iU', '', $connection->result("SELECT sql FROM sqlite_master WHERE name = " . $connection->quote($name)))); //! identifiers may be inside []
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
		global $connection;
		return $connection->quote($val);
	}

	function create_database($db, $collation) {
		global $connection;
		if (file_exists($db)) {
			$connection->error = lang('File exists.');
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
		$connection->Min_SQLite(":memory:");
		$connection->error = lang('File exists.');
		return @rename(DB, $name);
	}
	
	function auto_increment() {
		return " PRIMARY KEY" . (DRIVER == "sqlite" ? " AUTOINCREMENT" : "");
	}
	
	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		global $connection;
		$alter = array();
		foreach ($fields as $field) {
			$alter[] = ($table != "" && $field[0] == "" ? "ADD " : "  ") . implode("", $field[1]);
		}
		$alter = array_merge($alter, $foreign);
		if ($table != "") {
			foreach ($alter as $val) {
				if (!queries("ALTER TABLE " . idf_escape($table) . " $val")) {
					return false;
				}
			}
			if ($table != $name && !queries("ALTER TABLE " . idf_escape($table) . " RENAME TO " . idf_escape($name))) {
				return false;
			}
		} elseif (!queries("CREATE TABLE " . idf_escape($name) . " (\n" . implode(",\n", $alter) . "\n)")) {
			return false;
		}
		if ($auto_increment) {
			queries("UPDATE sqlite_sequence SET seq = $auto_increment WHERE name = " . $connection->quote($name)); // ignores error
		}
		return true;
	}
	
	function alter_indexes($table, $alter) {
		foreach ($alter as $val) {
			if (!queries(($val[2] ? "DROP INDEX" : "CREATE" . ($val[0] != "INDEX" ? " UNIQUE" : "") . " INDEX " . idf_escape(uniqid($table . "_")) . " ON " . idf_escape($table)) . " $val[1]")) { //! primary key must be created in CREATE TABLE
				return false;
			}
		}
		return true;
	}
	
	function truncate_tables($tables) {
		foreach ($tables as $table) {
			if (!queries("DELETE FROM " . idf_escape($table))) {
				return false;
			}
		}
		return true;
	}
	
	function drop_views($views) {
		foreach ($views as $view) {
			if (!queries("DROP VIEW " . idf_escape($view))) {
				return false;
			}
		}
		return true;
	}
	
	function drop_tables($tables) {
		foreach ($tables as $table) {
			if (!queries("DROP TABLE " . idf_escape($table))) {
				return false;
			}
		}
		return true;
	}
	
	function trigger($name) {
		global $connection;
		preg_match('~^CREATE\\s+TRIGGER\\s*(?:[^`"\\s]+|`[^`]*`|"[^"]*")+\\s*([a-z]+)\\s+([a-z]+)\\s+ON\\s*(?:[^`"\\s]+|`[^`]*`|"[^"]*")+\\s*(?:FOR\\s*EACH\\s*ROW\\s)?(.*)~is', $connection->result("SELECT sql FROM sqlite_master WHERE name = " . $connection->quote($name)), $match);
		return array("Timing" => strtoupper($match[1]), "Event" => strtoupper($match[2]), "Trigger" => $name, "Statement" => $match[3]);
	}
	
	function triggers($table) {
		global $connection;
		$return = array();
		$result = $connection->query("SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $connection->quote($table));
		while ($row = $result->fetch_assoc()) {
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
	
	function begin() {
		return queries("BEGIN");
	}
	
	function insert_into($table, $set) {
		return queries("INSERT INTO " . idf_escape($table) . ($set ? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")" : "DEFAULT VALUES"));
	}
	
	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}
	
	function create_sql($table) {
		global $connection;
		return $connection->result("SELECT sql FROM sqlite_master WHERE name = " . $connection->quote($table));
	}
	
	function use_sql($database) {
		global $connection;
		return "ATTACH " . $connection->quote($database) . " AS " . idf_escape($database);
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
	
	function support($feature) {
		return ereg('^(view|trigger|variables|status)$', $feature);
	}
	
	$driver = "sqlite";
	$types = array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0);
	$structured_types = array_keys($types);
	$unsigned = array();
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL"); // REGEXP can be user defined function
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
