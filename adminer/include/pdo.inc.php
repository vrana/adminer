<?php
// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	class Min_PDO extends PDO {
		var $_result, $server_info, $affected_rows, $error;
		
		function __construct() {
		}
		
		function dsn($dsn, $username, $password) {
			set_exception_handler('auth_error'); // try/catch is not compatible with PHP 4
			parent::__construct($dsn, $username, $password);
			restore_exception_handler();
			$this->setAttribute(13, array('Min_PDOStatement')); // PDO::ATTR_STATEMENT_CLASS
		}
		
		function select_db($database) {
			// database selection is separated from the connection so dbname in DSN can't be used
			return $this->query("USE " . idf_escape($database));
		}
		
		function query($query, $unbuffered = false) {
			$return = parent::query($query);
			if (!$return) {
				$errorInfo = $this->errorInfo();
				$this->error = $errorInfo[2];
				return false;
			}
			if ($return->columnCount()) {
				$this->affected_rows = $return->rowCount();
			} else {
				$return->num_rows = $return->rowCount(); // is not guaranteed to work with all drivers
			}
			return $return;
		}
		
		function multi_query($query) {
			return $this->_result = $this->query($query);
		}
		
		function store_result() {
			$return = &$this->_result;
			if ($return->columnCount()) {
				$this->affected_rows = $return->rowCount();
				return true;
			}
			$return->num_rows = $return->rowCount();
			return $return;
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
	}
}
