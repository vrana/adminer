<?php
/**
* @author Steve Krämer
*/

$drivers['firebird'] = 'Firebird (alpha)';
	
if (isset($_GET["firebird"])) {
	$possible_drivers = array("interbase");
	define("DRIVER", "firebird");

	if (extension_loaded("interbase") ) {
		class Min_DB {
			var
				$extension = "Firebird",
				$server_info,
				$affected_rows,
				$errno,
				$error,
				$_link, $_result
			;

			function connect($server, $username, $password) {
				$this->_link = ibase_connect($server, $username, $password); 
				if ($this->_link) {
					$url_parts = explode(':', $server);
					$this->service_link = ibase_service_attach($url_parts[0], $username, $password);
					$this->server_info = ibase_server_info($this->service_link, IBASE_SVC_SERVER_VERSION);
				} else {
					$this->errno = ibase_errcode();
					$this->error = ibase_errmsg();
				}
				return (bool) $this->_link;
			}

			function quote($string) {
				return "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db($database) {
				return ($database == "domain");
			}

			function query($query, $unbuffered = false) {
				$result = ibase_query($query, $this->_link);
				if (!$result) {
					$this->errno = ibase_errcode();
					$this->error = ibase_errmsg();
					return false;
				}
				$this->error = "";
				if ($result === true) {
					$this->affected_rows = ibase_affected_rows($this->_link);
					return true;
				}
				return new Min_Result($result);
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

			function result($query, $field = 0) {
				$result = $this->query($query);
				if (!$result || !$result->num_rows) {
					return false;
				}
				$row = $result->fetch_row();
				return $row[$field];
			}
		}

		class Min_Result {
			var $num_rows, $_result, $_offset = 0;

			function __construct($result) {
				$this->_result = $result;
				// $this->num_rows = ibase_num_rows($result);
			}

			function fetch_assoc() {
				return ibase_fetch_assoc($this->_result);
			}

			function fetch_row() {
				return ibase_fetch_row($this->_result);
			}

			function fetch_field() {
				$field = ibase_field_info($this->_result, $this->_offset++);
				return (object) array(
					'name' => $field['name'],
					'orgname' => $field['name'],
					'type' => $field['type'],
					'charsetnr' => $field['length'],
				);
			}

			function __destruct() {
				ibase_free_result($this->_result);
			}
		}

	}
	
	
	
	class Min_Driver extends Min_SQL {
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

	function get_databases($flush) {
		return array("domain");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		$return = ''; 
		$return .= ($limit !== null ? $separator . "FIRST $limit" . ($offset ? " SKIP $offset" : "") : "");
		$return .= " $query$where";
		return $return;
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation($db, $collations) {
	}

	function engines() {
		return array();
	}

	function logged_user() {
		global $adminer;
		$credentials = $adminer->credentials();
		return $credentials[1];
	}

	function tables_list() {
		global $connection;
		$query = 'SELECT RDB$RELATION_NAME FROM rdb$relations WHERE rdb$system_flag = 0';
		$result = ibase_query($connection->_link, $query);
		$return = array();
		while ($row = ibase_fetch_assoc($result)) {
				$return[$row['RDB$RELATION_NAME']] = 'table';
		}
		ksort($return);	
		return $return;
	}

	function count_tables($databases) {
		return array();
	}

	function table_status($name = "", $fast = false) {
		global $connection;
		$return = array();
		$data = tables_list();
		foreach ($data as $index => $val) {
			$index = trim($index);
			$return[$index] = array(
				'Name' => $index,
				'Engine' => 'standard',
			);
			if ($name == $index) {
				return $return[$index];
			}
		}
		return $return;
	}

	function is_view($table_status) {
		return false;
	}

	function fk_support($table_status) {
		return preg_match('~InnoDB|IBMDB2I~i', $table_status["Engine"]);
	}

	function fields($table) {
		global $connection;
		$return = array();
		$query = 'SELECT r.RDB$FIELD_NAME AS field_name,
r.RDB$DESCRIPTION AS field_description,
r.RDB$DEFAULT_VALUE AS field_default_value,
r.RDB$NULL_FLAG AS field_not_null_constraint,
f.RDB$FIELD_LENGTH AS field_length,
f.RDB$FIELD_PRECISION AS field_precision,
f.RDB$FIELD_SCALE AS field_scale,
CASE f.RDB$FIELD_TYPE
WHEN 261 THEN \'BLOB\'
WHEN 14 THEN \'CHAR\'
WHEN 40 THEN \'CSTRING\'
WHEN 11 THEN \'D_FLOAT\'
WHEN 27 THEN \'DOUBLE\'
WHEN 10 THEN \'FLOAT\'
WHEN 16 THEN \'INT64\'
WHEN 8 THEN \'INTEGER\'
WHEN 9 THEN \'QUAD\'
WHEN 7 THEN \'SMALLINT\'
WHEN 12 THEN \'DATE\'
WHEN 13 THEN \'TIME\'
WHEN 35 THEN \'TIMESTAMP\'
WHEN 37 THEN \'VARCHAR\'
ELSE \'UNKNOWN\'
END AS field_type,
f.RDB$FIELD_SUB_TYPE AS field_subtype,
coll.RDB$COLLATION_NAME AS field_collation,
cset.RDB$CHARACTER_SET_NAME AS field_charset
FROM RDB$RELATION_FIELDS r
LEFT JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
LEFT JOIN RDB$COLLATIONS coll ON f.RDB$COLLATION_ID = coll.RDB$COLLATION_ID
LEFT JOIN RDB$CHARACTER_SETS cset ON f.RDB$CHARACTER_SET_ID = cset.RDB$CHARACTER_SET_ID
WHERE r.RDB$RELATION_NAME = ' . q($table) . '
ORDER BY r.RDB$FIELD_POSITION';
		$result = ibase_query($connection->_link, $query);
		while ($row = ibase_fetch_assoc($result)) {
			$return[trim($row['FIELD_NAME'])] = array(
				"field" => trim($row["FIELD_NAME"]),
				"full_type" => trim($row["FIELD_TYPE"]),
				"type" => trim($row["FIELD_SUB_TYPE"]),
				"default" => trim($row['FIELD_DEFAULT_VALUE']),
				"null" => (trim($row["FIELD_NOT_NULL_CONSTRAINT"]) == "YES"),
				"auto_increment" => '0',
				"collation" => trim($row["FIELD_COLLATION"]),
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1),
				"comment" => trim($row["FIELD_DESCRIPTION"]),
			);
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		$return = array();
		/*
		$query = 'SELECT RDB$INDEX_SEGMENTS.RDB$FIELD_NAME AS field_name,
RDB$INDICES.RDB$DESCRIPTION AS description,
(RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION + 1) AS field_position
FROM RDB$INDEX_SEGMENTS
LEFT JOIN RDB$INDICES ON RDB$INDICES.RDB$INDEX_NAME = RDB$INDEX_SEGMENTS.RDB$INDEX_NAME
LEFT JOIN RDB$RELATION_CONSTRAINTS ON RDB$RELATION_CONSTRAINTS.RDB$INDEX_NAME = RDB$INDEX_SEGMENTS.RDB$INDEX_NAME
WHERE UPPER(RDB$INDICES.RDB$RELATION_NAME) = ' . q($table) . '
// AND UPPER(RDB$INDICES.RDB$INDEX_NAME) = \'TEST2_FIELD5_IDX\'
AND RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_TYPE IS NULL
ORDER BY RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION';
		*/
		return $return;
	}

	function foreign_keys($table) {
		return array();
	}

	function collations() {
		return array();
	}

	function information_schema($db) {
		return false;
	}

	function error() {
		global $connection;
		return h($connection->error);
	}

	function types() {
		return array();
	}

	function schemas() {
		return array();
	}

	function get_schema() {
		return "";
	}

	function set_schema($schema) {
		return true;
	}

	function support($feature) {
		return preg_match("~^(columns|sql|status|table)$~", $feature);
	}

	$jush = "firebird";
	$operators = array("=");
	$functions = array();
	$grouping = array();
	$edit_functions = array();
}
