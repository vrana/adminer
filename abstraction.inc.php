<?php
if (extension_loaded("mysqli")) {
	class Min_MySQLi extends MySQLi {
		function Min_MySQLi() {
			$this->init();
		}
		
		function connect($server, $username, $password) {
			return $this->real_connect(
				(strlen($server) ? $server : ini_get("mysqli.default_host")),
				(strlen("$server$username") ? $username : ini_get("mysqli.default_user")),
				(strlen("$server$username$password") ? $password : ini_get("mysqli.default_pw"))
			);
		}
		
		function result($result, $offset, $field = 0) {
			$result->data_seek($offset);
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

		function result($result, $offset, $field = 0) {
			return mysql_result($result->_result, $offset, $field);
		}
		
		function select_db($database) {
			return mysql_select_db($database, $this->_link);
		}
		
		function real_escape_string($string) {
			return mysql_real_escape_string($string, $this->_link);
		}
	}
	
	class Min_MySQLResult {
		var $_result, $_offset, $num_rows;
		
		function Min_MySQLResult($result) {
			$this->_result = $result;
			$this->_offset = 0;
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

} else {
	page_header(lang('No MySQL extension'));
	echo "<p class='error'>" . lang('None of supported PHP extensions (%s) are available.', 'mysqli, mysql') . "</p>\n";
	page_footer("auth");
	exit;
}
