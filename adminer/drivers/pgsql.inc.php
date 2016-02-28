<?php
$drivers["pgsql"] = "PostgreSQL";

if (isset($_GET["pgsql"])) {
	$possible_drivers = array("PgSQL", "PDO_PgSQL");
	define("DRIVER", "pgsql");
	if (extension_loaded("pgsql")) {
		class Min_DB {
			var $extension = "PgSQL", $_link, $_result, $_string, $_database = true, $server_info, $affected_rows, $error;

			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function connect($server, $username, $password) {
				global $adminer;
				$db = $adminer->database();
				set_error_handler(array($this, '_error'));
				$this->_string = "host='" . str_replace(":", "' port='", addcslashes($server, "'\\")) . "' user='" . addcslashes($username, "'\\") . "' password='" . addcslashes($password, "'\\") . "'";
				$this->_link = @pg_connect("$this->_string dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'", PGSQL_CONNECT_FORCE_NEW);
				if (!$this->_link && $db != "") {
					// try to connect directly with database for performance
					$this->_database = false;
					$this->_link = @pg_connect("$this->_string dbname='postgres'", PGSQL_CONNECT_FORCE_NEW);
				}
				restore_error_handler();
				if ($this->_link) {
					$version = pg_version($this->_link);
					$this->server_info = $version["server"];
					pg_set_client_encoding($this->_link, "UTF8");
				}
				return (bool) $this->_link;
			}

			function quote($string) {
				return "'" . pg_escape_string($this->_link, $string) . "'"; //! bytea
			}

			function select_db($database) {
				global $adminer;
				if ($database == $adminer->database()) {
					return $this->_database;
				}
				$return = @pg_connect("$this->_string dbname='" . addcslashes($database, "'\\") . "'", PGSQL_CONNECT_FORCE_NEW);
				if ($return) {
					$this->_link = $return;
				}
				return $return;
			}

			function close() {
				$this->_link = @pg_connect("$this->_string dbname='postgres'");
			}

			function query($query, $unbuffered = false) {
				$result = @pg_query($this->_link, $query);
				$this->error = "";
				if (!$result) {
					$this->error = pg_last_error($this->_link);
					return false;
				} elseif (!pg_num_fields($result)) {
					$this->affected_rows = pg_affected_rows($result);
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
				// PgSQL extension doesn't support multiple results
				return false;
			}

			function result($query, $field = 0) {
				$result = $this->query($query);
				if (!$result || !$result->num_rows) {
					return false;
				}
				return pg_fetch_result($result->_result, 0, $field);
			}
		}

		class Min_Result {
			var $_result, $_offset = 0, $num_rows;

			function __construct($result) {
				$this->_result = $result;
				$this->num_rows = pg_num_rows($result);
			}

			function fetch_assoc() {
				return pg_fetch_assoc($this->_result);
			}

			function fetch_row() {
				return pg_fetch_row($this->_result);
			}

			function fetch_field() {
				$column = $this->_offset++;
				$return = new stdClass;
				if (function_exists('pg_field_table')) {
					$return->orgtable = pg_field_table($this->_result, $column);
				}
				$return->name = pg_field_name($this->_result, $column);
				$return->orgname = $return->name;
				$return->type = pg_field_type($this->_result, $column);
				$return->charsetnr = ($return->type == "bytea" ? 63 : 0); // 63 - binary
				return $return;
			}

			function __destruct() {
				pg_free_result($this->_result);
			}
		}

	} elseif (extension_loaded("pdo_pgsql")) {
		class Min_DB extends Min_PDO {
			var $extension = "PDO_PgSQL";

			function connect($server, $username, $password) {
				global $adminer;
				$db = $adminer->database();
				$string = "pgsql:host='" . str_replace(":", "' port='", addcslashes($server, "'\\")) . "' options='-c client_encoding=utf8'";
				$this->dsn("$string dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'", $username, $password);
				//! connect without DB in case of an error
				return true;
			}

			function select_db($database) {
				global $adminer;
				return ($adminer->database() == $database);
			}

			function close() {
			}
		}

	}



	class Min_Driver extends Min_SQL {

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
			if ($connection->server_info >= 9) {
				$connection->query("SET application_name = 'Adminer'");
			}
			return $connection;
		}
		return $connection->error;
	}

	function get_databases() {
		return get_vals("SELECT datname FROM pg_database ORDER BY datname");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($query, $where) {
		return " $query$where";
	}

	function db_collation($db, $collations) {
		global $connection;
		return $connection->result("SHOW LC_COLLATE"); //! respect $db
	}

	function engines() {
		return array();
	}

	function logged_user() {
		global $connection;
		return $connection->result("SELECT user");
	}

	function tables_list() {
		$query = "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = current_schema()";
		if (support('materializedview')) {
			$query .= "
UNION ALL
SELECT matviewname, 'MATERIALIZED VIEW'
FROM pg_matviews
WHERE schemaname = current_schema()";
		}
		$query .= "
ORDER BY 1";
		return get_key_vals($query);
	}

	function count_tables($databases) {
		return array(); // would require reconnect
	}

	function table_status($name = "") {
		$return = array();
		foreach (get_rows("SELECT relname AS \"Name\", CASE relkind WHEN 'r' THEN 'table' WHEN 'mv' THEN 'materialized view' WHEN 'f' THEN 'foreign table' ELSE 'view' END AS \"Engine\", pg_relation_size(oid) AS \"Data_length\", pg_total_relation_size(oid) - pg_relation_size(oid) AS \"Index_length\", obj_description(oid, 'pg_class') AS \"Comment\", relhasoids::int AS \"Oid\", reltuples as \"Rows\"
FROM pg_class
WHERE relkind IN ('r','v','mv','f')
AND relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())
" . ($name != "" ? "AND relname = " . q($name) : "ORDER BY relname")
		) as $row) { //! Index_length, Auto_increment
			$return[$row["Name"]] = $row;
		}
		return ($name != "" ? $return[$name] : $return);
	}

	function is_view($table_status) {
		return in_array($table_status["Engine"], array("view", "materialized view"));
	}

	function fk_support($table_status) {
		return true;
	}

	function fields($table) {
		$return = array();
		$aliases = array(
			'timestamp without time zone' => 'timestamp',
			'timestamp with time zone' => 'timestamptz',
		);
		foreach (get_rows("SELECT a.attname AS field, format_type(a.atttypid, a.atttypmod) AS full_type, d.adsrc AS default, a.attnotnull::int, col_description(c.oid, a.attnum) AS comment
FROM pg_class c
JOIN pg_namespace n ON c.relnamespace = n.oid
JOIN pg_attribute a ON c.oid = a.attrelid
LEFT JOIN pg_attrdef d ON c.oid = d.adrelid AND a.attnum = d.adnum
WHERE c.relname = " . q($table) . "
AND n.nspname = current_schema()
AND NOT a.attisdropped
AND a.attnum > 0
ORDER BY a.attnum"
		) as $row) {
			//! collation, primary
			preg_match('~([^([]+)(\((.*)\))?([a-z ]+)?((\[[0-9]*])*)$~', $row["full_type"], $match);
			list(, $type, $length, $row["length"], $addon, $array) = $match;
			$row["length"] .= $array;
			$check_type = $type . $addon;
			if (isset($aliases[$check_type])) {
				$row["type"] = $aliases[$check_type];
				$row["full_type"] = $row["type"] . $length . $array;
			} else {
				$row["type"] = $type;
				$row["full_type"] = $row["type"] . $length . $addon . $array;
			}
			$row["null"] = !$row["attnotnull"];
			$row["auto_increment"] = preg_match('~^nextval\\(~i', $row["default"]);
			$row["privileges"] = array("insert" => 1, "select" => 1, "update" => 1);
			if (preg_match('~(.+)::[^)]+(.*)~', $row["default"], $match)) {
				$row["default"] = ($match[1][0] == "'" ? idf_unescape($match[1]) : $match[1]) . $match[2];
			}
			$return[$row["field"]] = $row;
		}
		return $return;
	}

	function indexes($table, $connection2 = null) {
		global $connection;
		if (!is_object($connection2)) {
			$connection2 = $connection;
		}
		$return = array();
		$table_oid = $connection2->result("SELECT oid FROM pg_class WHERE relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema()) AND relname = " . q($table));
		$columns = get_key_vals("SELECT attnum, attname FROM pg_attribute WHERE attrelid = $table_oid AND attnum > 0", $connection2);
		foreach (get_rows("SELECT relname, indisunique::int, indisprimary::int, indkey, indoption FROM pg_index i, pg_class ci WHERE i.indrelid = $table_oid AND ci.oid = i.indexrelid", $connection2) as $row) {
			$relname = $row["relname"];
			$return[$relname]["type"] = ($row["indisprimary"] ? "PRIMARY" : ($row["indisunique"] ? "UNIQUE" : "INDEX"));
			$return[$relname]["columns"] = array();
			foreach (explode(" ", $row["indkey"]) as $indkey) {
				$return[$relname]["columns"][] = $columns[$indkey];
			}
			$return[$relname]["descs"] = array();
			foreach (explode(" ", $row["indoption"]) as $indoption) {
				$return[$relname]["descs"][] = ($indoption & 1 ? '1' : null); // 1 - INDOPTION_DESC
			}
			$return[$relname]["lengths"] = array();
		}
		return $return;
	}

	function foreign_keys($table) {
		global $on_actions;
		$return = array();
		foreach (get_rows("SELECT conname, pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid = (SELECT pc.oid FROM pg_class AS pc INNER JOIN pg_namespace AS pn ON (pn.oid = pc.relnamespace) WHERE pc.relname = " . q($table) . " AND pn.nspname = current_schema())
AND contype = 'f'::char
ORDER BY conkey, conname") as $row) {
			if (preg_match('~FOREIGN KEY\s*\((.+)\)\s*REFERENCES (.+)\((.+)\)(.*)$~iA', $row['definition'], $match)) {
				$row['source'] = array_map('trim', explode(',', $match[1]));
				if (preg_match('~^(("([^"]|"")+"|[^"]+)\.)?"?("([^"]|"")+"|[^"]+)$~', $match[2], $match2)) {
					$row['ns'] = str_replace('""', '"', preg_replace('~^"(.+)"$~', '\1', $match2[2]));
					$row['table'] = str_replace('""', '"', preg_replace('~^"(.+)"$~', '\1', $match2[4]));
				}
				$row['target'] = array_map('trim', explode(',', $match[3]));
				$row['on_delete'] = (preg_match("~ON DELETE ($on_actions)~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$row['on_update'] = (preg_match("~ON UPDATE ($on_actions)~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$return[$row['conname']] = $row;
			}
		}
		return $return;
	}

	function view($name) {
		global $connection;
		return array("select" => $connection->result("SELECT pg_get_viewdef(" . q($name) . ")"));
	}

	function collations() {
		//! supported in CREATE DATABASE
		return array();
	}

	function information_schema($db) {
		return ($db == "information_schema");
	}

	function error() {
		global $connection;
		$return = h($connection->error);
		if (preg_match('~^(.*\\n)?([^\\n]*)\\n( *)\\^(\\n.*)?$~s', $return, $match)) {
			$return = $match[1] . preg_replace('~((?:[^&]|&[^;]*;){' . strlen($match[3]) . '})(.*)~', '\\1<b>\\2</b>', $match[2]) . $match[4];
		}
		return nl_br($return);
	}

	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " ENCODING " . idf_escape($collation) : ""));
	}

	function drop_databases($databases) {
		global $connection;
		$connection->close();
		return apply_queries("DROP DATABASE", $databases, 'idf_escape');
	}

	function rename_database($name, $collation) {
		//! current database cannot be renamed
		return queries("ALTER DATABASE " . idf_escape(DB) . " RENAME TO " . idf_escape($name));
	}

	function auto_increment() {
		return "";
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		$queries = array();
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter[] = "DROP $column";
			} else {
				$val5 = $val[5];
				unset($val[5]);
				if (isset($val[6]) && $field[0] == "") { // auto_increment
					$val[1] = ($val[1] == "bigint" ? " big" : " ") . "serial";
				}
				if ($field[0] == "") {
					$alter[] = ($table != "" ? "ADD " : "  ") . implode($val);
				} else {
					if ($column != $val[0]) {
						$queries[] = "ALTER TABLE " . table($table) . " RENAME $column TO $val[0]";
					}
					$alter[] = "ALTER $column TYPE$val[1]";
					if (!$val[6]) {
						$alter[] = "ALTER $column " . ($val[3] ? "SET$val[3]" : "DROP DEFAULT");
						$alter[] = "ALTER $column " . ($val[2] == " NULL" ? "DROP NOT" : "SET") . $val[2];
					}
				}
				if ($field[0] != "" || $val5 != "") {
					$queries[] = "COMMENT ON COLUMN " . table($table) . ".$val[0] IS " . ($val5 != "" ? substr($val5, 9) : "''");
				}
			}
		}
		$alter = array_merge($alter, $foreign);
		if ($table == "") {
			array_unshift($queries, "CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)");
		} elseif ($alter) {
			array_unshift($queries, "ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter));
		}
		if ($table != "" && $table != $name) {
			$queries[] = "ALTER TABLE " . table($table) . " RENAME TO " . table($name);
		}
		if ($table != "" || $comment != "") {
			$queries[] = "COMMENT ON TABLE " . table($name) . " IS " . q($comment);
		}
		if ($auto_increment != "") {
			//! $queries[] = "SELECT setval(pg_get_serial_sequence(" . q($name) . ", ), $auto_increment)";
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		return true;
	}

	function alter_indexes($table, $alter) {
		$create = array();
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				//! descending UNIQUE indexes results in syntax error
				$create[] = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table) . " (" . implode(", ", $val[2]) . ")";
			}
		}
		if ($create) {
			array_unshift($queries, "ALTER TABLE " . table($table) . implode(",", $create));
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

	function truncate_tables($tables) {
		return queries("TRUNCATE " . implode(", ", array_map('table', $tables)));
		return true;
	}

	function drop_views($views) {
		return drop_tables($views);
	}

	function drop_tables($tables) {
		foreach ($tables as $table) {
		    $status = table_status($table);
				if (!queries("DROP " . strtoupper($status["Engine"]) . " " . table($table))) {
					return false;
				}
		}
		return true;
	}

	function move_tables($tables, $views, $target) {
		foreach (array_merge($tables, $views) as $table) {
			$status = table_status($table);
			if (!queries("ALTER " . strtoupper($status["Engine"]) . " " . table($table) . " SET SCHEMA " . idf_escape($target))) {
				return false;
			}
		}
		return true;
	}

	function trigger($name) {
		if ($name == "") {
			return array("Statement" => "EXECUTE PROCEDURE ()");
		}
		$rows = get_rows('SELECT trigger_name AS "Trigger", condition_timing AS "Timing", event_manipulation AS "Event", \'FOR EACH \' || action_orientation AS "Type", action_statement AS "Statement" FROM information_schema.triggers WHERE event_object_table = ' . q($_GET["trigger"]) . ' AND trigger_name = ' . q($name));
		return reset($rows);
	}

	function triggers($table) {
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.triggers WHERE event_object_table = " . q($table)) as $row) {
			$return[$row["trigger_name"]] = array($row["condition_timing"], $row["event_manipulation"]);
		}
		return $return;
	}

	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("FOR EACH ROW", "FOR EACH STATEMENT"),
		);
	}

	/*
	function routine($name, $type) {
		//! there can be more functions with the same name differing only in parameters, it must be also passed to DROP FUNCTION
		//! no procedures, only functions
		//! different syntax of CREATE FUNCTION
		$rows = get_rows('SELECT pg_catalog.format_type(p.prorettype, NULL) AS "returns", p.prosrc AS "definition"
FROM pg_catalog.pg_namespace n
JOIN pg_catalog.pg_proc p ON p.pronamespace = n.oid
WHERE n.nspname = current_schema() AND p.proname = ' . q($name));
		$rows[0]["fields"] = array(); //!
		return $rows[0];
	}
	*/

	function routines() {
		return get_rows('SELECT p.proname AS "ROUTINE_NAME", p.proargtypes AS "ROUTINE_TYPE", pg_catalog.format_type(p.prorettype, NULL) AS "DTD_IDENTIFIER"
FROM pg_catalog.pg_namespace n
JOIN pg_catalog.pg_proc p ON p.pronamespace = n.oid
WHERE n.nspname = current_schema()
ORDER BY p.proname');
	}

	function routine_languages() {
		return get_vals("SELECT langname FROM pg_catalog.pg_language");
	}

	function last_id() {
		return 0; // there can be several sequences
	}

	function explain($connection, $query) {
		return $connection->query("EXPLAIN $query");
	}

	function found_rows($table_status, $where) {
		global $connection;
		if (preg_match(
			"~ rows=([0-9]+)~",
			$connection->result("EXPLAIN SELECT * FROM " . idf_escape($table_status["Name"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")),
			$regs
		)) {
			return $regs[1];
		}
		return false;
	}

	function types() {
		return get_vals("SELECT typname
FROM pg_type
WHERE typnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())
AND typtype IN ('b','d','e')
AND typelem = 0"
		);
	}

	function schemas() {
		return get_vals("SELECT nspname FROM pg_namespace ORDER BY nspname");
	}

	function get_schema() {
		global $connection;
		return $connection->result("SELECT current_schema()");
	}

	function set_schema($schema) {
		global $connection, $types, $structured_types;
		$return = $connection->query("SET search_path TO " . idf_escape($schema));
		foreach (types() as $type) { //! get types from current_schemas('t')
			if (!isset($types[$type])) {
				$types[$type] = 0;
				$structured_types[lang('User types')][] = $type;
			}
		}
		return $return;
	}

	function use_sql($database) {
		return "\connect " . idf_escape($database);
	}

	function show_variables() {
		return get_key_vals("SHOW ALL");
	}

	function process_list() {
		global $connection;
		return get_rows("SELECT * FROM pg_stat_activity ORDER BY " . ($connection->server_info < 9.2 ? "procpid" : "pid"));
	}

	function show_status() {
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		global $connection;
		return preg_match('~^(database|table|columns|sql|indexes|comment|view|' . ($connection->server_info >= 9.3 ? 'materializedview|' : '') . 'scheme|processlist|sequence|trigger|type|variables|drop_col|kill)$~', $feature); //! routine|
	}

	function kill_process($val) {
		return queries("SELECT pg_terminate_backend(" . number($val).")");
	}

	function max_connections() {
		global $connection;
		return $connection->result("SHOW max_connections");
	}

	$jush = "pgsql";
	$types = array();
	$structured_types = array();
	foreach (array( //! arrays
		lang('Numbers') => array("smallint" => 5, "integer" => 10, "bigint" => 19, "boolean" => 1, "numeric" => 0, "real" => 7, "double precision" => 16, "money" => 20),
		lang('Date and time') => array("date" => 13, "time" => 17, "timestamp" => 20, "timestamptz" => 21, "interval" => 0),
		lang('Strings') => array("character" => 0, "character varying" => 0, "text" => 0, "tsquery" => 0, "tsvector" => 0, "uuid" => 0, "xml" => 0),
		lang('Binary') => array("bit" => 0, "bit varying" => 0, "bytea" => 0),
		lang('Network') => array("cidr" => 43, "inet" => 43, "macaddr" => 17, "txid_snapshot" => 0),
		lang('Geometry') => array("box" => 0, "circle" => 0, "line" => 0, "lseg" => 0, "path" => 0, "point" => 0, "polygon" => 0),
	) as $key => $val) { //! can be retrieved from pg_type
		$types += $val;
		$structured_types[$key] = array_keys($val);
	}
	$unsigned = array();
	$operators = array("=", "<", ">", "<=", ">=", "!=", "~", "!~", "LIKE", "LIKE %%", "ILIKE", "ILIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL"); // no "SQL" to avoid SQL injection
	$functions = array("char_length", "lower", "round", "to_hex", "to_timestamp", "upper");
	$grouping = array("avg", "count", "count distinct", "max", "min", "sum");
	$edit_functions = array(
		array(
			"char" => "md5",
			"date|time" => "now",
		), array(
			"int|numeric|real|money" => "+/-",
			"date|time" => "+ interval/- interval", //! escape
			"char|text" => "||",
		)
	);
}
