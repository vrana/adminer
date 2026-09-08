<?php
namespace Adminer;

add_driver("oracle", "Oracle");

if (isset($_GET["oracle"])) {
	define('Adminer\DRIVER', "oracle");

	/** Join the server name to the Easy Connect syntax: host:port/service
	* @param Server $server
	*/
	function easy_connect(array $server): string {
		return url_host($server["host"]) . ($server["port"] != "" ? ":$server[port]" : "") . $server["path"];
	}

	if (extension_loaded("oci8") && $_GET["ext"] != "pdo") {
		class Db extends SqlDb {
			public $extension = "oci8";
			private $link;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function attach(array $server, string $username, string $password): string {
				$this->link = @oci_new_connect($username, $password, easy_connect($server), "AL32UTF8");
				if ($this->link) {
					$this->server_info = oci_server_version($this->link);
					return '';
				}
				$error = oci_error();
				return ($error ? $error["message"] : lang('Unknown error.')); // oci_error() returns false if the client library is not set up
			}

			function quote(string $string): string {
				return "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db(string $database) {
				return $this->query("ALTER SESSION SET CURRENT_SCHEMA = " . idf_escape($database));
			}

			function query(string $query, bool $unbuffered = false) {
				$result = oci_parse($this->link, $query);
				$this->error = "";
				if (!$result) {
					$error = oci_error($this->link);
					$this->errno = $error["code"];
					$this->error = $error["message"];
					return false;
				}
				set_error_handler(array($this, '_error'));
				$return = @oci_execute($result);
				restore_error_handler();
				if ($return) {
					if (oci_num_fields($result)) {
						return new Result($result);
					}
					$this->affected_rows = oci_num_rows($result);
					oci_free_statement($result);
				}
				return $return;
			}

			function timeout(int $ms): bool {
				return function_exists('oci_set_call_timeout') && oci_set_call_timeout($this->link, $ms); // available since PHP 7.2.13
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 1;

			function __construct($result) {
				$this->result = $result;
			}

			private function convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'OCILob') || is_a($val, 'OCI-Lob')) {
						$row[$key] = $val->load();
					}
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->convert(oci_fetch_assoc($this->result));
			}

			function fetch_row() {
				return $this->convert(oci_fetch_row($this->result));
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$return = new \stdClass;
				$return->name = oci_field_name($this->result, $column);
				$type = oci_field_type($this->result, $column);
				$return->native_type = $type;
				$return->type = $type; //! map to MySQL numbers
				$return->charsetnr = (preg_match("~raw|blob|bfile~", $type) ? 63 : 0); // 63 - binary
				return $return;
			}
		}

	} elseif (extension_loaded("pdo_oci")) {
		class Db extends PdoDb {
			public $extension = "PDO_OCI";

			function attach(array $server, string $username, string $password): string {
				return $this->dsn("oci:dbname=//" . easy_connect($server) . ";charset=AL32UTF8", $username, $password);
			}

			function select_db(string $database) {
				return $this->query("ALTER SESSION SET CURRENT_SCHEMA = " . idf_escape($database));
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("OCI8", "PDO_OCI");
		static $jush = "oracle";

		static $serverPath = true; // the service name in the Easy Connect syntax

		public $insertFunctions = array( //! no parentheses
			"date" => "current_date",
			"timestamp" => "current_timestamp",
		);
		public $editFunctions = array(
			"number|float|double" => "+/-",
			"date|timestamp" => "+ interval/- interval",
			"char|clob" => "||",
		);

		public $functions = array("length", "lower", "round", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		function operators(?array $tableStatus): array {
			return array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL");
		}

		static function connect(string $server, string $username, string $password) {
			$connection = parent::connect($server, $username, $password);
			if (is_object($connection)) {
				$connection->query("ALTER SESSION SET CURSOR_SHARING = FORCE" // convert the literals to binds so that a dictionary query compiled for one table is reused for the others
					. " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'" // the ISO date and time formats sent by the edit form and import
					. " NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS.FF'"
					. " NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS.FF TZH:TZM'");
			}
			return $connection;
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array(
				lang('Numbers') => array("number" => 38, "binary_float" => 12, "binary_double" => 21),
				lang('Date and time') => array("date" => 10, "timestamp" => 29, "interval year" => 12, "interval day" => 28), //! year(), day() to second()
				lang('Strings') => array("char" => 2000, "varchar2" => 4000, "nchar" => 2000, "nvarchar2" => 4000, "clob" => 4294967295, "nclob" => 4294967295),
				lang('Binary') => array("raw" => 2000, "long raw" => 2147483648, "blob" => 4294967295, "bfile" => 4294967296),
				lang('Geometry') => array("sdo_geometry" => 0),
			);
		}

		//! support empty $set in insert()

		function begin() {
			return true; // automatic start
		}

		function convertSearch(string $idf, array $val, array $field): string {
			$type = $field["type"];
			$pattern = strpos($val["op"], "LIKE") !== false;
			if ($type == "xmltype") {
				return "XMLSERIALIZE(CONTENT $idf AS VARCHAR2(4000))";
			}
			if ($type == "json") {
				return "JSON_SERIALIZE($idf)"; // TO_CHAR() doesn't accept JSON
			}
			if (preg_match('~^(date|timestamp)~', $type)) {
				return "TO_CHAR($idf, 'YYYY-MM-DD HH24:MI:SS')"; // the session format is not the searched one
			}
			if (preg_match('~char~', $type) || (preg_match('~clob~', $type) && $pattern)) {
				return $idf; // LIKE works on a LOB, the comparison operators don't
			}
			return (!$pattern && preg_match(number_type(), $type) ? $idf : "TO_CHAR($idf)");
		}

		function quoteBinary(string $s): string {
			return "HEXTORAW(" . q(bin2hex($s)) . ")"; //! the literal is limited to 4000 characters
		}

		function hasCStyleEscapes(): bool {
			return true;
		}

		function select(string $table, array $select, array $where, array $group, array $order = array(), int $limit = 1, ?int $page = 0, bool $print = false) {
			// fetching a row with a raw object column fails (ORA-00932) so unlike in MySQL, the conversions appended next to * don't help - * must be expanded to convert the objects in SQL
			if (in_array("*", $select)) {
				$convert = array();
				$found = false;
				foreach (fields($table) as $name => $field) {
					$as = convert_field($field);
					$found = ($found || $as);
					$convert[] = ($as ? "$as AS " : "") . idf_escape($name);
				}
				if ($found) {
					$select = $convert; // also drops the appended conversions, their aliases would collide in the subquery added by limit()
				}
			}
			return parent::select($table, $select, $where, $group, $order, $limit, $page, $print);
		}

		function allFields(): array {
			$return = array();
			// no filter to tables and views - it only excludes rare clusters and slows the query down threefold
			$rows = get_rows('SELECT c.table_name "tab", c.column_name "field", c.data_type "type", c.nullable "nullable",
	c.data_precision "precision", c.data_scale "scale", c.char_col_decl_length "char_length"
FROM all_tab_columns c
WHERE ' . where_owner("c.owner") . '
ORDER BY c.table_name, c.column_id', $this->conn);
			foreach ($rows as $row) {
				$length = "$row[precision],$row[scale]";
				$row["length"] = ($length == "," ? $row["char_length"] : $length); //! int
				$row["type"] = strtolower($row["type"]);
				$row["null"] = ($row["nullable"] == "Y");
				$return[$row["tab"]][] = $row;
			}
			return $return;
		}
	}



	function idf_escape(string $idf): string {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table(string $idf): string {
		return idf_escape($idf);
	}

	function get_databases(bool $flush): array {
		// oracle_maintained skips the internal schemas, the column is available since Oracle 12.1
		$return = get_vals("SELECT username FROM all_users WHERE oracle_maintained = 'N' ORDER BY 1");
		return ($return ?: get_vals("SELECT username FROM all_users ORDER BY 1"));
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return ($offset ? " * FROM (SELECT t.*, rownum AS rnum FROM (SELECT $query$where) t WHERE rownum <= " . ($limit + $offset) . ") WHERE rnum > $offset"
			: ($limit ? " * FROM (SELECT $query$where) WHERE rownum <= " . ($limit + $offset)
			: " $query$where"
		));
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return " $query$where"; //! limit
	}

	function db_collation(string $db, array $collations): string {
		return get_val("SELECT value FROM nls_database_parameters WHERE parameter = 'NLS_CHARACTERSET'"); // the character set is common to all schemas
	}

	function logged_user(): string {
		return get_val("SELECT USER FROM DUAL");
	}

	function where_owner(string $owner = "owner"): string {
		return "$owner = " . q(DB); // an empty DB matches no object
	}

	function views_table(string $columns): string {
		return "(SELECT $columns FROM all_views WHERE " . where_owner() . ")";
	}

	// all_objects is much faster than all_tables, which digs into segments and statistics (hundreds of ms on an idle XE)
	// a materialized view has a container table of the same name so including 'TABLE' and 'VIEW' matches all_tables UNION all_views
	function objects_table(): string {
		return "(SELECT object_name, DECODE(object_type, 'VIEW', 'view', 'table') object_type FROM all_objects WHERE " . where_owner() . " AND object_type IN ('TABLE', 'VIEW'))";
	}

	function tables_list(): array {
		return get_key_vals("SELECT * FROM " . objects_table() . " ORDER BY 1");
	}

	function count_tables(array $databases): array {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = get_val("SELECT COUNT(*) FROM all_objects WHERE object_type IN ('TABLE', 'VIEW') AND owner = " . q($db));
		}
		return $return;
	}

	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		$search = q($name);
		if ($fast || $name != "") {
			// sizes and rows are displayed only in the list of all tables; compiling the user_segments query costs ~750 ms after an instance restart
			foreach (get_rows('SELECT object_name "Name", object_type "Engine" FROM ' . objects_table() . ($name != "" ? " WHERE object_name = $search" : "") . ' ORDER BY 1') as $row) {
				$return[$row["Name"]] = $row;
			}
			return $return;
		}
		foreach (
			// sizes are available only for segments of the current user
			get_rows('SELECT t.table_name "Name", \'table\' "Engine", s.bytes "Data_length", i.bytes "Index_length", t.num_rows "Rows"
FROM all_tables t
LEFT JOIN (SELECT segment_name, SUM(bytes) bytes FROM user_segments WHERE segment_type LIKE \'TABLE%\' GROUP BY segment_name) s ON s.segment_name = t.table_name
LEFT JOIN (SELECT i.table_name, SUM(s.bytes) bytes FROM user_indexes i
	JOIN user_segments s ON s.segment_name = i.index_name AND s.segment_type LIKE \'INDEX%\' GROUP BY i.table_name) i ON i.table_name = t.table_name
WHERE ' . where_owner("t.owner") . "
UNION SELECT view_name, 'view', 0, 0, 0 FROM " . views_table("view_name") . "
ORDER BY 1") as $row
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view(array $table_status): bool {
		return $table_status["Engine"] == "view";
	}

	function fk_support(array $table_status): bool {
		return true;
	}

	function fields(string $table): array {
		$return = array();
		foreach (get_rows("SELECT * FROM all_tab_columns WHERE table_name = " . q($table) . " AND " . where_owner() . " ORDER BY column_id") as $row) {
			$type = $row["DATA_TYPE"];
			$length = "$row[DATA_PRECISION],$row[DATA_SCALE]";
			if ($length == ",") {
				$length = $row["CHAR_COL_DECL_LENGTH"];
			} //! int
			$default = $row["DATA_DEFAULT"];
			if ($default !== null) {
				$default = rtrim($default); // Oracle pads the default by a space
				if (preg_match("~^'(.*)'\$~s", $default, $match)) {
					$default = str_replace("''", "'", $match[1]); // a string is stored as a quoted literal
				}
			}
			$privileges = array("insert" => 1, "select" => 1, "update" => 1, "order" => 1);
			if ($row["DATA_TYPE_OWNER"] == "" || $type == "XMLTYPE") { // an object type, e.g. SDO_GEOMETRY, can't be compared with a string
				$privileges["where"] = 1;
			}
			$return[$row["COLUMN_NAME"]] = array(
				"field" => $row["COLUMN_NAME"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => strtolower($type),
				"length" => $length,
				"default" => $default,
				"null" => ($row["NULLABLE"] == "Y"),
				//! "auto_increment" => false,
				//! "collation" => $row["CHARACTER_SET_NAME"],
				"privileges" => $privileges,
				//! "comment" => $row["Comment"],
				//! "primary" => ($row["Key"] == "PRI"),
			);
		}
		return $return;
	}

	/** Get the primary, unique and referential constraints of the table with their columns in position order
	* @return array<string, array{type: string, columns: list<string>, r_owner: string, r_constraint: string, delete_rule: string}>
	*/
	function table_constraints(string $table, ?Db $connection2 = null): array {
		// one statement shape for both indexes() and foreign_keys() - every statement over all_constraints takes ~0.5 s to compile after a restart of the server
		$return = array();
		foreach (
			get_rows('SELECT c.constraint_name "name", c.constraint_type "type", c.r_owner "r_owner", c.r_constraint_name "r_constraint", c.delete_rule "delete_rule", cc.column_name "column"
FROM all_constraints c
JOIN all_cons_columns cc ON cc.owner = c.owner AND cc.constraint_name = c.constraint_name
WHERE c.constraint_type IN (\'P\', \'U\', \'R\') AND ' . where_owner("c.owner") . " AND c.table_name = " . q($table) . '
ORDER BY cc.position', $connection2) as $row
		) {
			$name = $row["name"];
			$return[$name]["type"] = $row["type"];
			$return[$name]["r_owner"] = $row["r_owner"];
			$return[$name]["r_constraint"] = $row["r_constraint"];
			$return[$name]["delete_rule"] = $row["delete_rule"];
			$return[$name]["columns"][] = $row["column"];
		}
		return $return;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		$return = array();
		$constraints = array();
		foreach (table_constraints($table, $connection2) as $name => $constraint) {
			$constraints[$name] = $constraint["type"];
		}
		foreach (
			get_rows("SELECT aic.*, atc.data_default
FROM all_ind_columns aic
LEFT JOIN all_tab_cols atc ON aic.column_name = atc.column_name AND aic.table_name = atc.table_name AND aic.index_owner = atc.owner
WHERE aic.table_name = " . q($table) . " AND " . where_owner("aic.table_owner") . "
ORDER BY aic.column_position", $connection2) as $row
		) {
			$index_name = $row["INDEX_NAME"];
			$column_name = $row["DATA_DEFAULT"];
			$column_name = ($column_name ? trim($column_name, '"') : $row["COLUMN_NAME"]); // trim - possibly wrapped in quotes but never contains quotes inside
			$type = idx($constraints, $index_name);
			$return[$index_name]["type"] = ($type == "P" ? "PRIMARY" : ($type == "U" ? "UNIQUE" : "INDEX"));
			$return[$index_name]["columns"][] = $column_name;
			$return[$index_name]["lengths"][] = ($row["CHAR_LENGTH"] && $row["CHAR_LENGTH"] != $row["COLUMN_LENGTH"] ? $row["CHAR_LENGTH"] : null);
			$return[$index_name]["descs"][] = ($row["DESCEND"] && $row["DESCEND"] == "DESC" ? '1' : null);
		}
		uasort($return, function ($a, $b) {
			// the constraint indexes first as the removed ORDER BY ac.constraint_type did, the callers pick the first usable index
			$order = array("PRIMARY" => 0, "UNIQUE" => 1, "INDEX" => 2);
			return $order[$a["type"]] - $order[$b["type"]];
		});
		return $return;
	}

	function view(string $name): array {
		$rows = get_rows('SELECT text "select" FROM ' . views_table("view_name, text") . ' WHERE view_name = ' . q($name));
		return reset($rows);
	}

	function collations(): array {
		return array(); //!
	}

	function information_schema(string $db, string $schema = ""): bool {
		return ($schema != "" ? $schema : $db) == "INFORMATION_SCHEMA"; //! SYS and SYSTEM are read-only too
	}

	function error(): string {
		return h(connection()->error); //! highlight sqltext from offset
	}

	function explain(Db $connection, string $query) {
		$connection->query("EXPLAIN PLAN FOR $query");
		return $connection->query("SELECT * FROM plan_table");
	}

	function found_rows(array $table_status, array $where) {
	}

	function auto_increment(): string {
		return "";
	}

	function alter_table(string $table, string $name, array $fields, array $foreign, ?string $comment, string $engine, string $collation, string $auto_increment, ?array $partitioning) {
		$alter = $drop = array();
		$orig_fields = ($table ? fields($table) : array());
		foreach ($fields as $field) {
			$val = $field[1];
			if ($val && $field[0] != "" && idf_escape($field[0]) != $val[0]) {
				queries("ALTER TABLE " . table($table) . " RENAME COLUMN " . idf_escape($field[0]) . " TO $val[0]");
			}
			$orig_field = $orig_fields[$field[0]];
			if ($val && $orig_field) {
				$old = process_field($orig_field, $orig_field);
				if ($val[2] == $old[2]) {
					$val[2] = "";
				}
			}
			if ($val) {
				list($val[2], $val[3]) = array($val[3], $val[2]); // Oracle expects DEFAULT before NOT NULL
				$alter[] = ($table != "" ? ($field[0] != "" ? "MODIFY (" : "ADD (") : "  ") . implode($val) . ($table != "" ? ")" : ""); //! error with name change only
			} else {
				$drop[] = idf_escape($field[0]);
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", array_merge($alter, $foreign)) . "\n)");
		}
		return (!$alter || queries("ALTER TABLE " . table($table) . "\n" . implode("\n", $alter)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP (" . implode(", ", $drop) . ")"))
			&& ($table == $name || queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name)))
		;
	}

	function alter_indexes(string $table, $alter) {
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				$val[2] = preg_replace('~ DESC$~', '', $val[2]);
				$create = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
				array_unshift($queries, "ALTER TABLE " . table($table) . $create);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table) . " (" . implode(", ", $val[2]) . ")";
			}
		}
		if ($drop) {
			array_unshift($queries, "DROP INDEX " . implode(", ", $drop));
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function foreign_keys(string $table): array {
		$return = array();
		$targets = array();
		foreach (table_constraints($table) as $name => $constraint) {
			if ($constraint["type"] == "R") {
				$return[$name] = array(
					"source" => $constraint["columns"],
					"target" => array(),
					"on_delete" => $constraint["delete_rule"],
					"on_update" => null,
				);
				$targets[$name] = array($constraint["r_owner"], $constraint["r_constraint"]);
			}
		}
		if ($targets) {
			$where = array();
			foreach ($targets as $target) {
				$where[] = "(owner = " . q($target[0]) . " AND constraint_name = " . q($target[1]) . ")";
			}
			foreach (get_rows("SELECT owner, constraint_name, table_name, column_name FROM all_cons_columns WHERE " . implode(" OR ", array_unique($where)) . " ORDER BY position") as $row) {
				foreach ($targets as $name => $target) {
					if ($target == array($row["OWNER"], $row["CONSTRAINT_NAME"])) {
						$return[$name]["db"] = $row["OWNER"];
						$return[$name]["table"] = $row["TABLE_NAME"];
						$return[$name]["target"][] = $row["COLUMN_NAME"];
					}
				}
			}
		}
		return $return;
	}

	function trigger(string $name, string $table): array {
		if ($name == "") {
			return array();
		}
		$rows = get_rows('SELECT trigger_name "Trigger", trigger_type "Type", triggering_event "Event", trigger_body "Statement"
FROM all_triggers
WHERE trigger_name = ' . q($name) . " AND " . where_owner());
		$return = reset($rows);
		if ($return) {
			$type = $return["Type"]; // e.g. 'BEFORE STATEMENT', 'AFTER EACH ROW', 'INSTEAD OF', 'COMPOUND'
			$return["Timing"] = (preg_match('~^(BEFORE|AFTER|INSTEAD OF)~', $type, $match) ? $match[1] : $type);
			$return["Type"] = (preg_match('~EACH ROW~', $type) || $type == "INSTEAD OF" ? "FOR EACH ROW" : "");
		}
		return ($return ?: array());
	}

	function triggers(string $table): array {
		$return = array();
		foreach (get_rows("SELECT trigger_name, trigger_type, triggering_event FROM all_triggers WHERE table_name = " . q($table) . " AND " . where_owner()) as $row) {
			$return[$row["TRIGGER_NAME"]] = array(preg_replace('~ (STATEMENT|EACH ROW)$~', '', $row["TRIGGER_TYPE"]), $row["TRIGGERING_EVENT"]);
		}
		return $return;
	}

	function trigger_options(): array {
		return array(
			"Timing" => array("BEFORE", "AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "DELETE", "INSERT OR UPDATE", "INSERT OR DELETE", "UPDATE OR DELETE", "INSERT OR UPDATE OR DELETE"), //! UPDATE OF columns
			"Type" => array("FOR EACH ROW", ""), // a statement level trigger has no clause
		);
	}

	function truncate_tables(array $tables): bool {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views(array $views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables(array $tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function last_id($result): string {
		return "0"; //!
	}

	function create_database(string $db, string $collation) {
		// a schema-only account, available since Oracle 18c; the quota is charged to the owner so the new schema needs it
		$return = queries("CREATE USER " . idf_escape($db) . " NO AUTHENTICATION");
		return ($return ? queries("GRANT UNLIMITED TABLESPACE TO " . idf_escape($db)) : $return);
	}

	function drop_databases(array $databases): bool {
		$return = true;
		foreach ($databases as $db) {
			$return = !!queries("DROP USER " . idf_escape($db) . " CASCADE") && $return;
		}
		return $return;
	}

	function rename_database(string $name, string $collation): bool {
		return !!queries("ALTER USER " . idf_escape(DB) . " RENAME TO " . idf_escape($name)); // Oracle reports ORA-03001: unimplemented feature
	}

	function show_variables(): array {
		return get_rows('SELECT name, display_value FROM v$parameter');
	}

	function show_status(): array {
		$return = array();
		$rows = get_rows('SELECT * FROM v$instance');
		foreach (reset($rows) as $key => $val) {
			$return[] = array($key, $val);
		}
		return $return;
	}

	function process_list(): array {
		return get_rows('SELECT
	sess.process AS "process",
	sess.username AS "user",
	sess.schemaname AS "schema",
	sess.status AS "status",
	sess.wait_class AS "wait_class",
	sess.seconds_in_wait AS "seconds_in_wait",
	sql.sql_text AS "sql_text",
	sess.machine AS "machine",
	sess.port AS "port"
FROM v$session sess
LEFT JOIN v$sql sql ON sql.sql_id = sess.sql_id
WHERE sess.type = \'USER\'
ORDER BY PROCESS
');
	}

	function convert_field(array $field) {
		if ($field["type"] == "sdo_geometry") {
			return "SDO_UTIL.TO_WKTGEOMETRY(" . idf_escape($field["field"]) . ")";
		}
	}

	function unconvert_field(array $field, string $return): string {
		return ($field["type"] == "sdo_geometry" ? "SDO_UTIL.FROM_WKTGEOMETRY($return)" : $return);
	}

	function support(string $feature): bool {
		return preg_match('~^(columns|database|drop_col|fast_status|indexes|descidx|processlist|sql|status|table|trigger|variables|view|view_trigger)$~', $feature);
	}
}
