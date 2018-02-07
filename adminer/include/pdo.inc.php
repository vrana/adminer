<?php
// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	/*abstract*/ class Min_PDO extends PDO {
		var $_result, $server_info, $affected_rows, $errno, $error;
		
		function __construct() {
			global $adminer;
			$pos = array_search("SQL", $adminer->operators);
			if ($pos !== false) {
				unset($adminer->operators[$pos]);
			}
		}
		
		function dsn($dsn, $username, $password, $options = array()) {
			try {
				parent::__construct($dsn, $username, $password, $options);
			} catch (Exception $ex) {
				auth_error(h($ex->getMessage()));
			}
			$this->setAttribute(13, array('Min_PDOStatement')); // 13 - PDO::ATTR_STATEMENT_CLASS
			$this->server_info = @$this->getAttribute(4); // 4 - PDO::ATTR_SERVER_VERSION
		}
		
		/*abstract function select_db($database);*/
		
		function query($query, $unbuffered = false) {
			$result = parent::query($query);
			$this->error = "";
			if (!$result) {
				list(, $this->errno, $this->error) = $this->errorInfo();
				return false;
			}
			$this->store_result($result);
			return $result;
		}
		
		function multi_query($query) {
			return $this->_result = $this->query($query);
		}
		
		function store_result($result = null) {
			if (!$result) {
				$result = $this->_result;
				if (!$result) {
					return false;
				}
			}
			if ($result->columnCount()) {
				$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
				return $result;
			}
			$this->affected_rows = $result->rowCount();
			return true;
		}
		
		function next_result() {
			if (!$this->_result) {
				return false;
			}
			$this->_result->_offset = 0;
			return @$this->_result->nextRowset(); // @ - PDO_PgSQL doesn't support it
		}
		
		function result($query, $field = 0) {
			$result = $this->query($query);
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
			$row->charsetnr = (in_array("blob", (array) $row->flags) ? 63 : 0);
			return $row;
		}
	}
}

$drivers = array();
