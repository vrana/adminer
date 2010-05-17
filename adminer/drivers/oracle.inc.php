<?php
$possible_drivers[] = "OCI8";
$possible_drivers[] = "PDO_OCI";
if (extension_loaded("oci8") || extension_loaded("pdo_oci")) {
	$drivers["oracle"] = "Oracle";
}

if (isset($_GET["oracle"])) {
	define("DRIVER", "oracle");
	if (extension_loaded("oci8")) {
		class Min_DB {
			var $extension = "oci8", $_link, $_result, $server_info, $affected_rows, $error;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = ereg_replace('^[^:]*: ', '', $error);
				$this->error = $error;
			}
			
			function connect($server, $username, $password) {
				$this->_link = @oci_new_connect($username, $password, $server); //! AL32UTF8
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
				if (!$result) {
					$error = oci_error($this->_link);
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

			function Min_Result($result) {
				$this->_result = $result;
				$this->num_rows = -1; // all results unbuffered
			}

			function fetch_assoc() {
				return oci_fetch_assoc($this->_result);
			}

			function fetch_row() {
				return oci_fetch_row($this->_result);
			}

			function fetch_field() {
				$column = $this->_offset++;
				$return = new stdClass;
				$return->name = oci_field_name($this->_result, $column);
				$return->orgname = $return->name;
				$return->type = oci_field_type($this->_result, $column);
				$return->charsetnr = (ereg("raw|blob|bfile", $return->type) ? 63 : 0); // 63 - binary
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
			}
			
			function select_db($database) {
			}
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
		return " $query$where" . (isset($limit) ? ($where ? " AND" : $separator . "WHERE") . ($offset ? " rownum > $offset AND" : "") . " rownum <= " . ($limit + $offset) : "");
	}

	function limit1($query, $where) {
		return " $query$where";
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
		global $connection;
		return get_key_vals("SELECT table_name FROM all_tables WHERE tablespace_name = " . $connection->quote(DB)); //! views
	}

	function count_tables($databases) {
		return array();
	}
	
	function table_status($name = "") {
		global $connection;
		$return = array();
		$result = $connection->query('SELECT table_name "Name" FROM all_tables' . ($name != "" ? ' WHERE table_name = ' . $connection->quote($name) : ''));
		while ($row = $result->fetch_assoc()) {
			if ($name != "") {
				return $row;
			}
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function fk_support($table_status) {
		return false; //!
	}

	function fields($table) {
		global $connection;
		$return = array();
		$result = $connection->query("SELECT * FROM all_tab_columns WHERE table_name = " . $connection->quote($table) . " ORDER BY column_id");
		if ($result) {
			while ($row = $result->fetch_assoc()) {
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
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		return array(); //!
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
	
	function exact_value($val) {
		global $connection;
		return $connection->quote($val);
	}
	
	function explain($connection, $query) {
		//!
	}
	
	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = $drop = array();
		foreach ($fields as $field) {
			$val = $field[1];
			if ($val && $field[0] != "" && idf_escape($field[0]) != $val[0]) {
				queries("ALTER TABLE " . table($name) . " RENAME COLUMN " . idf_escape($field[0]) . " TO $val[0]");
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
		return (!$alter || queries("ALTER TABLE " . table($name) . "\n" . implode("\n", $alter)))
			&& (!$drop || queries("ALTER TABLE " . table($name) . " DROP (" . implode(", ", $drop) . ")"))
		;
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

	function begin() {
		return true; // automatic start
	}
	
	function insert_into($table, $set) {
		return queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"); //! no columns
	}
	
	function last_id() {
		return 0; //!
	}
	
	function schemas() {
		return array();
	}
	
	function get_schema() {
		return "";
	}
	
	function set_schema($scheme) {
		return true;
	}
	
	function support($feature) {
		return ereg("drop_col", $feature); //!
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
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL");
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
