<?php
namespace Adminer;

$drivers = array();

/** Add a driver
* @param string
* @param string
* @return null
*/
function add_driver($id, $name) {
	global $drivers;
	$drivers[$id] = $name;
}

/** Get driver name
* @param string
* @return string
*/
function get_driver($id) {
	global $drivers;
	return $drivers[$id];
}

abstract class SqlDriver {
	static $possibleDrivers = array();
	static $jush; ///< @var string JUSH identifier

	protected $conn;
	protected $types = array(); ///< @var array [$description => [$type => $maximum_unsigned_length, ...], ...]
	public $editFunctions = array(); ///< @var array of ["$type|$type2" => "$function/$function2"] functions used in editing, [0] - edit and insert, [1] - edit only
	public $unsigned = array(); ///< @var array number variants
	public $operators = array(); ///< @var array operators used in select
	public $functions = array(); ///< @var array functions used in select
	public $grouping = array(); ///< @var array grouping functions used in select
	public $onActions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; ///< @var string used in foreign_keys()
	public $inout = "IN|OUT|INOUT"; ///< @var string used in routines
	public $enumLength = "'(?:''|[^'\\\\]|\\\\.)*'"; ///< @var string regular expression for parsing enum lengths
	public $generated = array(); ///< @var array allowed types of generated columns

	/** Create object for performing database operations
	* @param Db
	*/
	function __construct($connection) {
		$this->conn = $connection;
	}

	/** Get all types
	* @return array [$type => $maximum_unsigned_length, ...]
	*/
	function types() {
		return call_user_func_array('array_merge', array_values($this->types));
	}

	/** Get structured types
	* @return array [$description => [$type, ...], ...]
	*/
	function structuredTypes() {
		return array_map('array_keys', $this->types);
	}

	/** Get enum values
	* @param array
	* @return string or null
	*/
	function enumLength($field) {
	}

	/** Function used to convert the value inputted by user
	* @param array
	* @return string or null
	*/
	function unconvertFunction($field) {
	}

	/** Select data from table
	* @param string
	* @param array result of $adminer->selectColumnsProcess()[0]
	* @param array result of $adminer->selectSearchProcess()
	* @param array result of $adminer->selectColumnsProcess()[1]
	* @param array result of $adminer->selectOrderProcess()
	* @param int result of $adminer->selectLimitProcess()
	* @param int index of page starting at zero
	* @param bool whether to print the query
	* @return Result
	*/
	function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
		global $adminer;
		$is_group = (count($group) < count($select));
		$query = $adminer->selectQueryBuild($select, $where, $group, $order, $limit, $page);
		if (!$query) {
			$query = "SELECT" . limit(
				($_GET["page"] != "last" && $limit != "" && $group && $is_group && JUSH == "sql" ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) . "\nFROM " . table($table),
				($where ? "\nWHERE " . implode(" AND ", $where) : "") . ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : ""),
				($limit != "" ? +$limit : null),
				($page ? $limit * $page : 0),
				"\n"
			);
		}
		$start = microtime(true);
		$return = $this->conn->query($query);
		if ($print) {
			echo $adminer->selectQuery($query, $start, !$return);
		}
		return $return;
	}

	/** Delete data from table
	* @param string
	* @param string " WHERE ..."
	* @param int 0 or 1
	* @return bool
	*/
	function delete($table, $queryWhere, $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($table, $query, $queryWhere) : " $query$queryWhere"));
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
		return queries("UPDATE" . ($limit ? limit1($table, $query, $queryWhere, $separator) : " $query$queryWhere"));
	}

	/** Insert data into table
	* @param string
	* @param array escaped columns in keys, quoted data in values
	* @return bool
	*/
	function insert($table, $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: " DEFAULT VALUES"
		) . $this->insertReturning($table));
	}

	/** Get RETURNING clause for INSERT queries, PostgreSQL specific
	* @param string
	* @return string
	*/
	function insertReturning($table) {
		return "";
	}

	/** Insert or update data in table
	* @param string
	* @param array
	* @param array of arrays with escaped columns in keys and quoted data in values
	* @return bool
	*/
	function insertUpdate($table, $rows, $primary) {
		return false;
	}

	/** Begin transaction
	* @return bool
	*/
	function begin() {
		return queries("BEGIN");
	}

	/** Commit transaction
	* @return bool
	*/
	function commit() {
		return queries("COMMIT");
	}

	/** Rollback transaction
	* @return bool
	*/
	function rollback() {
		return queries("ROLLBACK");
	}

	/** Return query with a timeout
	* @param string
	* @param int seconds
	* @return string or null if the driver doesn't support query timeouts
	*/
	function slowQuery($query, $timeout) {
	}

	/** Convert column to be searchable
	* @param string escaped column name
	* @param array ["op" => , "val" => ]
	* @param array
	* @return string
	*/
	function convertSearch($idf, $val, $field) {
		return $idf;
	}

	/** Convert operator so it can be used in search
	* @param string $operator
	* @return string
	*/
	function convertOperator($operator) {
		return $operator;
	}

	/** Convert value returned by database to actual value
	* @param string
	* @param array
	* @return string
	*/
	function value($val, $field) {
		return (method_exists($this->conn, 'value')
			? $this->conn->value($val, $field)
			: (is_resource($val) ? stream_get_contents($val) : $val)
		);
	}

	/** Quote binary string
	* @param string
	* @return string
	*/
	function quoteBinary($s) {
		return q($s);
	}

	/** Get warnings about the last command
	* @return string HTML
	*/
	function warnings() {
		return '';
	}

	/** Get help link for table
	* @param string
	* @param bool
	* @return string relative URL or null
	*/
	function tableHelp($name, $is_view = false) {
	}

	/** Check if C-style escapes are supported
	* @return bool
	*/
	function hasCStyleEscapes() {
		return false;
	}

	/** Get supported engines
	* @return array
	*/
	function engines() {
		return array();
	}

	/** Check whether table supports indexes
	* @param array result of table_status()
	* @return bool
	*/
	function supportsIndex($table_status) {
		return !is_view($table_status);
	}

	/** Get defined check constraints
	* @param string
	* @return array [$name => $clause]
	*/
	function checkConstraints($table) {
		// MariaDB contains CHECK_CONSTRAINTS.TABLE_NAME, MySQL and PostrgreSQL not
		return get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME
WHERE c.CONSTRAINT_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
AND t.TABLE_NAME = " . q($table) . "
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'"); // ignore default IS NOT NULL checks in PostrgreSQL
	}
}
