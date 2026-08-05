<?php
namespace Adminer;

/** Add or overwrite a driver */
function add_driver(string $id, string $name): void {
	SqlDriver::$drivers[$id] = $name;
}

/** Get driver name */
function get_driver(string $id): ?string {
	return SqlDriver::$drivers[$id];
}

abstract class SqlDriver {
	/** @var Driver */ static $instance;
	/** @var string[] */ static $drivers = array(); // all available drivers
	/** @var list<string> */ static $extensions = array(); // possible extensions in the current driver
	/** @var string */ static $jush; // JUSH identifier

	/** @var Db */ protected $conn;
	/** @var int[][] */ protected $types = array(); // [$group => [$type => $maximum_unsigned_length, ...], ...]
	/** @var string */ public $delimiter = ";"; // string separating queries, it is used also as a regular expression
	/** @var string[] */ public $insertFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit and insert
	/** @var string[] */ public $editFunctions = array(); // ["$type|$type2" => "$function/$function2"] functions used in edit only
	/** @var list<string> */ public $unsigned = array(); // number variants
	/** @var list<string> */ public $operators = array(); // operators used in select
	/** @var list<string> */ public $functions = array(); // functions used in select
	/** @var list<string> */ public $grouping = array(); // grouping functions used in select
	/** @var string */ public $onActions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; // used in foreign_keys()
	/** @var list<string> */ public $partitionBy = array(); // supported partitioning types
	/** @var string */ public $inout = "IN|OUT|INOUT"; // used in routines
	/** @var string */ public $enumLength = "'(?:''|[^'\\\\]|\\\\.)*'"; // regular expression for parsing enum lengths
	/** @var list<string> */ public $generated = array(); // allowed types of generated columns

	/** Connect to the database
	* @return Db|string string for error
	*/
	static function connect(string $server, string $username, string $password) {
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
	* @param int $limit result of adminer()->selectLimitProcess()
	* @param int $page index of page starting at zero
	* @param bool $print whether to print the query
	* @return Result|bool
	* @uses $_GET["next"] drivers with support("cursor") ignore $page, they get the cursor of the selected page here and set it to the cursor of the following page, "" if there is none
	*/
	function select(string $table, array $select, array $where, array $group, array $order = array(), int $limit = 1, ?int $page = 0, bool $print = false) {
		$is_group = (count($group) < count($select));
		$query = adminer()->selectQueryBuild($select, $where, $group, $order, $limit, $page);
		if (!$query) {
			$query = "SELECT" . limit(
				($_GET["page"] != "last" && $limit && $group && $is_group && JUSH == "sql" ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) . "\nFROM " . table($table),
				($where ? "\nWHERE " . implode(" AND ", $where) : "") . ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : ""),
				$limit,
				($page ? $limit * $page : 0),
				"\n"
			);
		}
		$start = microtime(true);
		// export gets all rows and doesn't print the query so nothing runs another query (e.g. SHOW WARNINGS) before fetching them
		$return = $this->conn->query($query, (!$limit && !$print ? 1 : 0)); // 1 - MYSQLI_USE_RESULT, unbuffered
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
		// drivers supporting an atomic upsert override this, the others update the row and insert it if nothing was updated
		foreach ($rows as $set) {
			$where = array();
			foreach ($set as $key => $val) {
				if (isset($primary[idf_unescape($key)])) {
					$where[] = "$key = $val";
				}
			}
			if (
				!($where && $this->update($table, $set, " WHERE " . implode(" AND ", $where)) && $this->conn->affected_rows)
				&& !$this->insert($table, $set)
			) {
				return false;
			}
		}
		return true;
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

	/** Convert value returned by database to actual value
	* @param array{type: string} $field
	*/
	function value(?string $val, array $field): ?string {
		return (method_exists($this->conn, 'value') ? $this->conn->value($val, $field) : $val);
	}

	/** Quote binary string */
	function quoteBinary(string $s): string {
		return q($s);
	}

	/** Get type name of a result column
	* @param \stdClass $field result of Result::fetch_field()
	* @return string "" if unknown
	*/
	function typeName(\stdClass $field): string {
		return (isset($field->native_type) ? $field->native_type : "");
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

	/** Get tables this table inherits from
	* @return list<array{table: string, ns: string}>
	*/
	function inheritsFrom(string $table): array {
		return array();
	}

	/** Get inherited tables
	* @return list<array{table: string, ns: string}>
	*/
	function inheritedTables(string $table): array {
		return array();
	}

	/** Get partitions info
	* @return Partitions
	*/
	function partitionsInfo(string $table): array {
		return array();
	}

	/** Check if C-style escapes are supported */
	function hasCStyleEscapes(): bool {
		return false;
	}

	/** Get regular expression matching the start of a line comment; must not match an empty string */
	function lineComment(): string {
		return "--";
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

	/** Check whether table indexes can be altered
	* @param TableStatus $table_status
	*/
	function supportsAlterIndex(array $table_status): bool {
		return true;
	}

	/** Return list of supported index algorithms, first one is default
	 * @param TableStatus $tableStatus
	 * @return list<string>
	 */
	function indexAlgorithms(array $tableStatus): array {
		return array();
	}

	/** Get operator classes usable in indexes
	* @return list<string>
	*/
	function indexOpclasses(): array {
		return array();
	}

	/** Get defined check constraints
	* @return string[] [$name => $clause]
	*/
	function checkConstraints(string $table): array {
		// MariaDB contains CHECK_CONSTRAINTS.TABLE_NAME, MySQL and PostrgreSQL not
		return get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
	ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME" . ($this->conn->flavor == 'maria' ? " AND c.TABLE_NAME = " . q($table) : "") . "
WHERE c.CONSTRAINT_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
AND t.TABLE_NAME = " . q($table) . (JUSH == "pgsql" ? "
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'" : ""), $this->conn); // ignore default IS NOT NULL checks in PostrgreSQL
	}

	/** Get all fields in the current schema
	* @return array<list<array{field:string, null:bool, type:string, length:?numeric-string}>> optionally also 'primary'
	*/
	function allFields(): array {
		$return = array();
		if (DB != "") {
			// 'primary' is the column name or null because PostgreSQL returns booleans as 't' and 'f' (both truthy) and MS SQL doesn't support them in SELECT
			foreach (
				get_rows("SELECT c.TABLE_NAME AS tab, c.COLUMN_NAME AS field, c.IS_NULLABLE AS nullable,
	c.DATA_TYPE AS type, c.CHARACTER_MAXIMUM_LENGTH AS length,
	" . (JUSH == 'sql' ? "c.COLUMN_KEY = 'PRI'" : "k.COLUMN_NAME") . " AS " . idf_escape("primary") . "
FROM INFORMATION_SCHEMA.COLUMNS c" . (JUSH == 'sql' ? "" : "
LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
	ON t.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME") . "
WHERE c.TABLE_SCHEMA = " . q($_GET["ns"] != "" ? $_GET["ns"] : DB) . "
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION", $this->conn) as $row
			) {
				$row["null"] = ($row["nullable"] == "YES");
				$return[$row["tab"]][] = $row;
			}
		}
		return $return;
	}
}
