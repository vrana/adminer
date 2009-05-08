<?php
if (extension_loaded("mysqli")) {
	class Min_MySQLi extends MySQLi {
		var $extension = "MySQLi";
		
		function Min_MySQLi() {
			$this->init();
		}
		
		function connect($server, $username, $password) {
			list($host, $port) = explode(":", $server, 2);
			return @$this->real_connect(
				(strlen($server) ? $host : ini_get("mysqli.default_host")),
				(strlen("$server$username") ? $username : ini_get("mysqli.default_user")),
				(strlen("$server$username$password") ? $password : ini_get("mysqli.default_pw")),
				null,
				(strlen($port) ? $port : ini_get("mysqli.default_port"))
			);
		}
		
		function result($result, $field = 0) {
			if (!$result) {
				return false;
			}
			$row = $result->_result->fetch_array();
			return $row[$field];
		}
		
		// minification compatibility start
		function select_db($database) {
			return parent::select_db($database);
		}
		
		function query($query) {
			$result = parent::query($query);
			return (is_object($result) ? new Min_MySQLiResult($result) : $result);
		}
		
		function multi_query($query) {
			return parent::multi_query($query);
		}
		
		function store_result() {
			$result = parent::store_result();
			return (is_object($result) ? new Min_MySQLiResult($result) : $result);
		}
		
		function next_result() {
			return parent::next_result();
		}
		
		function escape_string($string) {
			return parent::escape_string($string);
		}
	}
	
	class Min_MySQLiResult {
		var $_result, $num_rows;
		
		function __construct($result) {
			$this->_result = $result;
			$this->num_rows = $result->num_rows;
		}
		
		function fetch_assoc() {
			return $this->_result->fetch_assoc();
		}
		
		function fetch_row() {
			return $this->_result->fetch_row();
		}
		
		function fetch_field() {
			return $this->_result->fetch_field();
		}
		
		function free() {
			return $this->_result->free();
		}
		// minification compatibility end
	}
	
	$dbh = new Min_MySQLi;

} elseif (extension_loaded("mysql")) {
	class Min_MySQL {
		var $extension = "MySQL", $_link, $_result, $server_info, $affected_rows, $error;
		
		function connect($server, $username, $password) {
			$this->_link = @mysql_connect(
				(strlen($server) ? $server : ini_get("mysql.default_host")),
				(strlen("$server$username") ? $username : ini_get("mysql.default_user")),
				(strlen("$server$username$password") ? $password : ini_get("mysql.default_password")),
				131072 // CLIENT_MULTI_RESULTS for CALL
			);
			if ($this->_link) {
				$this->server_info = mysql_get_server_info($this->_link);
			}
			return (bool) $this->_link;
		}
		
		function select_db($database) {
			return mysql_select_db($database, $this->_link);
		}
		
		function query($query) {
			$result = mysql_query($query, $this->_link);
			if (!$result) {
				$this->error = mysql_error($this->_link);
				return false;
			} elseif ($result === true) {
				$this->affected_rows = mysql_affected_rows($this->_link);
				return true;
			}
			return new Min_MySQLResult($result);
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
		
		function result($result, $field = 0) {
			if (!$result) {
				return false;
			}
			return mysql_result($result->_result, 0, $field);
		}
		
		function escape_string($string) {
			return mysql_real_escape_string($string, $this->_link);
		}
	}
	
	class Min_MySQLResult {
		var $_result, $_offset = 0, $num_rows;
		
		function Min_MySQLResult($result) {
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
		
		function free() {
			return mysql_free_result($this->_result);
		}
	}
	
	$dbh = new Min_MySQL;

} elseif (extension_loaded("pdo_mysql")) {
	class Min_PDO_MySQL extends PDO {
		var $extension = "PDO_MySQL", $_result, $server_info, $affected_rows, $error;
		
		function __construct() {
		}
		
		function connect($server, $username, $password) {
			set_exception_handler('auth_error'); // try/catch is not compatible with PHP 4
			parent::__construct("mysql:host=" . str_replace(":", ";port=", $server), $username, $password);
			restore_exception_handler();
			$this->setAttribute(13, array('Min_PDOStatement')); // PDO::ATTR_STATEMENT_CLASS
			$this->server_info = $this->result($this->query("SELECT VERSION()"));
			return true;
		}
		
		function select_db($database) {
			return $this->query("USE " . idf_escape($database));
		}
		
		function query($query) {
			$result = parent::query($query);
			if (!$result) {
				$errorInfo = $this->errorInfo();
				$this->error = $errorInfo[2];
				return false;
			}
			$this->_result = $result;
			if (!$result->columnCount()) {
				$this->affected_rows = $result->rowCount();
				return true;
			}
			$result->num_rows = $result->rowCount();
			return $result;
		}
		
		function multi_query($query) {
			return $this->query($query);
		}
		
		function store_result() {
			return ($this->_result->columnCount() ? $this->_result : true);
		}
		
		function next_result() {
			return $this->_result->nextRowset();
		}
		
		function result($result, $field = 0) {
			if (!$result) {
				return false;
			}
			$row = $result->fetch();
			return $row[$field];
		}
		
		function escape_string($string) {
			return substr($this->quote($string), 1, -1);
		}
	}
	
	class Min_PDOStatement extends PDOStatement {
		var $_offset = 0, $num_rows;
		
		function fetch_assoc() {
			return $this->fetch(2); // PDO::FETCH_ASSOC
		}
		
		function fetch_row() {
			return $this->fetch(3); // PDO::FETCH_NUM
		}
		
		function fetch_field() {
			$row = (object) $this->getColumnMeta($this->_offset++);
			$row->orgtable = $row->table;
			$row->orgname = $row->name;
			$row->charsetnr = (in_array("blob", $row->flags) ? 63 : 0);
			return $row;
		}
		
		function free() {
			// $this->__destruct() is not callable
		}
	}
	
	$dbh = new Min_PDO_MySQL;

} else {
	page_header(lang('No MySQL extension'), lang('None of supported PHP extensions (%s) are available.', 'MySQLi, MySQL, PDO_MySQL'), null);
	page_footer("auth");
	exit;
}

$types = array(
	"tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20,
	"float" => 12, "double" => 21, "decimal" => 66,
	"date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4,
	"char" => 255, "varchar" => 65535,
	"binary" => 255, "varbinary" => 65535,
	"tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295,
	"tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295,
	"enum" => 65535, "set" => 64,
);
$unsigned = array("", "unsigned", "zerofill", "unsigned zerofill");

function get_databases() {
	$return = &$_SESSION["databases"][$_GET["server"]];
	if (!isset($return)) {
		flush();
		$return = get_vals("SHOW DATABASES");
	}
	return $return;
}

function fields($table) {
	global $dbh;
	$return = array();
	$result = $dbh->query("SHOW FULL COLUMNS FROM " . idf_escape($table));
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			preg_match('~^([^( ]+)(?:\\((.+)\\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
			$return[$row["Field"]] = array(
				"field" => $row["Field"],
				"type" => $match[1],
				"length" => $match[2],
				"unsigned" => ltrim($match[3] . $match[4]),
				"default" => (strlen($row["Default"]) || ereg("char", $match[1]) ? $row["Default"] : null),
				"null" => ($row["Null"] == "YES"),
				"auto_increment" => ($row["Extra"] == "auto_increment"),
				"collation" => $row["Collation"],
				"privileges" => array_flip(explode(",", $row["Privileges"])),
				"comment" => $row["Comment"],
				"primary" => ($row["Key"] == "PRI"),
			);
		}
		$result->free();
	}
	return $return;
}

function indexes($table) {
	global $dbh;
	$return = array();
	$result = $dbh->query("SHOW INDEX FROM " . idf_escape($table));
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$return[$row["Key_name"]]["type"] = ($row["Key_name"] == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? "INDEX" : "UNIQUE")));
			$return[$row["Key_name"]]["columns"][$row["Seq_in_index"]] = $row["Column_name"];
			$return[$row["Key_name"]]["lengths"][$row["Seq_in_index"]] = $row["Sub_part"];
		}
		$result->free();
	}
	return $return;
}

function foreign_keys($table) {
	global $dbh, $on_actions;
	static $pattern = '(?:[^`]+|``)+';
	$return = array();
	$result = $dbh->query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		$create_table = $dbh->result($result, 1);
		$result->free();
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

function view($name) {
	global $dbh;
	return array("select" => preg_replace('~^(?:[^`]+|`[^`]*`)* AS ~U', '', $dbh->result($dbh->query("SHOW CREATE VIEW " . idf_escape($name)), 1)));
}

function collations() {
	global $dbh;
	$return = array();
	$result = $dbh->query("SHOW COLLATION");
	while ($row = $result->fetch_assoc()) {
		if ($row["Default"] && $return[$row["Charset"]]) {
			array_unshift($return[$row["Charset"]], $row["Collation"]);
		} else {
			$return[$row["Charset"]][] = $row["Collation"];
		}
	}
	$result->free();
	return $return;
}

function table_comment(&$row) {
	if ($row["Engine"] == "InnoDB") {
		$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["Comment"]);
	}
}
