<?php
if (extension_loaded("mysqli")) {
	class Min_MySQLi extends MySQLi {
		function mysqli_result($result, $row, $field) {
			mysqli_data_seek($result, $row);
			$row = mysql_fetch_assoc($result);
			return $row[$field];
		}
	}
	$mysql = mysqli_init();
} elseif (extension_loaded("mysql")) {
	class Min_MySQL {
		var $_link;
		function real_connect($server, $username, $password) { return $this->_link = mysql_connect($server, $username, $password, false, 131072); }
		function query($query) { return new Min_MySQLResult(mysql_query($query, $this->_link)); }
		function result($result, $row, $field = 0) { return mysql_result($result->_result, $row, $field); }
		function error() { return mysql_error($this->_link); }
		function affected_rows() { return mysql_affected_rows($this->_link); }
		function select_db($database) { return mysql_select_db($database, $this->_link); }
		function real_escape_string($string) { return mysql_real_escape_string($string, $this->_link); }
		function get_server_info() { return mysql_get_server_info($this->_link); }
		
		function fetch_field($result, $offset = null) {
			$row = mysql_fetch_field($result, $offset);
			$row->orgtable = $row->table;
			$row->orgname = $row->name;
			$row->charsetnr = ($row->blob ? 63 : 0);
			return $row;
		}
	}
	class Min_MySQLResult {
		var $_result;
		function Min_MySQLResult($result) { $this->_result = $result; }
		function fetch_assoc() { return mysql_fetch_assoc($this->_result); }
		function fetch_row() { return mysql_fetch_row($this->_result); }
		function free_result() { return mysql_free_result($this->_result); }
		function num_rows() { return mysql_num_rows($this->_result); }
	}
	$mysql = new Min_MySQL;
} else {
	
}
