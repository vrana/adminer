<?php
$possible_drivers[] = "MySQLi";
$possible_drivers[] = "MySQL";
$possible_drivers[] = "PDO_MySQL";
if (extension_loaded("mysqli") || extension_loaded("mysql") || extension_loaded("pdo_mysql")) {
	$drivers = array("server" => "MySQL") + $drivers;
}

if (!defined("DRIVER")) {
	define("DRIVER", "server"); // server - backwards compatibility
	// MySQLi supports everything, MySQL doesn't support multiple result sets, PDO_MySQL doesn't support orgtable
	if (extension_loaded("mysqli")) {
		class Min_DB extends MySQLi {
			var $extension = "MySQLi";
			
			function Min_DB() {
				parent::init();
			}
			
			function connect($server, $username, $password) {
				list($host, $port) = explode(":", $server, 2); // part after : is used for port or socket
				return @$this->real_connect(
					($server != "" ? $host : ini_get("mysqli.default_host")),
					("$server$username" != "" ? $username : ini_get("mysqli.default_user")),
					("$server$username$password" != "" ? $password : ini_get("mysqli.default_pw")),
					null,
					(is_numeric($port) ? $port : ini_get("mysqli.default_port")),
					(!is_numeric($port) ? $port : null)
				);
			}
			
			function result($query, $field = 0) {
				$result = $this->query($query);
				if (!$result) {
					return false;
				}
				$row = $result->fetch_array();
				return $row[$field];
			}
			
			function quote($string) {
				return "'" . $this->escape_string($string) . "'";
			}
		}
		
	} elseif (extension_loaded("mysql")) {
		class Min_DB {
			var
				$extension = "MySQL", ///< @var string extension name
				$server_info, ///< @var string server version
				$affected_rows, ///< @var int number of affected rows
				$error, ///< @var string last error message
				$_link, $_result ///< @access private
			;
			
			/** Connect to server
			* @param string
			* @param string
			* @param string
			* @return bool
			*/
			function connect($server, $username, $password) {
				$this->_link = @mysql_connect(
					($server != "" ? $server : ini_get("mysql.default_host")),
					("$server$username" != "" ? $username : ini_get("mysql.default_user")),
					("$server$username$password" != "" ? $password : ini_get("mysql.default_password")),
					true,
					131072 // CLIENT_MULTI_RESULTS for CALL
				);
				if ($this->_link) {
					$this->server_info = mysql_get_server_info($this->_link);
				} else {
					$this->error = mysql_error();
				}
				return (bool) $this->_link;
			}
			
			/** Quote string to use in SQL
			* @param string
			* @return string escaped string enclosed in '
			*/
			function quote($string) {
				return "'" . mysql_real_escape_string($string, $this->_link) . "'";
			}
			
			/** Select database
			* @param string
			* @return bool
			*/
			function select_db($database) {
				return mysql_select_db($database, $this->_link);
			}
			
			/** Send query
			* @param string
			* @param bool
			* @return mixed bool or Min_Result
			*/
			function query($query, $unbuffered = false) {
				$result = @($unbuffered ? mysql_unbuffered_query($query, $this->_link) : mysql_query($query, $this->_link)); // @ - mute mysql.trace_mode
				if (!$result) {
					$this->error = mysql_error($this->_link);
					return false;
				}
				if ($result === true) {
					$this->affected_rows = mysql_affected_rows($this->_link);
					$this->info = mysql_info($this->_link);
					return true;
				}
				return new Min_Result($result);
			}
			
			/** Send query with more resultsets
			* @param string
			* @return bool
			*/
			function multi_query($query) {
				return $this->_result = $this->query($query);
			}
			
			/** Get current resultset
			* @return Min_Result
			*/
			function store_result() {
				return $this->_result;
			}
			
			/** Fetch next resultset
			* @return bool
			*/
			function next_result() {
				// MySQL extension doesn't support multiple results
				return false;
			}
			
			/** Get single field from result
			* @param string
			* @param int
			* @return string
			*/
			function result($query, $field = 0) {
				$result = $this->query($query);
				if (!$result) {
					return false;
				}
				return mysql_result($result->_result, 0, $field);
			}
		}
		
		class Min_Result {
			var
				$num_rows, ///< @var int number of rows in the result
				$_result ///< @access private
			;
			
			/** Constructor
			* @param resource
			*/
			function Min_Result($result) {
				$this->_result = $result;
				$this->num_rows = mysql_num_rows($result);
			}
			
			/** Fetch next row as associative array
			* @return array
			*/
			function fetch_assoc() {
				return mysql_fetch_assoc($this->_result);
			}
			
			/** Fetch next row as numbered array
			* @return array
			*/
			function fetch_row() {
				return mysql_fetch_row($this->_result);
			}
			
			/** Fetch next field
			* @return object properties: name, type, orgtable, orgname, charsetnr
			*/
			function fetch_field() {
				$return = mysql_fetch_field($this->_result);
				$return->orgtable = $return->table;
				$return->orgname = $return->name;
				$return->charsetnr = ($return->blob ? 63 : 0);
				return $return;
			}
			
			/** Free result set
			*/
			function __destruct() {
				mysql_free_result($this->_result); //! not called in PHP 4 which is a problem with mysql.trace_mode
			}
		}
		
	} elseif (extension_loaded("pdo_mysql")) {
		class Min_DB extends Min_PDO {
			var $extension = "PDO_MySQL";
			
			function connect($server, $username, $password) {
				$this->dsn("mysql:host=" . str_replace(":", ";unix_socket=", preg_replace('~:([0-9])~', ';port=\\1', $server)), $username, $password);
				return true;
			}
			
			function select_db($database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query("USE " . idf_escape($database));
			}
			
			function query($query, $unbuffered = false) {
				$this->setAttribute(1000, !$unbuffered); // 1000 - PDO::MYSQL_ATTR_USE_BUFFERED_QUERY
				return parent::query($query, $unbuffered);
			}
		}
		
	}

	/** Escape database identifier
	* @param string
	* @return string
	*/
	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	/** Connect to the database
	* @return mixed Min_DB or string for error
	*/
	function connect() {
		global $adminer;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			$connection->query("SET SQL_QUOTE_SHOW_CREATE=1");
			$connection->query("SET NAMES utf8");
			return $connection;
		}
		return $connection->error;
	}

	/** Get cached list of databases
	* @param bool
	* @return array
	*/
	function get_databases($flush = true) {
		// SHOW DATABASES can take a very long time so it is cached
		$return = &get_session("databases");
		if (!isset($return)) {
			if ($flush) {
				restart_session();
				ob_flush();
				flush();
			}
			$return = get_vals("SHOW DATABASES");
		}
		return $return;
	}

	/** Formulate SQL query with limit
	* @param string everything after SELECT
	* @param int
	* @param int
	* @param string
	* @return string
	*/
	function limit($query, $limit, $offset = 0, $separator = " ") {
		return " $query" . (isset($limit) ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	/** Formulate SQL modification query with limit 1
	* @param string everything after UPDATE or DELETE
	* @return string
	*/
	function limit1($query) {
		return limit($query, 1);
	}

	/** Get database collation
	* @param string
	* @param array result of collations()
	* @return string
	*/
	function db_collation($db, $collations) {
		global $connection;
		$return = null;
		$create = $connection->result("SHOW CREATE DATABASE " . idf_escape($db), 1);
		if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
			$return = $match[1];
		} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
			// default collation
			$return = $collations[$match[1]][0];
		}
		return $return;
	}

	/** Get supported engines
	* @return array
	*/
	function engines() {
		global $connection;
		$return = array();
		$result = $connection->query("SHOW ENGINES");
		while ($row = $result->fetch_assoc()) {
			if (ereg("YES|DEFAULT", $row["Support"])) {
				$return[] = $row["Engine"];
			}
		}
		return $return;
	}

	/** Get logged user
	* @return string
	*/
	function logged_user() {
		global $connection;
		return $connection->result("SELECT USER()");
	}

	/** Get tables list
	* @return array
	*/
	function tables_list() {
		global $connection;
		return get_key_vals("SHOW" . ($connection->server_info >= 5 ? " FULL" : "") . " TABLES");
	}

	/** Count tables in all databases
	* @param array
	* @return array array($db => $tables)
	*/
	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = count(get_vals("SHOW TABLES IN " . idf_escape($db)));
		}
		return $return;
	}

	/** Get table status
	* @param string
	* @return array
	*/
	function table_status($name = "") {
		global $connection;
		$return = array();
		$result = $connection->query("SHOW TABLE STATUS" . ($name != "" ? " LIKE " . $connection->quote(addcslashes($name, "%_")) : ""));
		while ($row = $result->fetch_assoc()) {
			if ($row["Engine"] == "InnoDB") {
				// ignore internal comment, unnecessary since MySQL 5.1.21
				$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["Comment"]);
			}
			if (!isset($row["Rows"])) {
				$row["Engine"] = "VIEW";
				$row["Comment"] = "";
			}
			if ($name != "") {
				return $row;
			}
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	/** Check if table supports foreign keys
	* @param array result of table_status
	* @return bool
	*/
	function fk_support($table_status) {
		return ($table_status["Engine"] == "InnoDB");
	}

	/** Get information about fields
	* @param string
	* @return array array($name => array("field" => , "full_type" => , "type" => , "length" => , "unsigned" => , "default" => , "null" => , "auto_increment" => , "on_update" => , "collation" => , "privileges" => , "comment" => , "primary" => ))
	*/
	function fields($table) {
		global $connection;
		$return = array();
		$result = $connection->query("SHOW FULL COLUMNS FROM " . idf_escape($table));
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				preg_match('~^([^( ]+)(?:\\((.+)\\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
				$return[$row["Field"]] = array(
					"field" => $row["Field"],
					"full_type" => $row["Type"],
					"type" => $match[1],
					"length" => $match[2],
					"unsigned" => ltrim($match[3] . $match[4]),
					"default" => ($row["Default"] != "" || ereg("char", $match[1]) ? $row["Default"] : null),
					"null" => ($row["Null"] == "YES"),
					"auto_increment" => ($row["Extra"] == "auto_increment"),
					"on_update" => (eregi('^on update (.+)', $row["Extra"], $match) ? $match[1] : ""), //! available since MySQL 5.1.23
					"collation" => $row["Collation"],
					"privileges" => array_flip(explode(",", $row["Privileges"])),
					"comment" => $row["Comment"],
					"primary" => ($row["Key"] == "PRI"),
				);
			}
		}
		return $return;
	}

	/** Get table indexes
	* @param string
	* @param string Min_DB to use
	* @return array array($key_name => array("type" => , "columns" => array(), "lengths" => array()))
	*/
	function indexes($table, $connection2 = null) {
		global $connection;
		if (!is_object($connection2)) { // use the main connection if the separate connection is unavailable
			$connection2 = $connection;
		}
		$return = array();
		$result = $connection2->query("SHOW INDEX FROM " . idf_escape($table));
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$return[$row["Key_name"]]["type"] = ($row["Key_name"] == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? "INDEX" : "UNIQUE")));
				$return[$row["Key_name"]]["columns"][] = $row["Column_name"];
				$return[$row["Key_name"]]["lengths"][] = $row["Sub_part"];
			}
		}
		return $return;
	}

	/** Get foreign keys in table
	* @param string
	* @return array array($name => array("db" => , "table" => , "source" => array(), "target" => array(), "on_delete" => , "on_update" => ))
	*/
	function foreign_keys($table) {
		global $connection, $on_actions;
		static $pattern = '`(?:[^`]|``)+`';
		$return = array();
		$create_table = $connection->result("SHOW CREATE TABLE " . idf_escape($table), 1);
		if ($create_table) {
			preg_match_all("~CONSTRAINT ($pattern) FOREIGN KEY \\(((?:$pattern,? ?)+)\\) REFERENCES ($pattern)(?:\\.($pattern))? \\(((?:$pattern,? ?)+)\\)(?: ON DELETE (" . implode("|", $on_actions) . "))?(?: ON UPDATE (" . implode("|", $on_actions) . "))?~", $create_table, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				preg_match_all("~$pattern~", $match[2], $source);
				preg_match_all("~$pattern~", $match[5], $target);
				$return[idf_unescape($match[1])] = array(
					"db" => idf_unescape($match[4] != "" ? $match[3] : $match[4]),
					"table" => idf_unescape($match[4] != "" ? $match[4] : $match[3]),
					"source" => array_map('idf_unescape', $source[0]),
					"target" => array_map('idf_unescape', $target[0]),
					"on_delete" => $match[6],
					"on_update" => $match[7],
				);
			}
		}
		return $return;
	}

	/** Get view SELECT
	* @param string
	* @return array array("select" => )
	*/
	function view($name) {
		global $connection;
		return array("select" => preg_replace('~^(?:[^`]|`[^`]*`)*\\s+AS\\s+~isU', '', $connection->result("SHOW CREATE VIEW " . idf_escape($name), 1)));
	}

	/** Get sorted grouped list of collations
	* @return array
	*/
	function collations() {
		global $connection;
		$return = array();
		$result = $connection->query("SHOW COLLATION");
		while ($row = $result->fetch_assoc()) {
			$return[$row["Charset"]][] = $row["Collation"];
		}
		ksort($return);
		foreach ($return as $key => $val) {
			sort($return[$key]);
		}
		return $return;
	}

	/** Find out if database is information_schema
	* @param string
	* @return bool
	*/
	function information_schema($db) {
		global $connection;
		return ($connection->server_info >= 5 && $db == "information_schema");
	}

	/** Get escaped error message
	* @return string
	*/
	function error() {
		global $connection;
		return h(preg_replace('~^You have an error.*syntax to use~U', "Syntax error", $connection->error));
	}

	/** Return expression for binary comparison
	* @param string
	* @return string
	*/
	function exact_value($val) {
		global $connection;
		return $connection->quote($val) . " COLLATE utf8_bin";
	}

	/** Create database
	* @param string
	* @return string
	*/
	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " COLLATE " . $connection->quote($collation) : ""));
	}
	
	/** Drop databases
	* @param array
	* @return bool
	*/
	function drop_databases($databases) {
		foreach ($databases as $db) {
			if (!queries("DROP DATABASE " . idf_escape($db))) {
				return false;
			}
		}
		return true;
	}
	
	/** Rename database from DB
	* @param string new name
	* @return string
	* @return bool
	*/
	function rename_database($name, $collation) {
		global $connection;
		$return = false;
		if (create_database($name, $collation)) {
			//! move triggers
			$return = true; // table list may by empty
			foreach (tables_list() as $table) {
				if (!queries("RENAME TABLE " . idf_escape($table) . " TO " . idf_escape($name) . "." . idf_escape($table))) {
					$return = false;
					break;
				}
			}
			if ($return) {
				queries("DROP DATABASE " . idf_escape(DB));
				//! saved to history of removed database
			}
		}
		return $return;
	}
	
	/** Generate modifier for auto increment column
	* @return string
	*/
	function auto_increment() {
		$auto_increment_index = " PRIMARY KEY";
		// don't overwrite primary key by auto_increment
		if ($_GET["create"] != "" && $_POST["auto_increment_col"]) {
			foreach (indexes($_GET["create"]) as $index) {
				if (in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"], $index["columns"], true)) {
					$auto_increment_index = "";
					break;
				}
				if ($index["type"] == "PRIMARY") {
					$auto_increment_index = " UNIQUE";
				}
			}
		}
		return " AUTO_INCREMENT$auto_increment_index";
	}
	
	/** Run commands to create or alter table
	* @param string "" to create
	* @param string new name
	* @param array of array($orig, $process_field, $after)
	* @param array of strings
	* @param string
	* @param string
	* @param string
	* @param int
	* @param string
	* @return bool
	*/
	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		global $connection;
		$alter = array();
		foreach ($fields as $field) {
			$alter[] = ($field[1]
				? ($table != "" ? ($field[0] != "" ? "CHANGE " . idf_escape($field[0]) : "ADD") : " ") . " " . implode("", $field[1]) . ($table != "" ? " $field[2]" : "")
				: "DROP " . idf_escape($field[0])
			);
		}
		$alter = array_merge($alter, $foreign);
		$status = "COMMENT=" . $connection->quote($comment)
			. ($engine ? " ENGINE=" . $connection->quote($engine) : "")
			. ($collation ? " COLLATE " . $connection->quote($collation) : "")
			. ($auto_increment != "" ? " AUTO_INCREMENT=$auto_increment" : "")
			. $partitioning
		;
		if ($table == "") {
			return queries("CREATE TABLE " . idf_escape($name) . " (\n" . implode(",\n", $alter) . "\n) $status");
		}
		if ($table != $name) {
			$alter[] = "RENAME TO " . idf_escape($name);
		}
		$alter[] = $status;
		return queries("ALTER TABLE " . idf_escape($table) . "\n" . implode(",\n", $alter));
	}
	
	/** Run commands to alter indexes
	* @param string escaped table name
	* @param array of array("index type", "(columns definition)") or array("index type", "escaped name", "DROP")
	* @return bool
	*/
	function alter_indexes($table, $alter) {
		foreach ($alter as $key => $val) {
			$alter[$key] = ($val[2] ? "\nDROP INDEX " : "\nADD $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "")) . $val[1];
		}
		return queries("ALTER TABLE " . idf_escape($table) . implode(",", $alter));
	}
	
	/** Run commands to truncate tables
	* @param array
	* @return bool
	*/
	function truncate_tables($tables) {
		foreach ($tables as $table) {
			if (!queries("TRUNCATE TABLE " . idf_escape($table))) {
				return false;
			}
		}
		return true;
	}
	
	/** Drop views
	* @param array
	* @return bool
	*/
	function drop_views($views) {
		return queries("DROP VIEW " . implode(", ", array_map('idf_escape', $views)));
	}
	
	/** Drop tables
	* @param array
	* @return bool
	*/
	function drop_tables($tables) {
		return queries("DROP TABLE " . implode(", ", array_map('idf_escape', $tables)));
	}
	
	/** Get information about trigger
	* @param string trigger name
	* @return array array("Trigger" => , "Timing" => , "Event" => , "Statement" => )
	*/
	function trigger($name) {
		global $connection;
		$result = $connection->query("SHOW TRIGGERS WHERE `Trigger` = " . $connection->quote($name));
		return $result->fetch_assoc();
	}
	
	/** Get defined triggers
	* @param string
	* @return array array($name => array($timing, $event))
	*/
	function triggers($table) {
		global $connection;
		$return = array();
		$result = $connection->query("SHOW TRIGGERS LIKE " . $connection->quote(addcslashes($table, "%_")));
		while ($row = $result->fetch_assoc()) {
			$return[$row["Trigger"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}
	
	/** Get trigger options
	* @return array ("Timing" => array(), "Type" => array())
	*/
	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			// Event is always INSERT, UPDATE, DELETE
			"Type" => array("FOR EACH ROW"),
		);
	}
	
	/** Get information about stored routine
	* @param string
	* @param string FUNCTION or PROCEDURE
	* @return array ("fields" => array("field" => , "type" => , "length" => , "unsigned" => , "inout" => , "collation" => ), "returns" => , "definition" => )
	*/
	function routine($name, $type) {
		global $connection, $enum_length, $inout, $types;
		$aliases = array("bit" => "tinyint", "bool" => "tinyint", "boolean" => "tinyint", "integer" => "int", "double precision" => "float", "real" => "float", "dec" => "decimal", "numeric" => "decimal", "fixed" => "decimal", "national char" => "char", "national varchar" => "varchar");
		$type_pattern = "((" . implode("|", array_keys($types + $aliases)) . ")(?:\\s*\\(((?:[^'\")]*|$enum_length)+)\\))?\\s*(zerofill\\s*)?(unsigned(?:\\s+zerofill)?)?)(?:\\s*(?:CHARSET|CHARACTER\\s+SET)\\s*['\"]?([^'\"\\s]+)['\"]?)?";
		$pattern = "\\s*(" . ($type == "FUNCTION" ? "" : implode("|", $inout)) . ")?\\s*(?:`((?:[^`]|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
		$create = $connection->result("SHOW CREATE $type " . idf_escape($name), 2);
		preg_match("~\\(((?:$pattern\\s*,?)*)\\)" . ($type == "FUNCTION" ? "\\s*RETURNS\\s+$type_pattern" : "") . "\\s*(.*)~is", $create, $match);
		$fields = array();
		preg_match_all("~$pattern\\s*,?~is", $match[1], $matches, PREG_SET_ORDER);
		foreach ($matches as $param) {
			$name = str_replace("``", "`", $param[2]) . $param[3];
			$data_type = strtolower($param[5]);
			$fields[] = array(
				"field" => $name,
				"type" => (isset($aliases[$data_type]) ? $aliases[$data_type] : $data_type),
				"length" => preg_replace_callback("~$enum_length~s", 'normalize_enum', $param[6]),
				"unsigned" => strtolower(preg_replace('~\\s+~', ' ', trim("$param[8] $param[7]"))),
				"full_type" => $param[4],
				"inout" => strtoupper($param[1]),
				"collation" => strtolower($param[9]),
			);
		}
		if ($type != "FUNCTION") {
			return array("fields" => $fields, "definition" => $match[11]);
		}
		return array(
			"fields" => $fields,
			"returns" => array("type" => $match[12], "length" => $match[13], "unsigned" => $match[15], "collation" => $match[16]),
			"definition" => $match[17],
		);
	}
	
	function routines() {
		global $connection;
		$return = array();
		$result = $connection->query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $connection->quote(DB));
		while ($row = $result->fetch_assoc()) {
			$return[] = $row;
		}
		return $return;
	}
	
	/** Begin transaction
	* @return bool
	*/
	function begin() {
		return queries("BEGIN");
	}
	
	/** Insert data into table
	* @param string
	* @param array
	* @return bool
	*/
	function insert_into($table, $set) {
		return queries("INSERT INTO " . idf_escape($table) . " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")");
	}
	
	/** Explain select
	* @param Min_DB
	* @param string
	* @return Min_Result
	*/
	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}
	
	/** Get SQL command to create table
	* @param string
	* @return string
	*/
	function create_sql($table) {
		global $connection;
		return $connection->result("SHOW CREATE TABLE " . idf_escape($table), 1);
	}
	
	/** Get SQL command to change database
	* @param string
	* @return string
	*/
	function use_sql($database) {
		return "USE " . idf_escape($database);
	}
	
	/** Get server variables
	* @return array ($name => $value)
	*/
	function show_variables() {
		return get_key_vals("SHOW VARIABLES");
	}
	
	/** Get status variables
	* @return array ($name => $value)
	*/
	function show_status() {
		return get_key_vals("SHOW STATUS");
	}
	
	/** Check whether a feature is supported
	* @param string
	* @return bool
	*/
	function support($feature) {
		global $connection;
		return ($connection->server_info >= 5.1 || ($connection->server_info >= 5 && !ereg("event|partitioning")) || !ereg("view|routine|trigger"));
	}

	$driver = "sql"; ///< @var string JUSH identifier
	$types = array(); ///< @var array ($type => $maximum_unsigned_length, ...)
	$structured_types = array(); ///< @var array ($description => array($type, ...), ...)
	foreach (array(
		lang('Numbers') => array("tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20, "decimal" => 66, "float" => 12, "double" => 21),
		lang('Date and time') => array("date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4),
		lang('Strings') => array("char" => 255, "varchar" => 65535, "tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295),
		lang('Binary') => array("bit" => 20, "binary" => 255, "varbinary" => 65535, "tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295),
		lang('Lists') => array("enum" => 65535, "set" => 64),
	) as $key => $val) {
		$types += $val;
		$structured_types[$key] = array_keys($val);
	}
	$unsigned = array("unsigned", "zerofill", "unsigned zerofill"); ///< @var array number variants
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "REGEXP", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL"); ///< @var array operators used in select
	$functions = array("char_length", "date", "from_unixtime", "hex", "lower", "round", "sec_to_time", "time_to_sec", "upper"); ///< @var array functions used in select
	$grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum"); ///< @var array grouping functions used in select
	$edit_functions = array( ///< @var array of array("$type|$type2" => "$function/$function2") functions used in editing, [0] - edit and insert, [1] - edit only
		array(
			"char" => "md5/sha1/password/encrypt/uuid", //! JavaScript for disabling maxlength
			"date|time" => "now",
		), array(
			"int|float|double|decimal" => "+/-",
			"date" => "+ interval/- interval",
			"time" => "addtime/subtime",
			"char|text" => "concat",
		)
	);
}
