<?php
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
				(strlen($server) ? $host : ini_get("mysqli.default_host")),
				(strlen("$server$username") ? $username : ini_get("mysqli.default_user")),
				(strlen("$server$username$password") ? $password : ini_get("mysqli.default_pw")),
				null,
				(is_numeric($port) ? $port : ini_get("mysqli.default_port")),
				(!is_numeric($port) ? $port : null)
			);
		}
		
		function result($result, $field = 0) {
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
		var $extension = "MySQL", $_link, $_result, $server_info, $affected_rows, $error;
		
		function connect($server, $username, $password) {
			$this->_link = @mysql_connect(
				(strlen($server) ? $server : ini_get("mysql.default_host")),
				(strlen("$server$username") ? $username : ini_get("mysql.default_user")),
				(strlen("$server$username$password") ? $password : ini_get("mysql.default_password")),
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
		
		function quote($string) {
			return "'" . mysql_real_escape_string($string, $this->_link) . "'";
		}
		
		function select_db($database) {
			return mysql_select_db($database, $this->_link);
		}
		
		function query($query, $unbuffered = false) {
			$result = @($unbuffered ? mysql_unbuffered_query($query, $this->_link) : mysql_query($query, $this->_link)); // @ - mute mysql.trace_mode
			if (!$result) {
				$this->error = mysql_error($this->_link);
				return false;
			} elseif ($result === true) {
				$this->affected_rows = mysql_affected_rows($this->_link);
				return true;
			}
			return new Min_Result($result);
		}
		
		function multi_query($query) {
			return $this->_result = $this->query($query);
		}
		
		function store_result() {
			return $this->_result;
		}
		
		function next_result() {
			// MySQL extension doesn't support multiple results
			return false;
		}
		
		function result($result, $field = 0) {
			if (!$result) {
				return false;
			}
			return mysql_result($result->_result, 0, $field);
		}
	}
	
	class Min_Result {
		var $_result, $_offset = 0, $num_rows;
		
		function Min_Result($result) {
			$this->_result = $result;
			$this->num_rows = mysql_num_rows($result);
		}
		
		function fetch_assoc() {
			return mysql_fetch_assoc($this->_result);
		}
		
		function fetch_row() {
			return mysql_fetch_row($this->_result);
		}
		
		function fetch_field() {
			$row = mysql_fetch_field($this->_result, $this->_offset++);
			$row->orgtable = $row->table;
			$row->orgname = $row->name;
			$row->charsetnr = ($row->blob ? 63 : 0);
			return $row;
		}
		
		function __destruct() {
			mysql_free_result($this->_result); //! is not called in PHP 4 which is a problem with mysql.trace_mode
		}
	}
	
} elseif (extension_loaded("pdo_mysql")) {
	class Min_DB extends Min_PDO {
		var $extension = "PDO_MySQL";
		
		function connect($server, $username, $password) {
			$this->dsn("mysql:host=" . str_replace(":", ";unix_socket=", preg_replace('~:([0-9])~', ';port=\\1', $server)), $username, $password);
			$this->server_info = $this->result($this->query("SELECT VERSION()"));
			return true;
		}
		
		function query($query, $unbuffered = false) {
			$this->setAttribute(1000, !$unbuffered); // 1000 - PDO::MYSQL_ATTR_USE_BUFFERED_QUERY
			return parent::query($query, $unbuffered);
		}
	}
	
} else {
	page_header(lang('No MySQL extension'), lang('None of the supported PHP extensions (%s) are available.', 'MySQLi, MySQL, PDO_MySQL'), null);
	page_footer("auth");
	exit;
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
	$return = &$_SESSION["databases"][$_GET["server"]];
	if (!isset($return)) {
		restart_session();
		$return = get_vals("SHOW DATABASES");
		if ($flush) {
			ob_flush();
			flush();
		}
	}
	return $return;
}

/** Get database collation
* @param string
* @param array result of collations()
* @return array
*/
function db_collation($db, $collations) {
	global $connection;
	$return = null;
	$result = $connection->query("SHOW CREATE DATABASE " . idf_escape($db));
	if ($result) {
		$create = $connection->result($result, 1);
		if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
			$return = $match[1];
		} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
			// default collation
			$return = $collations[$match[1]][0];
		}
	}
	return $return;
}

/**Get supported engines 
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

/** Get tables list
* @return array
*/
function tables_list() {
	return get_vals("SHOW TABLES");
}

/** Get table status
* @param string
* @return array
*/
function table_status($name = "") {
	global $connection;
	$return = array();
	$result = $connection->query("SHOW TABLE STATUS" . (strlen($name) ? " LIKE " . $connection->quote(addcslashes($name, "%_")) : ""));
	while ($row = $result->fetch_assoc()) {
		if ($row["Engine"] == "InnoDB") {
			// ignore internal comment, unnecessary since MySQL 5.1.21
			$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["Comment"]);
		}
		if (strlen($name)) {
			return $row;
		}
		$return[$row["Name"]] = $row;
	}
	return $return;
}

/** Get status of referencable tables
* @return array
*/
function table_status_referencable() {
	$return = array();
	foreach (table_status() as $name => $row) {
		if ($row["Engine"] == "InnoDB") {
			$return[$name] = $row;
		}
	}
	return $return;
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
				"default" => (strlen($row["Default"]) || ereg("char", $match[1]) ? $row["Default"] : null),
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
			$return[$row["Key_name"]]["columns"][$row["Seq_in_index"]] = $row["Column_name"];
			$return[$row["Key_name"]]["lengths"][$row["Seq_in_index"]] = $row["Sub_part"];
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
	static $pattern = '(?:[^`]|``)+';
	$return = array();
	$result = $connection->query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		$create_table = $connection->result($result, 1);
		preg_match_all("~CONSTRAINT `($pattern)` FOREIGN KEY \\(((?:`$pattern`,? ?)+)\\) REFERENCES `($pattern)`(?:\\.`($pattern)`)? \\(((?:`$pattern`,? ?)+)\\)(?: ON DELETE (" . implode("|", $on_actions) . "))?(?: ON UPDATE (" . implode("|", $on_actions) . "))?~", $create_table, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			preg_match_all("~`($pattern)`~", $match[2], $source);
			preg_match_all("~`($pattern)`~", $match[5], $target);
			$return[$match[1]] = array(
				"db" => idf_unescape(strlen($match[4]) ? $match[3] : $match[4]),
				"table" => idf_unescape(strlen($match[4]) ? $match[4] : $match[3]),
				"source" => array_map('idf_unescape', $source[1]),
				"target" => array_map('idf_unescape', $target[1]),
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
	return array("select" => preg_replace('~^(?:[^`]|`[^`]*`)* AS ~U', '', $connection->result($connection->query("SHOW CREATE VIEW " . idf_escape($name)), 1)));
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

/** Return expression for binary comparison
* @param string
* @return string
*/
function exact_value($val) {
	global $connection;
	return "BINARY " . $connection->quote($val);
}

// value means maximum unsigned length
$types = array();
$structured_types = array();
foreach (array(
	lang('Numbers') => array("tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20, "float" => 12, "double" => 21, "decimal" => 66),
	lang('Date and time') => array("date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4),
	lang('Strings') => array("char" => 255, "varchar" => 65535, "tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295),
	lang('Binary') => array("binary" => 255, "varbinary" => 65535, "tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295),
	lang('Lists') => array("enum" => 65535, "set" => 64),
) as $key => $val) {
	$types += $val;
	$structured_types[$key] = array_keys($val);
}
$unsigned = array("unsigned", "zerofill", "unsigned zerofill");
