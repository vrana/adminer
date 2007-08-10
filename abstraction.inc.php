<?php
if (extension_loaded("mysqli")) {
	class Min_MySQLi extends MySQLi {
		function Min_MySQLi() {
			$this->init();
		}
		
		function connect($server, $username, $password) {
			return @$this->real_connect(
				(strlen($server) ? $server : ini_get("mysqli.default_host")),
				(strlen("$server$username") ? $username : ini_get("mysqli.default_user")),
				(strlen("$server$username$password") ? $password : ini_get("mysqli.default_pw"))
			);
		}
		
		function result($result, $field = 0) {
			$row = $result->fetch_array();
			return $row[$field];
		}
	}
	
	$mysql = new Min_MySQLi;

} elseif (extension_loaded("mysql")) {
	class Min_MySQL {
		var $_link, $_result, $server_info, $affected_rows, $error;
		
		function connect($server, $username, $password) {
			$this->_link = @mysql_pconnect(
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
	
	$mysql = new Min_MySQL;

} elseif (extension_loaded("pdo_mysql")) {
	class Min_PDO_MySQL extends PDO {
		var $_result, $server_info, $affected_rows, $error;
		
		function __construct() {
		}
		
		function connect($server, $username, $password) {
			set_exception_handler('auth_error'); // try/catch is not compatible with PHP 4
			parent::__construct("mysql:host=$server", $username, $password);
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
	
	$mysql = new Min_PDO_MySQL;

} else {
	page_header(lang('No MySQL extension'), null);
	echo "<p class='error'>" . lang('None of supported PHP extensions (%s) are available.', 'mysqli, mysql, pdo') . "</p>\n";
	page_footer("auth");
	exit;
}
