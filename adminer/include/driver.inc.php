<?php
namespace Adminer;

$drivers = array();

/** Add a driver
* @param string $id
* @param string $name
* @return void
*/
function add_driver($id, $name) {
	global $drivers;
	$drivers[$id] = $name;
}

/** Get driver name
* @param string $id
* @return string
*/
function get_driver($id) {
	global $drivers;
	return $drivers[$id];
}

abstract class SqlDriver {
	/** @var list<string> */ static $possibleDrivers = array();
	/** @var string */ static $jush; // JUSH identifier

	/** @var Db */ protected $conn;
	/** @var int[][] */ protected $types = array(); // [$group => [$type => $maximum_unsigned_length, ...], ...]
	/** @var array{0?:string[], 1?:string[]} */ public $editFunctions = array(); // of ["$type|$type2" => "$function/$function2"] functions used in editing, [0] - edit and insert, [1] - edit only
	/** @var list<string> */ public $unsigned = array(); // number variants
	/** @var list<string> */ public $operators = array(); // operators used in select
	/** @var list<string> */ public $functions = array(); // functions used in select
	/** @var list<string> */ public $grouping = array(); // grouping functions used in select
	/** @var string */ public $onActions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; // used in foreign_keys()
	/** @var string */ public $inout = "IN|OUT|INOUT"; // used in routines
	/** @var string */ public $enumLength = "'(?:''|[^'\\\\]|\\\\.)*'"; // regular expression for parsing enum lengths
	/** @var list<string> */ public $generated = array(); // allowed types of generated columns

	/** Create object for performing database operations
	* @param Db $connection
	*/
	function __construct($connection) {
		$this->conn = $connection;
	}

	/** Get all types
	* @return int[] [$type => $maximum_unsigned_length, ...]
	*/
	function types() {
		return call_user_func_array('array_merge', array_values($this->types));
	}

	/** Get structured types
	* @return list<string>[]|list<string> [$description => [$type, ...], ...]
	*/
	function structuredTypes() {
		return array_map('array_keys', $this->types);
	}

	/** Get enum values
	* @param Field $field
	* @return string|void
	*/
	function enumLength($field) {
	}

	/** Function used to convert the value inputted by user
	* @param Field $field
	* @return string|void
	*/
	function unconvertFunction($field) {
	}

	/** Select data from table
	* @param string $table
	* @param list<string> $select result of $adminer->selectColumnsProcess()[0]
	* @param list<string> $where result of $adminer->selectSearchProcess()
	* @param list<string> $group result of $adminer->selectColumnsProcess()[1]
	* @param list<string> $order result of $adminer->selectOrderProcess()
	* @param int|numeric-string $limit result of $adminer->selectLimitProcess()
	* @param int $page index of page starting at zero
	* @param bool $print whether to print the query
	* @return Result|false
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
	* @param string $table
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function delete($table, $queryWhere, $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($table, $query, $queryWhere) : " $query$queryWhere"));
	}

	/** Update data in table
	* @param string $table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @param string $separator
	* @return Result|bool
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
	* @param string $table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @return Result|bool
	*/
	function insert($table, $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: " DEFAULT VALUES"
		) . $this->insertReturning($table));
	}

	/** Get RETURNING clause for INSERT queries (PostgreSQL specific)
	* @param string $table
	* @return string
	*/
	function insertReturning($table) {
		return "";
	}

	/** Insert or update data in table
	* @param string $table
	* @param list<string[]> $rows of arrays with escaped columns in keys and quoted data in values
	* @param int[] $primary column names in keys
	* @return Result|bool
	*/
	function insertUpdate($table, $rows, $primary) {
		return false;
	}

	/** Begin transaction
	* @return Result|bool
	*/
	function begin() {
		return queries("BEGIN");
	}

	/** Commit transaction
	* @return Result|bool
	*/
	function commit() {
		return queries("COMMIT");
	}

	/** Rollback transaction
	* @return Result|bool
	*/
	function rollback() {
		return queries("ROLLBACK");
	}

	/** Return query with a timeout
	* @param string $query
	* @param int $timeout seconds
	* @return string|void null if the driver doesn't support query timeouts
	*/
	function slowQuery($query, $timeout) {
	}

	/** Convert column to be searchable
	* @param string $idf escaped column name
	* @param array{op:string, val:string} $val
	* @param Field $field
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
	* @param string $val
	* @param Field $field
	* @return string
	*/
	function value($val, $field) {
		return (method_exists($this->conn, 'value')
			? $this->conn->value($val, $field)
			: (is_resource($val) ? stream_get_contents($val) : $val)
		);
	}

	/** Quote binary string
	* @param string $s
	* @return string
	*/
	function quoteBinary($s) {
		return q($s);
	}

	/** Get warnings about the last command
	* @return string|void HTML
	*/
	function warnings() {
	}

	/** Get help link for table
	* @param string $name
	* @param bool $is_view
	* @return string|void relative URL
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
	* @return list<string>
	*/
	function engines() {
		return array();
	}

	/** Check whether table supports indexes
	* @param TableStatus $table_status result of table_status1()
	* @return bool
	*/
	function supportsIndex($table_status) {
		return !is_view($table_status);
	}

	/** Get defined check constraints
	* @param string $table
	* @return string[] [$name => $clause]
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
