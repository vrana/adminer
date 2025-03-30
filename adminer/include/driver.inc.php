<?php
namespace Adminer;

/** Add or overwrite a driver */
function add_driver(string $id, string $name): void {
	SqlDriver::$drivers[$id] = $name;
}

/** Get driver name */
function get_driver(string $id): string {
	return SqlDriver::$drivers[$id];
}

abstract class SqlDriver {
	static Driver $instance;
	/** @var string[] */ static array $drivers = array(); // all available drivers
	/** @var list<string> */ static array $extensions = array(); // possible extensions in the current driver
	static string $jush; // JUSH identifier

	protected Db $conn;
	/** @var int[][] */ protected array $types = array(); // [$group => [$type => $maximum_unsigned_length, ...], ...]
	/** @var string[] */ public array $insertFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit and insert
	/** @var string[] */ public array $editFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit only
	/** @var list<string> */ public array $unsigned = array(); // number variants
	/** @var list<string> */ public array $operators = array(); // operators used in select
	/** @var list<string> */ public array $functions = array(); // functions used in select
	/** @var list<string> */ public array $grouping = array(); // grouping functions used in select
	public string $onActions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; // used in foreign_keys()
	public string $inout = "IN|OUT|INOUT"; // used in routines
	public string $enumLength = "'(?:''|[^'\\\\]|\\\\.)*'"; // regular expression for parsing enum lengths
	/** @var list<string> */ public array $generated = array(); // allowed types of generated columns

	/** Connect to the database
	* @return Db|string string for error
	*/
	static function connect(?string $server, string $username, string $password) {
		$connection = new Db;
		return ($connection->attach($server, $username, $password) ?: $connection);
	}

	/** Create object for performing database operations */
	function __construct(Db $connection) {
		$this->conn = $connection;
	}

	/** Get all types
	* @return int[] [$type => $maximum_unsigned_length, ...]
	*/
	function types(): array {
		return call_user_func_array('array_merge', array_values($this->types));
	}

	/** Get structured types
	* @return list<string>[]|list<string> [$description => [$type, ...], ...]
	*/
	function structuredTypes(): array {
		return array_map('array_keys', $this->types);
	}

	/** Get enum values
	* @param Field $field
	* @return string|void
	*/
	function enumLength(array $field) {
	}

	/** Function used to convert the value inputted by user
	* @param Field $field
	* @return string|void
	*/
	function unconvertFunction(array $field) {
	}

	/** Select data from table
	* @param list<string> $select result of adminer()->selectColumnsProcess()[0]
	* @param list<string> $where result of adminer()->selectSearchProcess()
	* @param list<string> $group result of adminer()->selectColumnsProcess()[1]
	* @param list<string> $order result of adminer()->selectOrderProcess()
	* @param int|numeric-string $limit result of adminer()->selectLimitProcess()
	* @param int $page index of page starting at zero
	* @param bool $print whether to print the query
	* @return Result|false
	*/
	function select(string $table, array $select, array $where, array $group, array $order = array(), $limit = 1, ?int $page = 0, bool $print = false) {
		$is_group = (count($group) < count($select));
		$query = adminer()->selectQueryBuild($select, $where, $group, $order, $limit, $page);
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
			echo adminer()->selectQuery($query, $start, !$return);
		}
		return $return;
	}

	/** Delete data from table
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function delete(string $table, string $queryWhere, int $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($table, $query, $queryWhere) : " $query$queryWhere"));
	}

	/** Update data in table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @param string $queryWhere " WHERE ..."
	* @param int $limit 0 or 1
	* @return Result|bool
	*/
	function update(string $table, array $set, string $queryWhere, int $limit = 0, string $separator = "\n") {
		$values = array();
		foreach ($set as $key => $val) {
			$values[] = "$key = $val";
		}
		$query = table($table) . " SET$separator" . implode(",$separator", $values);
		return queries("UPDATE" . ($limit ? limit1($table, $query, $queryWhere, $separator) : " $query$queryWhere"));
	}

	/** Insert data into table
	* @param string[] $set escaped columns in keys, quoted data in values
	* @return Result|bool
	*/
	function insert(string $table, array $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: " DEFAULT VALUES"
		) . $this->insertReturning($table));
	}

	/** Get RETURNING clause for INSERT queries (PostgreSQL specific) */
	function insertReturning(string $table): string {
		return "";
	}

	/** Insert or update data in table
	* @param list<string[]> $rows of arrays with escaped columns in keys and quoted data in values
	* @param int[] $primary column names in keys
	* @return Result|bool
	*/
	function insertUpdate(string $table, array $rows, array $primary) {
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
	* @param int $timeout seconds
	* @return string|void null if the driver doesn't support query timeouts
	*/
	function slowQuery(string $query, int $timeout) {
	}

	/** Convert column to be searchable
	* @param string $idf escaped column name
	* @param array{op:string, val:string} $val
	* @param Field $field
	*/
	function convertSearch(string $idf, array $val, array $field): string {
		return $idf;
	}

	/** Convert operator so it can be used in search */
	function convertOperator(string $operator): string {
		return $operator;
	}

	/** Convert value returned by database to actual value
	* @param Field $field
	*/
	function value(?string $val, array $field): ?string {
		return (method_exists($this->conn, 'value')
			? $this->conn->value($val, $field)
			: (is_resource($val) ? stream_get_contents($val) : $val)
		);
	}

	/** Quote binary string */
	function quoteBinary(string $s): string {
		return q($s);
	}

	/** Get warnings about the last command
	* @return string|void HTML
	*/
	function warnings() {
	}

	/** Get help link for table
	* @return string|void relative URL
	*/
	function tableHelp(string $name, bool $is_view = false) {
	}

	/** Check if C-style escapes are supported */
	function hasCStyleEscapes(): bool {
		return false;
	}

	/** Get supported engines
	* @return list<string>
	*/
	function engines(): array {
		return array();
	}

	/** Check whether table supports indexes
	* @param TableStatus $table_status
	*/
	function supportsIndex(array $table_status): bool {
		return !is_view($table_status);
	}

	/** Get defined check constraints
	* @return string[] [$name => $clause]
	*/
	function checkConstraints(string $table): array {
		// MariaDB contains CHECK_CONSTRAINTS.TABLE_NAME, MySQL and PostrgreSQL not
		return get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME
WHERE c.CONSTRAINT_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
AND t.TABLE_NAME = " . q($table) . "
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'"); // ignore default IS NOT NULL checks in PostrgreSQL
	}
}
