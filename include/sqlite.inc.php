<?php
if (extension_loaded($_GET["sqlite_version"] == 2 ? "sqlite" : "sqlite3")) {
	if ($_GET["sqlite_version"] == 2) {
		
		class SQLite extends SQLiteDatabase {
			var $extension = "SQLite";
			
			function open($filename) {
				parent::__construct($filename);
			}
			
			function query($query) {
				$result = @parent::query($query, SQLITE_BOTH, $error);
				if (!$result) {
					$this->error = $error;
					return false;
				} elseif ($result === true) {
					$this->affected_rows = parent::changes();
					return true;
				}
				return new Min_SQLiteResult($result);
			}
			
			function escape_string($string) {
				return sqlite_escape_string($string);
			}
			
			function result($result, $field = 0) {
				if (!$result) {
					return false;
				}
				$row = $result->_result->fetch();
				return $row[$field];
			}
		}
		
		class Min_SQLiteResult {
			var $_result, $num_rows;
			
			function __construct($result) {
				$this->_result = $result;
				$this->num_rows = $result->numRows();
			}
			
			function fetch_assoc() {
				return $this->_result->fetch(SQLITE_ASSOC);
			}
			
			function fetch_row() {
				return $this->_result->fetch(SQLITE_NUM);
			}
			
			function fetch_field() {
				static $column = -1;
				$column++;
				return (object) array(
					"name" => parent::fieldName($column),
					//! type, orgtable, charsetnr
				);
			}
			
			function free() {
			}
		}
		
	} else {
		
		class SQLite extends SQLite3 {
			var $extension = "SQLite3";
			
			function open($filename) {
				parent::__construct($filename);
			}
			
			function query($query) {
				$result = @parent::query($query);
				if (!$result) {
					$this->error = parent::lastErrorMsg();
					return false;
				} elseif ($result === true) {
					$this->affected_rows = parent::changes();
					return true;
				}
				return new Min_SQLiteResult($result);
			}
			
			function escape_string($string) {
				return parent::escapeString($string);
			}
			
			function result($result, $field = 0) {
				if (!$result) {
					return false;
				}
				$row = $result->_result->fetchArray();
				return $row[$field];
			}
		}
		
		class Min_SQLiteResult {
			var $_result, $num_rows;
			
			function __construct($result) {
				$this->_result = $result;
				//! $this->num_rows = ;
			}
			
			function fetch_assoc() {
				return $this->_result->fetchArray(SQLITE3_ASSOC);
			}
			
			function fetch_row() {
				return $this->_result->fetchArray(SQLITE3_NUM);
			}
			
			function fetch_field() {
				static $column = -1;
				$column++;
				return (object) array(
					"name" => parent::columnName($column),
					"type" => parent::columnType($column),
					//! orgtable, charsetnr
				);
			}
			
			function free() {
				return $this->_result->finalize();
			}
		}
		
	}
	
	class Min_SQLite extends SQLite {
		
		function __construct() {
		}
		
		function connect() {
		}
		
		function select_db($filename) {
			set_exception_handler('connect_error'); // try/catch is not compatible with PHP 4
			$this->open($filename);
			restore_exception_handler();
			$this->server_info = $this->result($this->query("SELECT sqlite_version()"));
			return true;
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
	
} elseif (extension_loaded("pdo_sqlite")) {
	class Min_PDO_MySQL extends Min_PDO {
		var $extension = "PDO_MySQL";
		
		function connect() {
		}
		
		function select_db($filename) {
			set_exception_handler('connect_error'); // try/catch is not compatible with PHP 4
			parent::__construct(($_GET["sqlite_version"] == 2 ? "sqlite2" : "sqlite") . ":$filename");
			restore_exception_handler();
			$this->setAttribute(13, array('Min_PDOStatement')); // PDO::ATTR_STATEMENT_CLASS
			$this->server_info = $this->result($this->query("SELECT sqlite_version()"));
			return true;
		}
	}
	
	$dbh = new Min_PDO_SQLite;
}

$types = array("text" => 0, "numeric" => 0, "integer" => 0, "real" => 0, "blob" => 0);
$unsigned = array();

function get_databases() {
	return array();
}

function table_status($table) {
	return array();
}

function fields($table) {
	global $dbh;
	$return = array();
	$result = $dbh->query("PRAGMA table_info(" . idf_escape($table) . ")");
	while ($row = $result->fetch_assoc()) {
		preg_match('~^([^( ]+)(?:\\((.+)\\))?$~', $row["Type"], $match);
		$return[$row["Field"]] = array(
			"field" => $row["name"],
			"type" => $match[1],
			"length" => $match[2],
			"default" => $row["dflt_value"],
			"null" => !$row["notnull"],
			"auto_increment" => false, //!
			"collation" => $row["Collation"], //!
			"comment" => "", //!
			"primary" => $row["pk"],
		);
	}
	$result->free();
	return $return;
}

function indexes($table) {
	global $dbh;
	$return = array();
	$result = $dbh->query("PRAGMA index_list(" . idf_escape($table) . ")");
	while ($row = $result->fetch_assoc()) {
		$return[$row["name"]]["type"] = ($row["unique"] ? "UNIQUE" : "INDEX");
		$result1 = $dbh->query("PRAGMA index_info(" . idf_escape($row["name"]) . ")");
		while ($row1 = $result1->fetch_assoc()) {
			$return[$row["name"]]["columns"][$row1["seqno"]] = $row1["name"];
		}
		$result1->free();
	}
	$result->free();
	//! detect primary key from table definition
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
	return array("select" => preg_replace('~^(?:[^`]+|`[^`]*`)* AS ~iU', '', $dbh->result($dbh->query("SELECT sql FROM sqlite_master WHERE name = '" . $dbh->escape_string($name) . "'"), 0)));
}

function collations() {
	return get_vals("PRAGMA collation_list", 1);
}

function table_comment(&$row) {
}
