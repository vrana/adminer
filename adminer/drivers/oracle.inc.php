<?php
$drivers["oracle"] = "Oracle (beta)";

if (isset($_GET["oracle"])) {
	$possible_drivers = array("OCI8", "PDO_OCI");
	define("DRIVER", "oracle");
	if (extension_loaded("oci8")) {
		class Min_DB {
			var $extension = "oci8", $_link, $_result, $server_info, $affected_rows, $errno, $error;

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

			function connect($server, $username, $password) {
				$this->dsn("oci:dbname=//$server;charset=AL32UTF8", $username, $password);
				return true;
			}

			function select_db($database) {
				return true;
			}
		}

	}



	class Min_Driver extends Min_SQL {

		//! support empty $set in insert()

		function begin() {
			return true; // automatic start
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
		return get_vals("SELECT tablespace_name FROM user_tablespaces");
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

	function tables_list() {
		return get_key_vals("SELECT table_name, 'table' FROM all_tables WHERE tablespace_name = " . q(DB) . "
UNION SELECT view_name, 'view' FROM user_views
ORDER BY 1"
		); //! views don't have schema
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "") {
		$return = array();
		$search = q($name);
		foreach (get_rows('SELECT table_name "Name", \'table\' "Engine", avg_row_len * num_rows "Data_length", num_rows "Rows" FROM all_tables WHERE tablespace_name = ' . q(DB) . ($name != "" ? " AND table_name = $search" : "") . "
UNION SELECT view_name, 'view', 0, 0 FROM user_views" . ($name != "" ? " WHERE view_name = $search" : "") . "
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
		foreach (get_rows("SELECT * FROM all_tab_columns WHERE table_name = " . q($table) . " ORDER BY column_id") as $row) {
			$type = $row["DATA_TYPE"];
			$length = "$row[DATA_PRECISION],$row[DATA_SCALE]";
			if ($length == ",") {
				$length = $row["DATA_LENGTH"];
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
		foreach (get_rows("SELECT uic.*, uc.constraint_type
FROM user_ind_columns uic
LEFT JOIN user_constraints uc ON uic.index_name = uc.constraint_name AND uic.table_name = uc.table_name
WHERE uic.table_name = " . q($table) . "
ORDER BY uc.constraint_type, uic.column_position", $connection2) as $row) {
			$index_name = $row["INDEX_NAME"];
			$return[$index_name]["type"] = ($row["CONSTRAINT_TYPE"] == "P" ? "PRIMARY" : ($row["CONSTRAINT_TYPE"] == "U" ? "UNIQUE" : "INDEX"));
			$return[$index_name]["columns"][] = $row["COLUMN_NAME"];
			$return[$index_name]["lengths"][] = ($row["CHAR_LENGTH"] && $row["CHAR_LENGTH"] != $row["COLUMN_LENGTH"] ? $row["CHAR_LENGTH"] : null);
			$return[$index_name]["descs"][] = ($row["DESCEND"] ? '1' : null);
		}
		return $return;
	}

	function view($name) {
		$rows = get_rows('SELECT text "select" FROM user_views WHERE view_name = ' . q($name));
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

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = $drop = array();
		foreach ($fields as $field) {
			$val = $field[1];
			if ($val && $field[0] != "" && idf_escape($field[0]) != $val[0]) {
				queries("ALTER TABLE " . table($table) . " RENAME COLUMN " . idf_escape($field[0]) . " TO $val[0]");
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
		return get_vals("SELECT DISTINCT owner FROM dba_segments WHERE owner IN (SELECT username FROM dba_users WHERE default_tablespace NOT IN ('SYSTEM','SYSAUX'))");
	}

	function get_schema() {
		global $connection;
		return $connection->result("SELECT sys_context('USERENV', 'SESSION_USER') FROM dual");
	}

	function set_schema($scheme) {
		global $connection;
		return $connection->query("ALTER SESSION SET CURRENT_SCHEMA = " . idf_escape($scheme));
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
		return preg_match('~^(columns|database|drop_col|indexes|processlist|scheme|sql|status|table|variables|view|view_trigger)$~', $feature); //!
	}

	$jush = "oracle";
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
	$unsigned = array();
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL", "SQL");
	$functions = array("length", "lower", "round", "upper");
	$grouping = array("avg", "count", "count distinct", "max", "min", "sum");
	$edit_functions = array(
		array( //! no parentheses
			"date" => "current_date",
			"timestamp" => "current_timestamp",
		), array(
			"number|float|double" => "+/-",
			"date|timestamp" => "+ interval/- interval",
			"char|clob" => "||",
		)
	);
}
