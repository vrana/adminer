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
	* @param array escaped columns in keys, quoted data in values
	* @param string " WHERE ..."
	* @param int 0 or 1
	* @param string
	* @return bool
	*/
	function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
		$values = array();
		foreach ($set as $key => $val) {
			$values[] = "$key = $val";
		}
		$query = table($table) . " SET$separator" . implode(",$separator", $values);
		return queries("UPDATE" . ($limit ? limit1($query, $queryWhere) : " $query$queryWhere"));
	}
	
	/** Insert data into table
	* @param string
	* @param array escaped columns in keys, quoted data in values
	* @return bool
	*/
	function insert($table, $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: "DEFAULT VALUES"
		));
	}
	
	/** Insert or update data in table
	* @param string
	* @param array
	* @param array of arrays with escaped columns in keys and quoted data in values
	* @return bool
	*/
	/*abstract*/ function insertUpdate($table, $rows, $primary) {
		return false;
	}
	
}
