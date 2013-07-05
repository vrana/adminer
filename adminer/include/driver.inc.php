<?php

/*abstract*/ class Min_SQL {
	var $_conn;
	
	/** Create object for performing database operations
	* @param Min_DB
	*/
	function Min_SQL($connection) {
		$this->_conn = $connection;
	}
	
	/** Delete data from table
	* @param string
	* @param string " WHERE ..."
	* @param int 0 or 1
	* @return bool
	*/
	function delete($table, $queryWhere, $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($query, $queryWhere) : " $query$queryWhere"));
	}
	
	/** Update data in table
	* @param string
	* @param array
	* @param string " WHERE ..."
	* @param int 0 or 1
	* @return bool
	*/
	function update($table, $set, $queryWhere, $limit = 0) {
		$query = table($table) . " SET" . implode(",", $set);
		return queries("UPDATE" . ($limit ? limit1($query, $queryWhere) : " $query$queryWhere"));
	}
	
	/** Insert data into table
	* @param string
	* @param array
	* @return bool
	*/
	function insert($table, $set) {
		return queries("INSERT INTO " . table($table) . ($set ? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")" : "DEFAULT VALUES"));
	}
	
	/** Insert or update data in table
	* @param string
	* @param array
	* @param array columns in keys
	* @return bool
	*/
	/*abstract*/ function insertUpdate($table, $set, $primary) {
		return false;
	}
	
}
