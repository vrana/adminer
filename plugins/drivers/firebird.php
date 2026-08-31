<?php
/**
* @author Steve Krämer
*/

namespace Adminer;

add_driver('firebird', 'Firebird (alpha)');

if (isset($_GET["firebird"])) {
	define('Adminer\DRIVER', "firebird");

	if (extension_loaded("interbase")) {
		class Db extends SqlDb {
			public $extension = "Firebird", $_link;

			function attach(array $server, string $username, string $password): string {
				$host = $server["host"] . ($server["port"] != "" ? "/" . $server["port"] : "");
				$this->_link = ibase_connect($host . ($server["path"] != "" ? ":" . $server["path"] : ""), $username, $password); // the ibase connection string is 'host/port:/path/to/your.gdb'
				if ($this->_link) {
					$service_link = ibase_service_attach($host, $username, $password);
					$this->server_info = ibase_server_info($service_link, IBASE_SVC_SERVER_VERSION);
					return '';
				}
				return ibase_errmsg();
			}

			function quote(string $string): string {
				return "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db(string $database): bool {
				return ($database == "domain");
			}

			function query(string $query, bool $unbuffered = false) {
				$result = ibase_query($this->_link, $query);
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
				return new Result($result);
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
				// $this->num_rows = ibase_num_rows($result);
			}

			function fetch_assoc() {
				return ibase_fetch_assoc($this->result);
			}

			function fetch_row() {
				return ibase_fetch_row($this->result);
			}

			function fetch_field(): \stdClass {
				$field = ibase_field_info($this->result, $this->offset++);
				return (object) array(
					'name' => $field['name'],
					'type' => $field['type'], //! map to MySQL numbers
					'charsetnr' => 0,
				);
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("interbase");
		static $jush = "firebird";

		static $serverPath = true; // the path to the database file

		public $operators = array("=");

		/** Get the JUSH module inlined in the released driver by the release script */
		static function jushModule(): string {
			return ""; // the repository and the source archive load adminer/static/jush/modules/jush-firebird.js
		}
	}



	function idf_escape(string $idf): string {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table(string $idf): string {
		return idf_escape($idf);
	}

	function get_databases(bool $flush): array {
		return array("domain");
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		$return = '';
		$return .= ($limit ? $separator . "FIRST $limit" . ($offset ? " SKIP $offset" : "") : "");
		$return .= " $query$where";
		return $return;
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation(string $db, array $collations) {
	}

	function logged_user(): string {
		$credentials = adminer()->credentials();
		return $credentials[1];
	}

	function tables_list(): array {
		$query = 'SELECT RDB$RELATION_NAME FROM rdb$relations WHERE rdb$system_flag = 0';
		$result = ibase_query(connection()->_link, $query);
		$return = array();
		while ($row = ibase_fetch_assoc($result)) {
				$return[$row['RDB$RELATION_NAME']] = 'table';
		}
		ksort($return);
		return $return;
	}

	function count_tables(array $databases): array {
		return array();
	}

	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		$data = ($name != "" ? array($name => 1) : tables_list());
		foreach ($data as $index => $val) {
			$index = trim($index);
			$return[$index] = array(
				'Name' => $index,
				'Engine' => 'standard',
			);
		}
		return $return;
	}

	function is_view(array $table_status): bool {
		return false;
	}

	function fk_support(array $table_status): bool {
		return preg_match('~InnoDB|IBMDB2I~i', $table_status["Engine"]);
	}

	function fields(string $table): array {
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
		$result = ibase_query(connection()->_link, $query);
		while ($row = ibase_fetch_assoc($result)) {
			$return[trim($row['FIELD_NAME'])] = array(
				"field" => trim($row["FIELD_NAME"]),
				"full_type" => trim($row["FIELD_TYPE"]),
				"type" => trim($row["FIELD_SUB_TYPE"]),
				"default" => trim($row['FIELD_DEFAULT_VALUE']),
				"null" => (trim($row["FIELD_NOT_NULL_CONSTRAINT"]) == "YES"),
				"auto_increment" => '0',
				"collation" => trim($row["FIELD_COLLATION"]),
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				"comment" => trim($row["FIELD_DESCRIPTION"]),
			);
		}
		return $return;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
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

	function foreign_keys(string $table): array {
		return array();
	}

	function collations(): array {
		return array();
	}

	function information_schema(string $db): bool {
		return false;
	}

	function error(): string {
		return h(connection()->error);
	}

	function types(): array {
		return array();
	}

	function convert_field(array $field) {
	}

	function unconvert_field(array $field, string $return): string {
		return $return;
	}

	function support(string $feature): bool {
		return preg_match("~^(columns|sql|status|table)$~", $feature);
	}
}
