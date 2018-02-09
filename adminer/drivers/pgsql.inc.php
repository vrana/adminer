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
				return "'" . pg_escape_string($this->_link, $string) . "'";
			}

			function value($val, $field) {
				return ($field["type"] == "bytea" ? pg_unescape_bytea($val) : $val);
			}

			function quoteBinary($string) {
				return "'" . pg_escape_bytea($this->_link, $string) . "'";
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

			function warnings() {
				return h(pg_last_notice($this->_link)); // second parameter is available since PHP 7.1.0
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

			function value($val, $field) {
				return $val;
			}

			function quoteBinary($s) {
				return q($s);
			}

			function warnings() {
				return ''; // not implemented in PDO_PgSQL as of PHP 7.2.1
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

		function convertSearch($idf, $val, $field) {
			return (preg_match('~char|text' . (is_numeric($val["val"]) && !preg_match('~LIKE~', $val["op"]) ? '|' . number_type() : '') . '~', $field["type"])
				? $idf
				: "CAST($idf AS text)"
			);
		}

		function value($val, $field) {
			return $this->_conn->value($val, $field);
		}

		function quoteBinary($s) {
			return $this->_conn->quoteBinary($s);
		}

		function warnings() {
			return $this->_conn->warnings();
		}

		function tableHelp($name) {
			$links = array(
				"information_schema" => "infoschema",
				"pg_catalog" => "catalog",
			);
			$link = $links[$_GET["ns"]];
			if ($link) {
				return "$link-" . str_replace("_", "-", $name) . ".html";
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
		global $adminer, $types, $structured_types;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			if (min_version(9, 0, $connection)) {
				$connection->query("SET application_name = 'Adminer'");
				if (min_version(9.2, 0, $connection)) {
					$structured_types[lang('Strings')][] = "json";
					$types["json"] = 4294967295;
					if (min_version(9.4, 0, $connection)) {
						$structured_types[lang('Strings')][] = "jsonb";
						$types["jsonb"] = 4294967295;
					}
				}
			}
			return $connection;
		}
		return $connection->error;
	}

	function get_databases() {
		return get_vals("SELECT datname FROM pg_database WHERE has_database_privilege(datname, 'CONNECT') ORDER BY datname");
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1($table, $query, $where, $separator = "\n") {
		return (preg_match('~^INTO~', $query)
			? limit($query, $where, 1, 0, $separator)
			: " $query WHERE ctid = (SELECT ctid FROM " . table($table) . $where . $separator . "LIMIT 1)"
		);
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
		foreach (get_rows("SELECT c.relname AS \"Name\", CASE c.relkind WHEN 'r' THEN 'table' WHEN 'm' THEN 'materialized view' ELSE 'view' END AS \"Engine\", pg_relation_size(c.oid) AS \"Data_length\", pg_total_relation_size(c.oid) - pg_relation_size(c.oid) AS \"Index_length\", obj_description(c.oid, 'pg_class') AS \"Comment\", CASE WHEN c.relhasoids THEN 'oid' ELSE '' END AS \"Oid\", c.reltuples as \"Rows\", n.nspname
FROM pg_class c
JOIN pg_namespace n ON(n.nspname = current_schema() AND n.oid = c.relnamespace)
WHERE relkind IN ('r', 'm', 'v', 'f')
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
				$row["default"] = ($match[1] == "NULL" ? null : (($match[1][0] == "'" ? idf_unescape($match[1]) : $match[1]) . $match[2]));
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
		foreach (get_rows("SELECT relname, indisunique::int, indisprimary::int, indkey, indoption , (indpred IS NOT NULL)::int as indispartial FROM pg_index i, pg_class ci WHERE i.indrelid = $table_oid AND ci.oid = i.indexrelid", $connection2) as $row) {
			$relname = $row["relname"];
			$return[$relname]["type"] = ($row["indispartial"] ? "INDEX" : ($row["indisprimary"] ? "PRIMARY" : ($row["indisunique"] ? "UNIQUE" : "INDEX")));
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
		foreach (get_rows("SELECT conname, condeferrable::int AS deferrable, pg_get_constraintdef(oid) AS definition
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
		return array("select" => trim($connection->result("SELECT view_definition
FROM information_schema.views
WHERE table_schema = current_schema() AND table_name = " . q($name))));
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

	function trigger($name, $table = null) {
		if ($name == "") {
			return array("Statement" => "EXECUTE PROCEDURE ()");
		}
		if ($table === null) {
			$table = $_GET['trigger'];
		}
		$rows = get_rows('SELECT t.trigger_name AS "Trigger", t.action_timing AS "Timing", (SELECT STRING_AGG(event_manipulation, \' OR \') FROM information_schema.triggers WHERE event_object_table = t.event_object_table AND trigger_name = t.trigger_name ) AS "Events", t.event_manipulation AS "Event", \'FOR EACH \' || t.action_orientation AS "Type", t.action_statement AS "Statement" FROM information_schema.triggers t WHERE t.event_object_table = ' . q($table) . ' AND t.trigger_name = ' . q($name));
		return reset($rows);
	}

	function triggers($table) {
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.triggers WHERE event_object_table = " . q($table)) as $row) {
			$return[$row["trigger_name"]] = array($row["action_timing"], $row["event_manipulation"]);
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

	function routine($name, $type) {
		$rows = get_rows('SELECT routine_definition AS definition, LOWER(external_language) AS language, *
FROM information_schema.routines
WHERE routine_schema = current_schema() AND specific_name = ' . q($name));
		$return = $rows[0];
		$return["returns"] = array("type" => $return["type_udt_name"]);
		$return["fields"] = get_rows('SELECT parameter_name AS field, data_type AS type, character_maximum_length AS length, parameter_mode AS inout
FROM information_schema.parameters
WHERE specific_schema = current_schema() AND specific_name = ' . q($name) . '
ORDER BY ordinal_position');
		return $return;
	}

	function routines() {
		return get_rows('SELECT specific_name AS "SPECIFIC_NAME", routine_type AS "ROUTINE_TYPE", routine_name AS "ROUTINE_NAME", type_udt_name AS "DTD_IDENTIFIER"
FROM information_schema.routines
WHERE routine_schema = current_schema()
ORDER BY SPECIFIC_NAME');
	}

	function routine_languages() {
		return get_vals("SELECT LOWER(lanname) FROM pg_catalog.pg_language");
	}

	function routine_id($name, $row) {
		$return = array();
		foreach ($row["fields"] as $field) {
			$return[] = $field["type"];
		}
		return idf_escape($name) . "(" . implode(", ", $return) . ")";
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

	function create_sql($table, $auto_increment, $style) {
		global $connection;
		$return = '';
		$return_parts = array();
		$sequences = array();

		$status = table_status($table);
		$fields = fields($table);
		$indexes = indexes($table);
		ksort($indexes);
		$fkeys = foreign_keys($table);
		ksort($fkeys);

		if (!$status || empty($fields)) {
			return false;
		}

		$return = "CREATE TABLE " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " (\n    ";

		// fields' definitions
		foreach ($fields as $field_name => $field) {
			$part = idf_escape($field['field']) . ' ' . $field['full_type']
				. default_value($field)
				. ($field['attnotnull'] ? " NOT NULL" : "");
			$return_parts[] = $part;

			// sequences for fields
			if (preg_match('~nextval\(\'([^\']+)\'\)~', $field['default'], $matches)) {
				$sequence_name = $matches[1];
				$sq = reset(get_rows(min_version(10)
					? "SELECT *, cache_size AS cache_value FROM pg_sequences WHERE schemaname = current_schema() AND sequencename = " . q($sequence_name)
					: "SELECT * FROM $sequence_name"
				));
				$sequences[] = ($style == "DROP+CREATE" ? "DROP SEQUENCE IF EXISTS $sequence_name;\n" : "")
					. "CREATE SEQUENCE $sequence_name INCREMENT $sq[increment_by] MINVALUE $sq[min_value] MAXVALUE $sq[max_value] START " . ($auto_increment ? $sq['last_value'] : 1) . " CACHE $sq[cache_value];";
			}
		}

		// adding sequences before table definition
		if (!empty($sequences)) {
			$return = implode("\n\n", $sequences) . "\n\n$return";
		}

		// primary + unique keys
		foreach ($indexes as $index_name => $index) {
			switch($index['type']) {
				case 'UNIQUE': $return_parts[] = "CONSTRAINT " . idf_escape($index_name) . " UNIQUE (" . implode(', ', array_map('idf_escape', $index['columns'])) . ")"; break;
				case 'PRIMARY': $return_parts[] = "CONSTRAINT " . idf_escape($index_name) . " PRIMARY KEY (" . implode(', ', array_map('idf_escape', $index['columns'])) . ")"; break;
			}
		}

		// foreign keys
		foreach ($fkeys as $fkey_name => $fkey) {
			$return_parts[] = "CONSTRAINT " . idf_escape($fkey_name) . " $fkey[definition] " . ($fkey['deferrable'] ? 'DEFERRABLE' : 'NOT DEFERRABLE');
		}

		$return .= implode(",\n    ", $return_parts) . "\n) WITH (oids = " . ($status['Oid'] ? 'true' : 'false') . ");";

		// "basic" indexes after table definition
		foreach ($indexes as $index_name => $index) {
			if ($index['type'] == 'INDEX') {
				$return .= "\n\nCREATE INDEX " . idf_escape($index_name) . " ON " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " USING btree (" . implode(', ', array_map('idf_escape', $index['columns'])) . ");";
			}
		}

		// coments for table & fields
		if ($status['Comment']) {
			$return .= "\n\nCOMMENT ON TABLE " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . " IS " . q($status['Comment']) . ";";
		}

		foreach ($fields as $field_name => $field) {
			if ($field['comment']) {
				$return .= "\n\nCOMMENT ON COLUMN " . idf_escape($status['nspname']) . "." . idf_escape($status['Name']) . "." . idf_escape($field_name) . " IS " . q($field['comment']) . ";";
			}
		}

		return rtrim($return, ';');
	}

	function truncate_sql($table) {
		return "TRUNCATE " . table($table);
	}

	function trigger_sql($table) {
		$status = table_status($table);
		$return = "";
		foreach (triggers($table) as $trg_id => $trg) {
			$trigger = trigger($trg_id, $status['Name']);
			$return .= "\nCREATE TRIGGER " . idf_escape($trigger['Trigger']) . " $trigger[Timing] $trigger[Events] ON " . idf_escape($status["nspname"]) . "." . idf_escape($status['Name']) . " $trigger[Type] $trigger[Statement];;\n";
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
		return get_rows("SELECT * FROM pg_stat_activity ORDER BY " . (min_version(9.2) ? "pid" : "procpid"));
	}

	function show_status() {
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function support($feature) {
		return preg_match('~^(database|table|columns|sql|indexes|comment|view|' . (min_version(9.3) ? 'materializedview|' : '') . 'scheme|routine|processlist|sequence|trigger|type|variables|drop_col|kill|dump)$~', $feature);
	}

	function kill_process($val) {
		return queries("SELECT pg_terminate_backend(" . number($val) . ")");
	}

	function connection_id(){
		return "SELECT pg_backend_pid()";
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
			number_type() => "+/-",
			"date|time" => "+ interval/- interval", //! escape
			"char|text" => "||",
		)
	);
}
