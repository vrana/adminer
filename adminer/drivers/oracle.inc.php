<?php
$drivers["oracle"] = "Oracle (beta)";

if (isset($_GET["oracle"])) {
	define("DRIVER", "oracle");
	if (extension_loaded("oci8")) {
		class Min_DB {
			var $extension = "oci8", $_link, $_result, $server_info, $affected_rows, $errno, $error;
			var $_current_db;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function connect($server, $username, $password) {
				$this->_link = @oci_new_connect($username, $password, $server, "AL32UTF8");
				if ($this->_link) {
					$this->server_info = oci_server_version($this->_link);
					return true;
				}
				$error = oci_error();
				$this->error = $error["message"];
				return false;
			}

			function quote($string) {
				return "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db($database) {
				$this->_current_db = $database;
				return true;
			}

			function query($query, $unbuffered = false) {
				$result = oci_parse($this->_link, $query);
				$this->error = "";
				if (!$result) {
					$error = oci_error($this->_link);
					$this->errno = $error["code"];
					$this->error = $error["message"];
					return false;
				}
				set_error_handler(array($this, '_error'));
				$return = @oci_execute($result);
				restore_error_handler();
				if ($return) {
					if (oci_num_fields($result)) {
						return new Min_Result($result);
					}
					$this->affected_rows = oci_num_rows($result);
					oci_free_statement($result);
				}
				return $return;
			}

			function multi_query($query) {
				return $this->_result = $this->query($query);
			}

			function store_result() {
				return $this->_result;
			}

			function next_result() {
				return false;
			}

			function result($query, $field = 1) {
				$result = $this->query($query);
				if (!is_object($result) || !oci_fetch($result->_result)) {
					return false;
				}
				return oci_result($result->_result, $field);
			}
		}

		class Min_Result {
			var $_result, $_offset = 1, $num_rows;

			function __construct($result) {
				$this->_result = $result;
			}

			function _convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'OCI-Lob')) {
						$row[$key] = $val->load();
					}
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->_convert(oci_fetch_assoc($this->_result));
			}

			function fetch_row() {
				return $this->_convert(oci_fetch_row($this->_result));
			}

			function fetch_field() {
				$column = $this->_offset++;
				$return = new stdClass;
				$return->name = oci_field_name($this->_result, $column);
				$return->orgname = $return->name;
				$return->type = oci_field_type($this->_result, $column);
				$return->charsetnr = (preg_match("~raw|blob|bfile~", $return->type) ? 63 : 0); // 63 - binary
				return $return;
			}

			function __destruct() {
				oci_free_statement($this->_result);
			}
		}

	} elseif (extension_loaded("pdo_oci")) {
		class Min_DB extends Min_PDO {
			var $extension = "PDO_OCI";
			var $_current_db;

			function connect($server, $username, $password) {
				$this->dsn("oci:dbname=//$server;charset=AL32UTF8", $username, $password);
				return true;
			}

			function select_db($database) {
				$this->_current_db = $database;
				return true;
			}
		}

	}



	class Min_Driver extends Min_SQL {

		//! support empty $set in insert()

		function begin() {
			return true; // automatic start
		}

		function insertUpdate($table, $rows, $primary) {
			global $connection;
			foreach ($rows as $set) {
				$update = array();
				$where = array();
				foreach ($set as $key => $val) {
					$update[] = "$key = $val";
					if (isset($primary[idf_unescape($key)])) {
						$where[] = "$key = $val";
					}
				}
				if (!(($where && queries("UPDATE " . table($table) . " SET " . implode(", ", $update) . " WHERE " . implode(" AND ", $where)) && $connection->affected_rows)
					|| queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES (" . implode(", ", $set) . ")")
				)) {
					return false;
				}
			}
			return true;
		}
	}



	function idf_escape($idf) {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function connect() {
		global $adminer;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			return $connection;
		}
		return $connection->error;
	}

	function get_databases() {
		return get_vals("SELECT tablespace_name FROM user_tablespaces ORDER BY 1");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return ($offset ? " * FROM (SELECT t.*, rownum AS rnum FROM (SELECT $query$where) t WHERE rownum <= " . ($limit + $offset) . ") WHERE rnum > $offset"
			: ($limit !== null ? " * FROM (SELECT $query$where) WHERE rownum <= " . ($limit + $offset)
			: " $query$where"
		));
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return " $query$where"; //! limit
	}

	function db_collation($db, $collations) {
		global $connection;
		return $connection->result("SELECT value FROM nls_database_parameters WHERE parameter = 'NLS_CHARACTERSET'"); //! respect $db
	}

	function engines() {
		return array();
	}

	function logged_user() {
		global $connection;
		return $connection->result("SELECT USER FROM DUAL");
	}

	function get_current_db() {
		global $connection;
		$db = $connection->_current_db ? $connection->_current_db : DB;
		unset($connection->_current_db);
		return $db;
	}

	function where_owner($prefix, $owner = "owner") {
		if (!$_GET["ns"]) {
			return '';
		}
		return "$prefix$owner = sys_context('USERENV', 'CURRENT_SCHEMA')";
	}

	function views_table($columns) {
		$owner = where_owner('');
		return "(SELECT $columns FROM all_views WHERE " . ($owner ? $owner : "rownum < 0") . ")";
	}

	function tables_list() {
		$view = views_table("view_name");
		$owner = where_owner(" AND ");
		return get_key_vals("SELECT table_name, 'table' FROM all_tables WHERE tablespace_name = " . q(DB) . "$owner
UNION SELECT view_name, 'view' FROM $view
ORDER BY 1"
		); //! views don't have schema
	}

	function count_tables($databases) {
		global $connection;
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = $connection->result("SELECT COUNT(*) FROM all_tables WHERE tablespace_name = " . q($db));
		}
		return $return;
	}

	function table_status($name = "") {
		$return = array();
		$search = q($name);
		$db = get_current_db();
		$view = views_table("view_name");
		$owner = where_owner(" AND ");
		foreach (get_rows('SELECT table_name "Name", \'table\' "Engine", avg_row_len * num_rows "Data_length", num_rows "Rows" FROM all_tables WHERE tablespace_name = ' . q($db) . $owner . ($name != "" ? " AND table_name = $search" : "") . "
UNION SELECT view_name, 'view', 0, 0 FROM $view" . ($name != "" ? " WHERE view_name = $search" : "") . "
ORDER BY 1"
		) as $row) {
			if ($name != "") {
				return $row;
			}
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view($table_status) {
		return $table_status["Engine"] == "view";
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$return = array();
		$owner = where_owner(" AND ");
		foreach (get_rows("SELECT * FROM all_tab_columns WHERE table_name = " . q($table) . "$owner ORDER BY column_id") as $row) {
			$type = $row["DATA_TYPE"];
			$length = "$row[DATA_PRECISION],$row[DATA_SCALE]";
			if ($length == ",") {
				$length = $row["CHAR_COL_DECL_LENGTH"];
			} //! int
			$return[$row["COLUMN_NAME"]] = array(
				"field" => $row["COLUMN_NAME"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => strtolower($type),
				"length" => $length,
				"default" => $row["DATA_DEFAULT"],
				"null" => ($row["NULLABLE"] == "Y"),
				//! "auto_increment" => false,
				//! "collation" => $row["CHARACTER_SET_NAME"],
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1),
				//! "comment" => $row["Comment"],
				//! "primary" => ($row["Key"] == "PRI"),
			);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		$owner = where_owner(" AND ", "aic.table_owner");
		foreach (get_rows("SELECT aic.*, ac.constraint_type, atc.data_default
FROM all_ind_columns aic
LEFT JOIN all_constraints ac ON aic.index_name = ac.constraint_name AND aic.table_name = ac.table_name AND aic.index_owner = ac.owner
LEFT JOIN all_tab_cols atc ON aic.column_name = atc.column_name AND aic.table_name = atc.table_name AND aic.index_owner = atc.owner
WHERE aic.table_name = " . q($table) . "$owner
ORDER BY ac.constraint_type, aic.column_position", $connection2) as $row) {
			$index_name = $row["INDEX_NAME"];
			$column_name = $row["DATA_DEFAULT"];
			$column_name = ($column_name ? trim($column_name, '"') : $row["COLUMN_NAME"]); // trim - possibly wrapped in quotes but never contains quotes inside
			$return[$index_name]["type"] = ($row["CONSTRAINT_TYPE"] == "P" ? "PRIMARY" : ($row["CONSTRAINT_TYPE"] == "U" ? "UNIQUE" : "INDEX"));
			$return[$index_name]["columns"][] = $column_name;
			$return[$index_name]["lengths"][] = ($row["CHAR_LENGTH"] && $row["CHAR_LENGTH"] != $row["COLUMN_LENGTH"] ? $row["CHAR_LENGTH"] : null);
			$return[$index_name]["descs"][] = ($row["DESCEND"] && $row["DESCEND"] == "DESC" ? '1' : null);
		}
		return $return;
	}

	function view($name) {
		$view = views_table("view_name, text");
		$rows = get_rows('SELECT text "select" FROM ' . $view . ' WHERE view_name = ' . q($name));
		return reset($rows);
	}

	function collations() {
		return array(); //!
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		global $connection;
		return h($connection->error); //! highlight sqltext from offset
	}

	function explain($connection, $query) {
		$connection->query("EXPLAIN PLAN FOR $query");
		return $connection->query("SELECT * FROM plan_table");
	}

	function found_rows($table_status, $where) {
	}

	function auto_increment() {
		return "";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
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
				$alter[] = ($table != "" ? ($field[0] != "" ? "MODIFY (" : "ADD (") : "  ") . implode($val) . ($table != "" ? ")" : ""); //! error with name change only
			} else {
				$drop[] = idf_escape($field[0]);
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)");
		}
		return (!$alter || queries("ALTER TABLE " . table($table) . "\n" . implode("\n", $alter)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP (" . implode(", ", $drop) . ")"))
			&& ($table == $name || queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name)))
		;
	}

	function alter_indexes($table, $alter) {
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				//! descending UNIQUE indexes results in syntax error
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

	function foreign_keys($table) {
		$return = array();
		$query = "SELECT c_list.CONSTRAINT_NAME as NAME,
c_src.COLUMN_NAME as SRC_COLUMN,
c_dest.OWNER as DEST_DB,
c_dest.TABLE_NAME as DEST_TABLE,
c_dest.COLUMN_NAME as DEST_COLUMN,
c_list.DELETE_RULE as ON_DELETE
FROM ALL_CONSTRAINTS c_list, ALL_CONS_COLUMNS c_src, ALL_CONS_COLUMNS c_dest
WHERE c_list.CONSTRAINT_NAME = c_src.CONSTRAINT_NAME
AND c_list.R_CONSTRAINT_NAME = c_dest.CONSTRAINT_NAME
AND c_list.CONSTRAINT_TYPE = 'R'
AND c_src.TABLE_NAME = " . q($table);
		foreach (get_rows($query) as $row) {
			$return[$row['NAME']] = array(
				"db" => $row['DEST_DB'],
				"table" => $row['DEST_TABLE'],
				"source" => array($row['SRC_COLUMN']),
				"target" => array($row['DEST_COLUMN']),
				"on_delete" => $row['ON_DELETE'],
				"on_update" => null,
			);
		}
		return $return;
	}

	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views($views) {
		return apply_queries("DROP VIEW", $views);
	}

	function drop_tables($tables) {
		return apply_queries("DROP TABLE", $tables);
	}

	function last_id() {
		return 0; //!
	}

	function schemas() {
		$return = get_vals("SELECT DISTINCT owner FROM dba_segments WHERE owner IN (SELECT username FROM dba_users WHERE default_tablespace NOT IN ('SYSTEM','SYSAUX')) ORDER BY 1");
		return ($return ? $return : get_vals("SELECT DISTINCT owner FROM all_tables WHERE tablespace_name = " . q(DB) . " ORDER BY 1"));
	}

	function get_schema() {
		global $connection;
		return $connection->result("SELECT sys_context('USERENV', 'SESSION_USER') FROM dual");
	}

	function set_schema($scheme, $connection2 = null) {
		global $connection;
		if (!$connection2) {
			$connection2 = $connection;
		}
		return $connection2->query("ALTER SESSION SET CURRENT_SCHEMA = " . idf_escape($scheme));
	}

	function show_variables() {
		return get_key_vals('SELECT name, display_value FROM v$parameter');
	}

	function process_list() {
		return get_rows('SELECT sess.process AS "process", sess.username AS "user", sess.schemaname AS "schema", sess.status AS "status", sess.wait_class AS "wait_class", sess.seconds_in_wait AS "seconds_in_wait", sql.sql_text AS "sql_text", sess.machine AS "machine", sess.port AS "port"
FROM v$session sess LEFT OUTER JOIN v$sql sql
ON sql.sql_id = sess.sql_id
WHERE sess.type = \'USER\'
ORDER BY PROCESS
');
	}

	function show_status() {
		$rows = get_rows('SELECT * FROM v$instance');
		return reset($rows);
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(columns|database|drop_col|indexes|descidx|processlist|scheme|sql|status|table|variables|view)$~', $feature); //!
	}

	function driver_config() {
		$types = array();
		$structured_types = array();
		foreach (array(
			lang('Numbers') => array("number" => 38, "binary_float" => 12, "binary_double" => 21),
			lang('Date and time') => array("date" => 10, "timestamp" => 29, "interval year" => 12, "interval day" => 28), //! year(), day() to second()
			lang('Strings') => array("char" => 2000, "varchar2" => 4000, "nchar" => 2000, "nvarchar2" => 4000, "clob" => 4294967295, "nclob" => 4294967295),
			lang('Binary') => array("raw" => 2000, "long raw" => 2147483648, "blob" => 4294967295, "bfile" => 4294967296),
		) as $key => $val) {
			$types += $val;
			$structured_types[$key] = array_keys($val);
		}
		return array(
			'possible_drivers' => array("OCI8", "PDO_OCI"),
			'jush' => "oracle",
			'types' => $types,
			'structured_types' => $structured_types,
			'unsigned' => array(),
			'operators' => array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL", "SQL"),
			'functions' => array("length", "lower", "round", "upper"),
			'grouping' => array("avg", "count", "count distinct", "max", "min", "sum"),
			'edit_functions' => array(
				array( //! no parentheses
					"date" => "current_date",
					"timestamp" => "current_timestamp",
				), array(
					"number|float|double" => "+/-",
					"date|timestamp" => "+ interval/- interval",
					"char|clob" => "||",
				)
			),
		);
	}
}
