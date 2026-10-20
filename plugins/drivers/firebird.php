<?php
/**
* @author Steve Krämer
*/

namespace Adminer;

add_driver('firebird', 'Firebird (alpha)');

if (isset($_GET["firebird"])) {
	define('Adminer\DRIVER', "firebird");

	if (extension_loaded("pdo_firebird")) {
		class Db extends PdoDb {
			public $extension = "PDO_Firebird";

			function attach(array $server, string $username, string $password): string {
				$host = $server["host"] . ($server["port"] != "" ? "/" . $server["port"] : "");
				// dbname is 'host/port:/path/to/your.fdb', a server without a path is a local file or an alias from databases.conf
				$return = $this->dsn("firebird:charset=UTF8;dbname=$host" . ($server["path"] != "" ? ":" . $server["path"] : ""), $username, $password);
				if ($return == "") {
					$this->server_info = get_val("SELECT rdb\$get_context('SYSTEM', 'ENGINE_VERSION') FROM rdb\$database", 0, $this); // ATTR_SERVER_VERSION is a long description
				}
				return $return;
			}

			function select_db(string $database): bool {
				return ($database == "domain");
			}
		}

	}



	class Driver extends SqlDriver {
		static $extensions = array("PDO_Firebird");
		static $jush = "firebird";

		static $serverPath = true; // the path to the database file

		function operators(?array $tableStatus): array {
			return array("=");
		}

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
		$return = array();
		foreach (get_vals('SELECT RDB$RELATION_NAME FROM rdb$relations WHERE rdb$system_flag = 0') as $name) {
			$return[trim($name)] = 'table';
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
COALESCE(r.RDB$DEFAULT_SOURCE, f.RDB$DEFAULT_SOURCE) AS field_default_value,
r.RDB$NULL_FLAG AS field_not_null_constraint,
r.RDB$IDENTITY_TYPE AS field_identity_type,
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
		foreach (get_rows($query) as $row) {
			$default = $row['FIELD_DEFAULT_VALUE']; // RDB$DEFAULT_VALUE is compiled BLR, the source is 'DEFAULT expression' of the column or its domain
			if ($default !== null) {
				$default = preg_replace('~^\s*DEFAULT\s+~i', '', $default);
				$default = (preg_match("~^'(.*)'$~s", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default));
			}
			$return[trim($row['FIELD_NAME'])] = array(
				"field" => trim($row["FIELD_NAME"]),
				"full_type" => trim($row["FIELD_TYPE"]),
				"type" => trim($row["FIELD_SUB_TYPE"]),
				"default" => ($row["FIELD_IDENTITY_TYPE"] !== null // 0 - GENERATED ALWAYS, 1 - GENERATED BY DEFAULT
					? "GENERATED " . ($row["FIELD_IDENTITY_TYPE"] ? "BY DEFAULT" : "ALWAYS") . " AS IDENTITY"
					: $default
				),
				"null" => (trim($row["FIELD_NOT_NULL_CONSTRAINT"]) == "YES"),
				"auto_increment" => ($row["FIELD_IDENTITY_TYPE"] !== null),
				"collation" => trim($row["FIELD_COLLATION"]),
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				"comment" => trim($row["FIELD_DESCRIPTION"]),
			);
		}
		return $return;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		$return = array();
		// RDB$FOREIGN_KEY - the index of a foreign key is managed by the constraint
		$query = 'SELECT i.RDB$INDEX_NAME AS index_name, c.RDB$CONSTRAINT_NAME AS constraint_name, c.RDB$CONSTRAINT_TYPE AS constraint_type,
i.RDB$UNIQUE_FLAG AS unique_flag, i.RDB$INDEX_TYPE AS index_type, i.RDB$INDEX_INACTIVE AS inactive,
i.RDB$EXPRESSION_SOURCE AS expression, ' . (min_version(5, "", $connection2) ? 'i.RDB$CONDITION_SOURCE' : 'NULL') . ' AS condition, s.RDB$FIELD_NAME AS field_name
FROM RDB$INDICES i
LEFT JOIN RDB$INDEX_SEGMENTS s ON s.RDB$INDEX_NAME = i.RDB$INDEX_NAME
LEFT JOIN RDB$RELATION_CONSTRAINTS c ON c.RDB$INDEX_NAME = i.RDB$INDEX_NAME
WHERE i.RDB$RELATION_NAME = ' . q($table) . ' AND i.RDB$FOREIGN_KEY IS NULL
ORDER BY c.RDB$CONSTRAINT_TYPE NULLS LAST, i.RDB$UNIQUE_FLAG DESC, i.RDB$INDEX_NAME, s.RDB$FIELD_POSITION';
		foreach (get_rows($query, $connection2) as $row) {
			$name = trim($row["CONSTRAINT_NAME"] ?: $row["INDEX_NAME"]); // the constraint is dropped by its name, the index can be named differently, e.g. RDB$PRIMARY1
			$return[$name]["type"] = ($row["CONSTRAINT_TYPE"] == "PRIMARY KEY" ? "PRIMARY" : ($row["UNIQUE_FLAG"] ? "UNIQUE" : "INDEX"));
			// an expression index has no segments, its source includes the parentheses of COMPUTED BY
			$return[$name]["columns"][] = ($row["EXPRESSION"] !== null ? substr($row["EXPRESSION"], 1, -1) : trim($row["FIELD_NAME"]));
			$return[$name]["lengths"] = array();
			$return[$name]["descs"][] = ($row["INDEX_TYPE"] == 1 ? '1' : null); // DESCENDING applies to the whole index
			$return[$name]["partial"] = preg_replace('~^WHERE\s+~i', '', (string) $row["CONDITION"]);
			$return[$name]["invalid"] = ($row["INACTIVE"] == 1);
		}
		return $return;
	}

	function alter_indexes(string $table, $alter) {
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			$name = $val[1];
			if ($val[2] == "DROP") {
				$drop[] = ($val[0] == "PRIMARY"
					|| ($val[0] == "UNIQUE" && get_val('SELECT 1 FROM RDB$RELATION_CONSTRAINTS WHERE RDB$RELATION_NAME = ' . q($table) . ' AND RDB$CONSTRAINT_NAME = ' . q($name)))
					? "ALTER TABLE " . table($table) . " DROP CONSTRAINT " . idf_escape($name)
					: "DROP INDEX " . idf_escape($name)
				);
			} else {
				// DESCENDING applies to the whole index, mixed directions are left for the server to reject
				$columns = preg_replace('~ DESC$~', '', $val[2]);
				$desc = !array_diff_key($val[2], preg_grep('~ DESC$~', $val[2]));
				$expression = preg_grep('~^"~', $columns, PREG_GREP_INVERT);
				$queries[] = ($val[0] == "PRIMARY" || ($val[0] == "UNIQUE" && !$desc && !$expression && $val[4] == "")
					? "ALTER TABLE " . table($table) . " ADD" . ($name != "" ? " CONSTRAINT " . idf_escape($name) : "")
						. ($val[0] == "PRIMARY" ? " PRIMARY KEY" : " UNIQUE") . " (" . implode(", ", $val[2]) . ")"
					: "CREATE" . ($val[0] == "UNIQUE" ? " UNIQUE" : "") . ($desc ? " DESCENDING" : "")
						. " INDEX " . idf_escape($name != "" ? $name : uniqid($table . "_")) . " ON " . table($table)
						. ($expression ? " COMPUTED BY" : "") . " (" . implode(", ", ($desc ? $columns : $val[2])) . ")"
						. ($val[4] != "" ? " WHERE $val[4]" : "")
				);
			}
		}
		// drop first, a changed index keeps its name
		foreach (array_merge($drop, $queries) as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
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

	function last_id($result) {
	}

	function explain(Db $connection, string $query) {
	}

	function found_rows(array $table_status, array $where) {
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
		return preg_match('~^(columns|descidx|indexes|single_db|sql|table)$~', $feature) || ($feature == "partial_indexes" && min_version(5));
	}
}
