<?php
namespace Adminer;

add_driver("pgsql", "PostgreSQL");

if (isset($_GET["pgsql"])) {
	define('Adminer\DRIVER', "pgsql");

	if (extension_loaded("pgsql") && $_GET["ext"] != "pdo") {
		class PgsqlDb extends SqlDb {
			public $extension = "PgSQL";
			public $timeout = 0;
			private $link, $string, $database = true;

			// no type declarations, this is passed to set_error_handler()
			function _error($errno, $error) {
				if (ini_bool("html_errors")) {
					$error = html_entity_decode(strip_tags($error));
				}
				$error = preg_replace('~^[^:]*: ~', '', $error);
				$this->error = $error;
			}

			function attach(string $server, string $username, string $password): string {
				$db = adminer()->database();
				set_error_handler(array($this, '_error'));
				list($host, $port) = host_port($server);
				$this->string = "host='$host'" . ($port ? " port=$port" : "") . " user='" . addcslashes($username, "'\\") . "' password='" . addcslashes($password, "'\\") . "'";
				$ssl = adminer()->connectSsl();
				if (isset($ssl["mode"])) {
					$this->string .= " sslmode=$ssl[mode]";
				}
				$this->link = @pg_connect("$this->string dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'", PGSQL_CONNECT_FORCE_NEW);
				if (!$this->link && $db != "") {
					// try to connect directly with database for performance
					$this->database = false;
					$this->link = @pg_connect("$this->string dbname='postgres'", PGSQL_CONNECT_FORCE_NEW);
				}
				restore_error_handler();
				if ($this->link) {
					pg_set_client_encoding($this->link, "UTF8");
				}
				return ($this->link ? '' : $this->error);
			}

			function quote(string $string): string {
				return (function_exists('pg_escape_literal')
					? pg_escape_literal($this->link, $string) // available since PHP 5.4.4
					: "'" . pg_escape_string($this->link, $string) . "'"
				);
			}

			function value(?string $val, array $field): ?string {
				return ($field["type"] == "bytea" && $val !== null ? pg_unescape_bytea($val) : $val);
			}

			function select_db(string $database) {
				if ($database == adminer()->database()) {
					return $this->database;
				}
				$return = @pg_connect("$this->string dbname='" . addcslashes($database, "'\\") . "'", PGSQL_CONNECT_FORCE_NEW);
				if ($return) {
					$this->link = $return;
				}
				return $return;
			}

			function close(): void {
				$this->link = @pg_connect("$this->string dbname='postgres'");
			}

			function query(string $query, bool $unbuffered = false) {
				if (self::$untrusted) {
					// the extended protocol doesn't allow multiple commands, the transaction doesn't allow modifying data
					$result = (@pg_query($this->link, "BEGIN READ ONLY")
						? @pg_query_params($this->link, $query, array())
						: false
					);
				} else {
					$result = @pg_query($this->link, $query);
				}
				$this->error = "";
				if (!$result) {
					$this->error = pg_last_error($this->link);
					$return = false;
				} elseif (!pg_num_fields($result)) {
					$this->affected_rows = pg_affected_rows($result);
					$return = true;
				} else {
					$return = new Result($result);
				}
				if (self::$untrusted) {
					@pg_query($this->link, "COMMIT"); // rollbacks the transaction if the query failed
				}
				if ($this->timeout) {
					$this->timeout = 0;
					$this->query("RESET statement_timeout");
				}
				return $return;
			}

			function warnings() {
				if (PHP_VERSION_ID >= 70100) {
					$return = implode("\n", pg_last_notice($this->link, PGSQL_NOTICE_ALL));
					pg_last_notice($this->link, PGSQL_NOTICE_CLEAR);
				} else {
					$return = pg_last_notice($this->link);
				}
				return nl_br(h($return));
			}

			function inTransaction(): bool {
				$status = pg_transaction_status($this->link);
				return $status == PGSQL_TRANSACTION_INTRANS || $status == PGSQL_TRANSACTION_INERROR; // not PGSQL_TRANSACTION_UNKNOWN - the connection is bad
			}

			/** Copy from array into a table
			* @param list<string> $rows
			*/
			function copyFrom(string $table, array $rows): bool {
				$this->error = '';
				set_error_handler(function (int $errno, string $error): bool {
					$this->error = (ini_bool('html_errors') ? html_entity_decode($error) : $error);
					return true;
				});
				$return = pg_copy_from($this->link, $table, $rows);
				restore_error_handler();
				return $return;
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0;

			function __construct($result) {
				$this->result = $result;
				$this->num_rows = pg_num_rows($result);
			}

			function fetch_assoc() {
				return pg_fetch_assoc($this->result);
			}

			function fetch_row() {
				return pg_fetch_row($this->result);
			}

			function fetch_field(): \stdClass {
				$column = $this->offset++;
				$return = new \stdClass;
				$return->orgtable = pg_field_table($this->result, $column);
				$return->name = pg_field_name($this->result, $column);
				$type = pg_field_type($this->result, $column);
				$return->native_type = $type;
				$return->type = (preg_match(number_type(), $type) ? 0 : 15);
				$return->charsetnr = ($type == "bytea" ? 63 : 0); // 63 - binary
				return $return;
			}
		}

	} elseif (extension_loaded("pdo_pgsql")) {
		class PgsqlDb extends PdoDb {
			public $extension = "PDO_PgSQL";
			public $timeout = 0;

			function attach(string $server, string $username, string $password): string {
				$db = adminer()->database();
				list($host, $port) = host_port($server);
				//! client_encoding is supported since 9.1, but we can't yet use min_version here
				$dsn = "pgsql:host='$host'" . ($port ? " port=$port" : "") . " client_encoding=utf8 dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'";
				$ssl = adminer()->connectSsl();
				if (isset($ssl["mode"])) {
					$dsn .= " sslmode=$ssl[mode]";
				}
				return $this->dsn($dsn, $username, $password);
			}

			function select_db(string $database) {
				return (adminer()->database() == $database);
			}

			function query(string $query, bool $unbuffered = false) {
				$return = (self::$untrusted ? $this->readOnlyQuery($query) : parent::query($query, $unbuffered));
				if ($this->timeout) {
					$this->timeout = 0;
					parent::query("RESET statement_timeout");
				}
				return $return;
			}

			/** Send a query which can't modify data
			* @return PdoResult|bool
			*/
			private function readOnlyQuery(string $query) {
				$this->error = "";
				if (!$this->pdo->query("BEGIN READ ONLY")) {
					list(, $this->errno, $this->error) = $this->pdo->errorInfo();
					return false;
				}
				/** @var PdoResult|false */
				$result = $this->pdo->prepare($query); // a prepared statement doesn't allow multiple commands
				$return = false;
				if ($result && $result->execute()) {
					$this->store_result($result);
					$return = $result;
				} else {
					// prepare() succeeds even for an invalid query, the error is reported by execute()
					list(, $this->errno, $this->error) = ($result ? $result->errorInfo() : $this->pdo->errorInfo());
					if (!$this->error) {
						$this->error = lang('Unknown error.');
					}
				}
				$this->pdo->query("COMMIT"); // rollbacks the transaction if the query failed
				return $return;
			}

			function warnings() {
				// not implemented in PDO_PgSQL as of PHP 7.2.1
			}

			function copyFrom(string $table, array $rows): bool {
				$return = $this->pdo->pgsqlCopyFromArray($table, $rows);
				$this->error = idx($this->pdo->errorInfo(), 2) ?: '';
				return $return;
			}

			function close(): void {
			}
		}

	}



	if (class_exists('Adminer\PgsqlDb')) {
		class Db extends PgsqlDb {
			function multi_query(string $query) {
				if (preg_match('~\bCOPY\s+(.+?)\s+FROM\s+stdin;\n?(.*)\n\\\\\.$~is', str_replace("\r\n", "\n", $query), $match)) { // no ^ to allow leading comments
					$rows = explode("\n", $match[2]);
					$this->multi = false; // the result of the previous query would overwrite affected_rows in PdoDb::store_result()
					$this->affected_rows = count($rows);
					return $this->copyFrom($match[1], $rows);
				}
				return parent::multi_query($query);
			}
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("PgSQL", "PDO_PgSQL");
		static $jush = "pgsql";

		//! SQL - same-site CSRF
		public $operators = array("=", "<", ">", "<=", ">=", "!=", "~", "~*", "!~", "LIKE", "LIKE %%", "ILIKE", "ILIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT ILIKE", "NOT IN", "IS NOT NULL", "SQL");
		public $functions = array("char_length", "lower", "round", "to_hex", "to_timestamp", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");

		public string $nsOid = "(SELECT oid FROM pg_namespace WHERE nspname = current_schema())";
		/** @var int[] */ private array $userTypes = array(); // [$name => $oid]

		static function connect(string $server, string $username, string $password) {
			$connection = parent::connect($server, $username, $password);
			if (is_string($connection)) {
				return $connection;
			}
			$version = get_val("SELECT version()", 0, $connection);
			$connection->flavor = (preg_match('~CockroachDB~', $version) ? 'cockroach' : '');
			$connection->server_info = preg_replace('~^\D*([\d.]+[-\w]*).*~', '\1', $version);
			if (min_version(9, 0, $connection)) {
				$connection->query("SET application_name = 'Adminer'");
			}
			if ($connection->flavor == 'cockroach') { // we don't use "PostgreSQL / CockroachDB" by default because it's too long
				add_driver(DRIVER, "CockroachDB");
			}
			return $connection;
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! arrays
				lang('Numbers') => array("smallint" => 5, "integer" => 10, "bigint" => 19, "boolean" => 1, "numeric" => 0, "real" => 7, "double precision" => 16, "money" => 20),
				lang('Date and time') => array("date" => 13, "time" => 17, "timestamp" => 20, "timestamptz" => 21, "interval" => 0),
				lang('Strings') => array("character" => 0, "character varying" => 0, "text" => 0, "tsquery" => 0, "tsvector" => 0, "uuid" => 0, "xml" => 0),
				lang('Binary') => array("bit" => 0, "bit varying" => 0, "bytea" => 0),
				lang('Network') => array("cidr" => 43, "inet" => 43, "macaddr" => 17, "macaddr8" => 23, "txid_snapshot" => 0),
				lang('Geometry') => array("box" => 0, "circle" => 0, "line" => 0, "lseg" => 0, "path" => 0, "point" => 0, "polygon" => 0),
			);
			if (min_version(9.2, 0, $connection)) {
				$this->types[lang('Strings')]["json"] = 4294967295;
				$this->types[lang('Ranges')] = array("int4range" => 0, "int8range" => 0, "numrange" => 0, "daterange" => 0, "tsrange" => 0, "tstzrange" => 0); //! multiranges since 14
				if (min_version(9.4, 0, $connection)) {
					$this->types[lang('Strings')]["jsonb"] = 4294967295;
				}
			}
			$this->insertFunctions = array(
				"char" => "md5",
				"date|time" => "now",
			);
			$this->editFunctions = array(
				number_type() => "+/-",
				"date|time" => "+ interval/- interval", //! escape
				"char|text" => "||",
			);
			if (min_version(12, 0, $connection)) {
				$this->generated[] = "STORED";
				if (min_version(18, 0, $connection)) {
					$this->generated[] = "VIRTUAL";
				}
			}
			$this->partitionBy = array("RANGE", "LIST");
			if (!$connection->flavor) {
				$this->partitionBy[] = "HASH";
			}
		}

		function enumLength(array $field) {
			$oid = $this->userTypes[$field["type"]];
			return ($oid ? type_values($oid) : "");
		}

		function setUserTypes(array $types): void {
			$this->userTypes = array_flip($types);
			// $this->types holds maximum lengths, user types have none
			$this->types[lang('User types')] = array_fill_keys(array_keys($this->userTypes), 0);
		}

		function insertReturning(string $table): string {
			$auto_increment = array_filter(fields($table), function ($field) {
				return $field['auto_increment'];
			});
			return (count($auto_increment) == 1 ? " RETURNING " . idf_escape(key($auto_increment)) : "");
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$columns = array_keys(reset($rows));
			$conflict = array();
			$update = array();
			foreach ($columns as $key) {
				if (isset($primary[idf_unescape($key)])) {
					$conflict[] = $key;
				} else {
					$update[] = "$key = EXCLUDED.$key";
				}
			}
			// ON CONFLICT is supported since PostgreSQL 9.5 and it needs all the conflicting columns
			if (!$conflict || !min_version(9.5) || count($conflict) != count($primary)) {
				return parent::insertUpdate($table, $rows, $primary);
			}
			$prefix = "INSERT INTO " . table($table) . " (" . implode(", ", $columns) . ") VALUES\n";
			$suffix = "\nON CONFLICT (" . implode(", ", $conflict) . ")" . ($update ? " DO UPDATE SET " . implode(", ", $update) : " DO NOTHING");
			$values = array();
			$length = 0;
			foreach ($rows as $set) {
				$value = "(" . implode(", ", $set) . ")";
				if ($values && strlen($prefix) + $length + strlen($value) + strlen($suffix) > 1e6) { // 1e6 - the same limit as in MySQL, PostgreSQL has none
					if (!queries($prefix . implode(",\n", $values) . $suffix)) {
						return false;
					}
					$values = array();
					$length = 0;
				}
				$values[] = $value;
				$length += strlen($value) + 2; // 2 - strlen(",\n")
			}
			return queries($prefix . implode(",\n", $values) . $suffix);
		}

		function slowQuery(string $query, int $timeout) {
			$this->conn->query("SET statement_timeout = " . (1000 * $timeout));
			$this->conn->timeout = 1000 * $timeout;
			return $query;
		}

		function convertSearch(string $idf, array $val, array $field): string {
			// comparing the text representation never fails, use the column as is only where the text would be compared differently
			$pattern = preg_match('(LIKE|^!?~)', $val["op"]); // the parentheses are the delimiters because ~ is an operator
			// the types are listed instead of matching substrings because a user type can be named e.g. enum and it supports no operator
			$native = preg_match('~^(character( varying)?|text|citext|bpchar|name)$~', $field["type"])
				|| (!$pattern && preg_match('~' . number_type() . '|^(date|time|timetz|timestamp|timestamptz|boolean)$~', $field["type"]));
			return ($native && !preg_match('~\[]$~', $field["full_type"]) // an array has the type of its elements
				? $idf
				: "CAST($idf AS text)"
			);
		}

		function quoteBinary(string $s): string {
			return "'\\x" . bin2hex($s) . "'"; // available since PostgreSQL 8.1
		}

		function warnings() {
			return $this->conn->warnings();
		}

		function tableHelp(string $name, bool $is_view = false) {
			$links = array(
				"information_schema" => "infoschema",
				"pg_catalog" => ($is_view ? "view" : "catalog"),
			);
			$link = $links[$_GET["ns"]];
			if ($link) {
				return "$link-" . str_replace("_", "-", $name) . ".html";
			}
		}

		function inheritsFrom(string $table): array {
			return get_rows(
				"SELECT relname AS table, nspname AS ns FROM pg_class JOIN pg_inherits ON inhparent = oid JOIN pg_namespace ON relnamespace = pg_namespace.oid WHERE inhrelid = "
					. $this->tableOid($table) . " ORDER BY 2, 1"
			);
		}

		function inheritedTables(string $table): array {
			return get_rows(
				"SELECT relname AS table, nspname AS ns FROM pg_inherits JOIN pg_class ON inhrelid = oid JOIN pg_namespace ON relnamespace = pg_namespace.oid WHERE inhparent = "
					. $this->tableOid($table) . " ORDER BY 2, 1"
			);
		}

		function partitionsInfo(string $table): array {
			$row = (min_version(10) ? $this->conn->query("SELECT * FROM pg_partitioned_table WHERE partrelid = " . $this->tableOid($table))->fetch_assoc() : null);
			if ($row) {
				$attrs = get_vals("SELECT attname FROM pg_attribute WHERE attrelid = $row[partrelid] AND attnum IN (" . str_replace(" ", ", ", $row["partattrs"]) . ")"); //! ordering
				$by = array('h' => 'HASH', 'l' => 'LIST', 'r' => 'RANGE');
				return array(
					"partition_by" => $by[$row["partstrat"]],
					"partition" => implode(", ", array_map('Adminer\idf_escape', $attrs)),
				);
			}
			return array();
		}

		function tableOid(string $table): string {
			return "(SELECT oid FROM pg_class WHERE relnamespace = $this->nsOid AND relname = " . q($table) . " AND relkind IN ('r', 'm', 'v', 'f', 'p'))";
		}

		function indexAlgorithms(array $tableStatus): array {
			static $return = array();
			if (!$return) {
				$return = get_vals("SELECT amname FROM pg_am" . (min_version(9.6) ? " WHERE amtype = 'i'" : "")
					. " ORDER BY amname = '" . ($this->conn->flavor == 'cockroach' ? "prefix" : "btree") . "' DESC, amname");
			}
			return $return;
		}

		function indexOpclasses(): array {
			static $return = array();
			if (!$return && $this->conn->flavor != 'cockroach') {
				$return = get_vals("SELECT DISTINCT opcname FROM pg_catalog.pg_opclass WHERE NOT opcdefault ORDER BY opcname");
			}
			return $return;
		}

		function supportsIndex(array $table_status): bool {
			// returns true for "materialized view"
			return $table_status["Engine"] != "view";
		}

		function hasCStyleEscapes(): bool {
			static $c_style;
			if ($c_style === null) {
				$c_style = (get_val("SHOW standard_conforming_strings", 0, $this->conn) == "off");
			}
			return $c_style;
		}
	}



	function idf_escape(string $idf): string {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table(string $idf): string {
		return idf_escape($idf);
	}

	function get_databases(bool $flush): array {
		return get_vals("SELECT datname FROM pg_database
WHERE datallowconn = TRUE AND has_database_privilege(datname, 'CONNECT')
ORDER BY datname");
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return " $query$where" . ($limit ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return (preg_match('~^INTO~', $query)
			? limit($query, $where, 1, 0, $separator)
			: " $query" . (is_view(table_status1($table)) ? $where : $separator . "WHERE ctid = (SELECT ctid FROM " . table($table) . $where . $separator . "LIMIT 1)")
		);
	}

	function db_collation(string $db, array $collations): string {
		return get_val("SELECT datcollate FROM pg_database WHERE datname = " . q($db));
	}

	function logged_user(): string {
		return get_val("SELECT user");
	}

	function tables_list(): array {
		$query = "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = current_schema()";
		if (support("materializedview")) {
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

	function count_tables(array $databases): array {
		$return = array();
		foreach ($databases as $db) {
			if (connection()->select_db($db)) {
				$return[$db] = count(tables_list());
			}
		}
		return $return;
	}

	function table_status(string $name = "", bool $fast = false): array {
		static $has_size;
		if ($has_size === null) {
			// https://github.com/cockroachdb/cockroach/issues/40391
			$has_size = get_val("SELECT 'pg_table_size'::regproc");
		}
		$sequences = (!$fast && min_version(10)); // pg_sequences is available since PostgreSQL 10
		$return = array();
		foreach (
			get_rows("SELECT
	relname AS \"Name\",
	CASE relkind WHEN 'v' THEN 'view' WHEN 'm' THEN 'materialized view' ELSE 'table' END AS \"Engine\"" . ($has_size ? ",
	pg_table_size(c.oid) AS \"Data_length\",
	pg_indexes_size(c.oid) AS \"Index_length\"" : "") . ",
	obj_description(c.oid, 'pg_class') AS \"Comment\",
	" . (min_version(12) ? "''" : "CASE WHEN relhasoids THEN 'oid' ELSE '' END") . " AS \"Oid\",
	reltuples AS \"Rows\",
	" . ($sequences ? "seq.last_value" : "NULL") . " AS \"Auto_increment\",
	" . (min_version(10) ? "relispartition::int AS partition," : "") . "
	current_schema() AS nspname
FROM pg_class c
" . ($sequences ? "LEFT JOIN (
	SELECT d.refobjid, max(s.last_value) AS last_value
	FROM pg_depend d
	JOIN pg_class sc ON sc.oid = d.objid AND sc.relkind = 'S' AND sc.relnamespace = " . driver()->nsOid . "
	JOIN pg_sequences s ON s.schemaname = current_schema() AND s.sequencename = sc.relname
	WHERE d.classid = 'pg_class'::regclass AND d.refclassid = 'pg_class'::regclass AND d.deptype IN ('a', 'i')
	" . ($name != "" ? "AND d.refobjid = " . driver()->tableOid($name) : "") . "
	GROUP BY d.refobjid
) seq ON seq.refobjid = c.oid
" : "") . "WHERE relkind IN ('r', 'm', 'v', 'f', 'p')
AND relnamespace = " . driver()->nsOid . "
" . ($name != "" ? "AND relname = " . q($name) : "ORDER BY relname")) as $row
		) {
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	function is_view(array $table_status): bool {
		return in_array($table_status["Engine"], array("view", "materialized view"));
	}

	function fk_support(array $table_status): bool {
		return true;
	}

	function fields(string $table): array {
		$return = array();
		$aliases = array(
			'timestamp without time zone' => 'timestamp',
			'timestamp with time zone' => 'timestamptz',
			'time without time zone' => 'time',
			'time with time zone' => 'timetz',
		);
		foreach (
			get_rows("SELECT
	a.attname AS field,
	format_type(a.atttypid, a.atttypmod) AS full_type,
	pg_get_expr(d.adbin, d.adrelid) AS default,
	a.attnotnull::int,
	i.indrelid AS primary,
	t.typcategory,
	col_description(a.attrelid, a.attnum) AS comment" . (min_version(10) ? ",
	a.attidentity" . (min_version(12) ? ",
	a.attgenerated" : "") : "") . "
FROM pg_attribute a
JOIN pg_type t ON t.oid = a.atttypid
LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
LEFT JOIN pg_index i ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) AND i.indisprimary
WHERE a.attrelid = " . driver()->tableOid($table) . "
AND NOT a.attisdropped
AND a.attnum > 0
ORDER BY a.attnum") as $row
		) {
			//! collation
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
			if (in_array($row['attidentity'], array('a', 'd'))) {
				$row['default'] = 'GENERATED ' . ($row['attidentity'] == 'd' ? 'BY DEFAULT' : 'ALWAYS') . ' AS IDENTITY';
			}
			$row["generated"] = idx(array("s" => "STORED", "v" => "VIRTUAL"), $row["attgenerated"], "");
			$row["composite"] = ($row["typcategory"] == "C"); // 'C' - a composite type, a domain over it or a table row type; an array of them is 'A'
			$row["null"] = !$row["attnotnull"];
			$row["auto_increment"] = $row['attidentity'] || preg_match('~^nextval\(~i', $row["default"])
				|| preg_match('~^unique_rowid\(~', $row["default"]); // CockroachDB
			$row["privileges"] = array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1);
			if (!$row['generated'] && preg_match('~(.+)::[^,)]+(.*)~', $row["default"], $match)) {
				$row["default"] = ($match[1] == "NULL" ? null : idf_unescape($match[1]) . $match[2]);
			}
			$return[$row["field"]] = $row;
		}
		return $return;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		$connection2 = connection($connection2);
		$return = array();
		$table_oid = driver()->tableOid($table);
		$columns = get_key_vals("SELECT attnum, attname FROM pg_attribute WHERE attrelid = $table_oid AND attnum > 0", $connection2);
		foreach (
			get_rows("SELECT relname, indisunique::int, indisprimary::int, indkey, indoption, amname,
	pg_get_expr(indpred, indrelid, true) AS partial, pg_get_expr(indexprs, indrelid) AS indexpr" . ($connection2->flavor == 'cockroach' ? "" : ",
	(SELECT string_agg(CASE WHEN opcdefault THEN '' ELSE opcname END, ' ' ORDER BY s)
		FROM generate_subscripts(indclass, 1) AS s JOIN pg_catalog.pg_opclass ON pg_opclass.oid = indclass[s]) AS opclasses") . "
FROM pg_index
JOIN pg_class ON indexrelid = oid
JOIN pg_am ON pg_am.oid = pg_class.relam
WHERE indrelid = $table_oid
ORDER BY indisprimary DESC, indisunique DESC", $connection2) as $row
		) {
			$relname = $row["relname"];
			$return[$relname]["type"] = ($row["indisprimary"] ? "PRIMARY" : ($row["indisunique"] ? "UNIQUE" : "INDEX"));
			$return[$relname]["columns"] = array();
			$return[$relname]["descs"] = array();
			$return[$relname]["algorithm"] = $row["amname"];
			$return[$relname]["partial"] = $row["partial"];
			$indexpr = preg_split('~(?<=\)), (?=\()~', $row["indexpr"]); //! '), (' used in expression
			foreach (explode(" ", $row["indkey"]) as $indkey) {
				$return[$relname]["columns"][] = ($indkey ? $columns[$indkey] : array_shift($indexpr));
			}
			foreach (explode(" ", $row["indoption"]) as $indoption) {
				$return[$relname]["descs"][] = (intval($indoption) & 1 ? '1' : null); // 1 - INDOPTION_DESC
			}
			$return[$relname]["opclasses"] = ($row["opclasses"] != "" ? explode(" ", $row["opclasses"]) : array());
			$return[$relname]["lengths"] = array();
		}
		return $return;
	}

	function foreign_keys(string $table): array {
		$return = array();
		foreach (
			get_rows("SELECT conname, condeferrable::int AS deferrable, condeferred::int AS deferred, pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid = " . driver()->tableOid($table) . "
AND contype = 'f'::char
ORDER BY conkey, conname") as $row
		) {
			$row['deferrable'] = ($row['deferrable'] ? '' : 'NOT ') . 'DEFERRABLE' . ($row['deferred'] ? ' INITIALLY DEFERRED' : '');
			if (preg_match('~FOREIGN KEY\s*\((.+)\)\s*REFERENCES (.+)\((.+)\)(.*)$~iA', $row['definition'], $match)) {
				$row['source'] = array_map('Adminer\idf_unescape', array_map('trim', explode(',', $match[1])));
				if (preg_match('~^(("([^"]|"")+"|[^"]+)\.)?"?("([^"]|"")+"|[^"]+)$~', $match[2], $match2)) {
					$row['ns'] = idf_unescape($match2[2]);
					$row['table'] = idf_unescape($match2[4]);
				}
				$row['target'] = array_map('Adminer\idf_unescape', array_map('trim', explode(',', $match[3])));
				$row['on_delete'] = (preg_match("~ON DELETE (" . driver()->onActions . ")~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$row['on_update'] = (preg_match("~ON UPDATE (" . driver()->onActions . ")~", $match[4], $match2) ? $match2[1] : 'NO ACTION');
				$return[$row['conname']] = $row;
			}
		}
		return $return;
	}

	function view(string $name): array {
		return array("select" => trim(get_val("SELECT pg_get_viewdef(" . driver()->tableOid($name) . ")")));
	}

	function collations(): array {
		//! supported in CREATE DATABASE
		return array();
	}

	function information_schema(string $db, string $schema = ""): bool {
		// pg_temp_* holds the session's temporary tables so it is writable
		return in_array($schema != "" ? $schema : get_schema(), array("information_schema", "pg_catalog", "pg_toast"));
	}

	function error(): string {
		$return = h(connection()->error);
		if (preg_match('~^(.*\n)?([^\n]*)\n( *)\^(\n.*)?$~s', $return, $match)) {
			$return = $match[1] . preg_replace('~((?:[^&]|&[^;]*;){' . strlen($match[3]) . '})(.*)~', '\1<b>\2</b>', $match[2]) . $match[4];
		}
		return nl_br($return);
	}

	function create_database(string $db, string $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " ENCODING " . idf_escape($collation) : ""));
	}

	function drop_databases(array $databases): bool {
		connection()->close();
		return apply_queries("DROP DATABASE", $databases, 'Adminer\idf_escape');
	}

	function rename_database(string $name, string $collation): bool {
		connection()->close();
		return !!queries("ALTER DATABASE " . idf_escape(DB) . " RENAME TO " . idf_escape($name));
	}

	function auto_increment(): string {
		return "";
	}

	function alter_table(string $table, string $name, array $fields, array $foreign, ?string $comment, string $engine, string $collation, string $auto_increment, ?array $partitioning) {
		$alter = array();
		$queries = array();
		if ($table != "" && $table != $name) {
			$queries[] = "ALTER TABLE " . table($table) . " RENAME TO " . table($name);
		}
		$sequence = "";
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter[] = "DROP $column";
			} else {
				$val5 = $val[5];
				unset($val[5]);
				if ($field[0] == "") {
					if (isset($val[6])) { // auto_increment
						$val[1] = ($val[1] == " bigint" ? " big" : ($val[1] == " smallint" ? " small" : " ")) . "serial";
					}
					$alter[] = ($table != "" ? "ADD " : "  ") . implode($val);
					if (isset($val[6])) {
						$alter[] = ($table != "" ? "ADD" : " ") . " PRIMARY KEY ($val[0])";
					}
				} else {
					if ($column != $val[0]) {
						$queries[] = "ALTER TABLE " . table($name) . " RENAME $column TO $val[0]";
					}
					$alter[] = "ALTER $column TYPE$val[1]";
					$sequence_name = $table . "_" . idf_unescape($val[0]) . "_seq";
					$alter[] = "ALTER $column " . ($val[3] ? "SET" . preg_replace('~GENERATED ALWAYS(.*) (STORED|VIRTUAL)~', 'EXPRESSION\1', $val[3])
						: (isset($val[6]) ? "SET DEFAULT nextval(" . q($sequence_name) . ")"
						: "DROP DEFAULT" //! change to DROP EXPRESSION with generated columns
					));
					if (isset($val[6])) {
						$sequence = "CREATE SEQUENCE IF NOT EXISTS " . idf_escape($sequence_name) . " OWNED BY " . idf_escape($table) . ".$val[0]";
					}
					$alter[] = "ALTER $column " . ($val[2] == " NULL" ? "DROP NOT" : "SET") . $val[2];
				}
				if ($field[0] != "" || $val5 != "") {
					$queries[] = "COMMENT ON COLUMN " . table($name) . ".$val[0] IS " . ($val5 != "" ? substr($val5, 9) : "''");
				}
			}
		}
		$alter = array_merge($alter, $foreign);
		if ($table == "") {
			$status = "";
			if ($partitioning) {
				$cockroach = (connection()->flavor == 'cockroach');
				$status = " PARTITION BY $partitioning[partition_by]($partitioning[partition])";
				if ($partitioning["partition_by"] == 'HASH') {
					$partitions = +$partitioning["partitions"];
					for ($i=0; $i < $partitions; $i++) {
						$queries[] = "CREATE TABLE " . idf_escape($name . "_$i") . " PARTITION OF " . idf_escape($name) . " FOR VALUES WITH (MODULUS $partitions, REMAINDER $i)";
					}
				} else {
					$prev = "MINVALUE";
					foreach ($partitioning["partition_names"] as $i => $val) {
						$value = $partitioning["partition_values"][$i];
						$partition = " VALUES " . ($partitioning["partition_by"] == 'LIST' ? "IN ($value)" : "FROM ($prev) TO ($value)");
						if ($cockroach) {
							$status .= ($i ? "," : " (") . "\n  PARTITION " . (preg_match('~^DEFAULT$~i', $val) ? $val : idf_escape($val)) . "$partition";
						} else {
							$queries[] = "CREATE TABLE " . idf_escape($name . "_$val") . " PARTITION OF " . idf_escape($name) . " FOR$partition";
						}
						$prev = $value;
					}
					$status .= ($cockroach ? "\n)" : "");
				}
			}
			array_unshift($queries, "CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status");
		} elseif ($alter) {
			array_unshift($queries, "ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter));
		}
		if ($sequence) {
			array_unshift($queries, $sequence);
		}
		if ($comment !== null) {
			$queries[] = "COMMENT ON TABLE " . table($name) . " IS " . q($comment);
		}
		foreach ($queries as $query) {
			if (!queries($query)) {
				return false;
			}
		}
		if ($auto_increment != "") {
			// the auto increment column is found after the alter because it could be added or renamed by it
			foreach (fields($name) as $field_name => $field) {
				if ($field["auto_increment"]) {
					return !!queries("SELECT setval(pg_get_serial_sequence(" . q(table($name)) . ", " . q($field_name) . "), $auto_increment)");
				}
			}
		}
		return true;
	}

	function alter_indexes(string $table, $alter) {
		$create = array();
		$drop = array();
		$queries = array();
		foreach ($alter as $val) {
			if ($val[0] != "INDEX") {
				//! descending UNIQUE indexes result in syntax error
				$create[] = ($val[2] == "DROP"
					? "\nDROP CONSTRAINT " . idf_escape($val[1])
					: "\nADD" . ($val[1] != "" ? " CONSTRAINT " . idf_escape($val[1]) : "") . " $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . "(" . implode(", ", $val[2]) . ")"
				);
			} elseif ($val[2] == "DROP") {
				$drop[] = idf_escape($val[1]);
			} else {
				$queries[] = "CREATE INDEX " . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_"))
					. " ON " . table($table)
					. ($val[3] ? " USING $val[3]" : "")
					. " (" . implode(", ", $val[2]) . ")"
					. ($val[4] ? " WHERE $val[4]" : "")
				;
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

	function truncate_tables(array $tables): bool {
		return !!queries("TRUNCATE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	/** Group tables and views by the command dropping them
	* @param TableStatus[] $tables
	* @return array<string, list<string>> [$kind => [$table]] dropping the objects of one kind by a single command succeeds even if they depend on each other
	*/
	function drop_kinds(array $tables): array {
		$return = array("MATERIALIZED VIEW" => array(), "VIEW" => array(), "TABLE" => array()); // views are dropped first, they can depend on the tables
		foreach ($tables as $name => $table_status) {
			//! foreign tables have Engine 'table' but they need DROP FOREIGN TABLE
			$return[strtoupper($table_status["Engine"])][] = idf_escape($table_status["nspname"]) . "." . table($name);
		}
		return array_filter($return);
	}

	function drop_views(array $views) {
		return drop_tables($views);
	}

	function drop_tables(array $tables) {
		$statuses = array();
		foreach ($tables as $table) {
			$statuses[$table] = table_status1($table);
		}
		foreach (drop_kinds($statuses) as $kind => $names) {
			if (!queries("DROP $kind " . implode(", ", $names))) {
				return false;
			}
		}
		return true;
	}

	function move_tables(array $tables, array $views, string $target): bool {
		foreach (array_merge($tables, $views) as $table) {
			$status = table_status1($table);
			if (!queries("ALTER " . strtoupper($status["Engine"]) . " " . table($table) . " SET SCHEMA " . idf_escape($target))) {
				return false;
			}
		}
		return true;
	}

	function trigger(string $name, string $table): array {
		if ($name == "") {
			return array("Statement" => "EXECUTE PROCEDURE ()");
		}
		$columns = array();
		$where = "WHERE trigger_schema = current_schema() AND event_object_table = " . q($table) . " AND trigger_name = " . q($name);
		foreach (get_rows("SELECT * FROM information_schema.triggered_update_columns $where") as $row) {
			$columns[] = $row["event_object_column"];
		}
		$return = array();
		foreach (
			get_rows('SELECT trigger_name AS "Trigger", action_timing AS "Timing", event_manipulation AS "Event", \'FOR EACH \' || action_orientation AS "Type", action_statement AS "Statement"
FROM information_schema.triggers' . "
$where
ORDER BY event_manipulation DESC") as $row
		) {
			if ($columns && $row["Event"] == "UPDATE") {
				$row["Event"] .= " OF";
			}
			$row["Of"] = implode(", ", $columns);
			if ($return) {
				$row["Event"] .= " OR $return[Event]";
			}
			$return = $row;
		}
		return $return;
	}

	function triggers(string $table): array {
		$return = array();
		foreach (get_rows("SELECT * FROM information_schema.triggers WHERE trigger_schema = current_schema() AND event_object_table = " . q($table)) as $row) {
			$trigger = trigger($row["trigger_name"], $table);
			$return[$trigger["Trigger"]] = array($trigger["Timing"], $trigger["Event"]);
		}
		return $return;
	}

	function trigger_options(): array {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array(
				"INSERT", "UPDATE", "UPDATE OF", "DELETE",
				"INSERT OR UPDATE", "INSERT OR UPDATE OF", "DELETE OR INSERT", "DELETE OR UPDATE", "DELETE OR UPDATE OF",
				"DELETE OR INSERT OR UPDATE", "DELETE OR INSERT OR UPDATE OF",
			),
			"Type" => array("FOR EACH ROW", "FOR EACH STATEMENT"),
		);
	}

	function routine(string $name, string $type): array {
		$rows = get_rows('SELECT routine_definition AS definition, LOWER(external_language) AS language, *
FROM information_schema.routines
WHERE routine_schema = current_schema() AND specific_name = ' . q($name));
		$return = idx($rows, 0, array());
		$return["returns"] = array("type" => preg_replace('~^_(.*)~', '\1[]', "$return[type_udt_name]")); // _int4 - array of int4
		$return["fields"] = get_rows("SELECT COALESCE(parameter_name, ordinal_position::text) AS field,
	CASE data_type WHEN 'USER-DEFINED' THEN udt_name WHEN 'ARRAY' THEN substr(udt_name, 2) || '[]' ELSE data_type END AS type,
	character_maximum_length AS length, parameter_mode AS inout
FROM information_schema.parameters
WHERE specific_schema = current_schema() AND specific_name = " . q($name) . "
ORDER BY ordinal_position");
		return $return;
	}

	function routines(): array {
		return get_rows('SELECT specific_name AS "SPECIFIC_NAME", routine_type AS "ROUTINE_TYPE", routine_name AS "ROUTINE_NAME", type_udt_name AS "DTD_IDENTIFIER"
FROM information_schema.routines
WHERE routine_schema = current_schema()' . (connection()->flavor == 'cockroach' ? '' : "
AND substring(specific_name, '[0-9]+\$')::oid NOT IN (SELECT objid FROM pg_catalog.pg_depend WHERE classid = 'pg_proc'::regclass AND deptype = 'e')") . '
ORDER BY SPECIFIC_NAME'); // 'e' - functions created by extensions
	}

	function routine_languages(): array {
		$return = array();
		foreach (get_vals("SELECT LOWER(lanname) FROM pg_catalog.pg_language") as $language) {
			$return[$language] = (preg_match('~sql$~', $language) ? "pgsql" : "txt"); // we don't ship the highlighters of the other languages, e.g. plperl or c
		}
		return $return;
	}

	function routine_id(string $name, array $row): string {
		$return = array();
		foreach ($row["fields"] as $field) {
			$length = $field["length"];
			$return[] = $field["type"] . ($length ? "($length)" : "");
		}
		return idf_escape($name) . "(" . implode(", ", $return) . ")";
	}

	function last_id($result): string {
		$row = (is_object($result) ? $result->fetch_row() : array());
		return ($row ? $row[0] : 0);
	}

	function explain(Db $connection, string $query) {
		return $connection->query("EXPLAIN $query");
	}

	function found_rows(array $table_status, array $where) {
		if (preg_match("~ rows=([0-9]+)~", get_val("EXPLAIN SELECT * FROM " . idf_escape($table_status["Name"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")), $regs)) {
			return $regs[1];
		}
	}

	function types(bool $extensions = false): array {
		$cockroach = connection()->flavor == 'cockroach';
		// 'b' - base, 'c' - composite, 'd' - domain, 'e' - enum, 'r' - range; 'p' pseudo-types and 'm' multiranges are not created on their own
		$kinds = ($cockroach ? "'e'" : "'b','c','d','e'" . (min_version(9.2) ? ",'r'" : ""));
		return get_key_vals(
			"SELECT t.oid, t.typname
FROM pg_type t
WHERE t.typnamespace = " . driver()->nsOid . "
AND t.typtype IN ($kinds)" . ($cockroach ? "
AND t.typelem = 0" : "
AND (t.typrelid = 0 OR (SELECT c.relkind FROM pg_class c WHERE c.oid = t.typrelid) = 'c')" // pg_type contains also the row type of every table, view and sequence
				. "
AND NOT EXISTS (SELECT 1 FROM pg_type e WHERE e.typarray = t.oid)" // types created automatically for arrays
				. ($extensions ? '' : "
AND t.oid NOT IN (SELECT objid FROM pg_catalog.pg_depend WHERE classid = 'pg_type'::regclass AND deptype = 'e')")) // 'e' - types created by extensions
			. "
ORDER BY t.typname"
		);
	}

	function type_values(int $id): string {
		// to get values from type string: unnest(enum_range(NULL::"$type"))
		$enums = get_vals("SELECT enumlabel FROM pg_enum WHERE enumtypid = $id ORDER BY enumsortorder");
		return ($enums ? "'" . implode("', '", array_map('addslashes', $enums)) . "'" : "");
	}

	/** Get SQL expression returning the name of a collation if it's not the one defined by the type
	* @param string $oid SQL expression with the collation OID
	*/
	function collation_name(string $oid): string {
		return (min_version(9.1) ? "(SELECT collname FROM pg_collation WHERE oid = $oid AND collname != 'default')" : "NULL");
	}

	function type_definition(int $id): array {
		$type = first(get_rows("SELECT typtype, typisdefined::int AS defined, typrelid FROM pg_type WHERE oid = $id"));
		$return = array("kind" => ($type ? $type["typtype"] : ""), "definition" => "");
		if (!$type || !$type["defined"]) { // shell type created by CREATE TYPE without options
			return $return;
		}
		switch ($return["kind"]) {
			case 'e':
				$values = get_vals("SELECT enumlabel FROM pg_enum WHERE enumtypid = $id ORDER BY enumsortorder");
				$return["definition"] = "AS ENUM (" . implode(", ", array_map('Adminer\q', $values)) . ")";
				break;

			case 'c':
				$columns = array();
				foreach (
					get_rows("SELECT attname, format_type(atttypid, atttypmod) AS full_type, " . collation_name("attcollation") . " AS collation
FROM pg_attribute
WHERE attrelid = $type[typrelid] AND attnum > 0 AND NOT attisdropped
ORDER BY attnum") as $row
				) {
					$columns[] = idf_escape($row["attname"]) . " $row[full_type]" . ($row["collation"] ? " COLLATE " . idf_escape($row["collation"]) : "");
				}
				$return["definition"] = "AS (\n\t" . implode(",\n\t", $columns) . "\n)";
				break;

			case 'd':
				$domain = first(get_rows("SELECT format_type(typbasetype, typtypmod) AS base, typnotnull::int AS notnull, typdefault, " . collation_name("typcollation") . " AS collation
FROM pg_type WHERE oid = $id"));
				$return["definition"] = "AS $domain[base]"
					. ($domain["collation"] ? " COLLATE " . idf_escape($domain["collation"]) : "")
					. ($domain["typdefault"] != "" ? " DEFAULT $domain[typdefault]" : "") // typdefault is an SQL expression
					. ($domain["notnull"] ? " NOT NULL" : "")
				;
				// 'n' - NOT NULL constraint created since PostgreSQL 17, it's already covered by typnotnull
				foreach (get_rows("SELECT conname, pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE contypid = $id AND contype != 'n' ORDER BY conname") as $row) {
					$return["definition"] .= " CONSTRAINT " . idf_escape($row["conname"]) . " $row[definition]"; // definition is CHECK (...)
				}
				break;

			case 'r':
				$range = first(get_rows("SELECT format_type(rngsubtype, NULL) AS subtype,
(SELECT opcname FROM pg_opclass WHERE oid = rngsubopc) AS subtype_opclass,
" . collation_name("rngcollation") . " AS collation,
NULLIF(rngcanonical, 0)::regproc::text AS canonical,
NULLIF(rngsubdiff, 0)::regproc::text AS subtype_diff" . (min_version(14) ? ",
(SELECT typname FROM pg_type WHERE oid = rngmultitypid) AS multirange_type_name" : "") . "
FROM pg_range WHERE rngtypid = $id"));
				$options = array();
				foreach (array("subtype" => 0, "subtype_opclass" => 1, "collation" => 1, "canonical" => 0, "subtype_diff" => 0, "multirange_type_name" => 1) as $key => $escape) {
					if ($range[$key] != "") {
						$options[] = strtoupper($key) . " = " . ($escape ? idf_escape($range[$key]) : $range[$key]);
					}
				}
				$return["definition"] = "AS RANGE (" . implode(", ", $options) . ")";
		}
		//! 'b' - base types, they are created only by a superuser so they are usually a part of an extension
		return $return;
	}

	function schemas(): array {
		return get_vals("SELECT nspname FROM pg_namespace ORDER BY nspname");
	}

	function get_schema(): string {
		return (string) get_val("SELECT current_schema()"); // NULL if no schema in search_path exists or is visible
	}

	function set_schema(string $schema, ?Db $connection2 = null): bool {
		$return = connection($connection2)->query("SET search_path TO " . idf_escape($schema));
		driver()->setUserTypes(types(true)); //! get types from current_schemas('t')
		return !!$return;
	}

	/** Get SQL commands dropping all exported tables and views
	* @param TableStatus[] $tables
	*/
	function drop_sql(array $tables): string {
		$return = "";
		foreach (drop_kinds($tables) as $kind => $names) {
			$return .= "DROP $kind IF EXISTS " . implode(", ", $names) . ";\n";
		}
		return ($return ? "$return\n" : "");
	}

	// create_sql() produces CREATE TABLE without FK CONSTRAINTs
	// foreign_keys_sql() produces all FK CONSTRAINTs as ALTER TABLE ... ADD CONSTRAINT
	// so that all FKs can be added after all tables have been created, avoiding any need to reorder CREATE TABLE statements in order of their FK dependencies
	function foreign_keys_sql(string $table): string {
		$return = "";

		$status = table_status1($table);
		$ns = idf_escape($status['nspname']);
		$fkeys = foreign_keys($table);
		ksort($fkeys);

		foreach ($fkeys as $fkey_name => $fkey) {
			$return .= "ALTER TABLE ONLY $ns." . idf_escape($status['Name']) . " ADD CONSTRAINT " . idf_escape($fkey_name) . " "
				. preg_replace('~( REFERENCES )([^(.]+\()~', "\\1$ns.\\2", $fkey["definition"]) . ";\n";
		}

		return ($return ? "$return\n" : $return);
	}

	/** Get commands creating indexes of a table or a materialized view
	* @param string $primary index which is already a part of CREATE TABLE
	* @return string starts with an empty line before each command
	*/
	function indexes_sql(string $table, string $primary = ""): string {
		$return = "";
		$query = "SELECT indexdef FROM pg_catalog.pg_indexes WHERE schemaname = current_schema() AND tablename = " . q($table) . ($primary != "" ? " AND indexname != " . q($primary) : "");
		foreach (get_rows($query, null, "-- ") as $row) {
			$return .= "\n\n$row[indexdef];";
		}
		return $return;
	}

	function create_sql(string $table, ?bool $auto_increment, string $style): string {
		$return_parts = array();
		$sequences = array();
		$sequences_owned = array(); // ALTER SEQUENCE commands linking the sequences to their columns
		$sequence_values = array(); // sequences whose value is restored after the table is created

		$status = table_status1($table);
		$ns = idf_escape($status['nspname']);
		if (is_view($status)) {
			$view = view($table);
			// Engine is 'view' or 'materialized view', only a materialized view can have indexes
			$create = "CREATE " . strtoupper($status["Engine"]) . " $ns." . idf_escape($table) . " AS " . rtrim($view["select"], ";") . ";";
			return rtrim($create . indexes_sql($table), ';');
		}
		$fields = fields($table);

		if (count($status) < 2 || empty($fields)) {
			return "";
		}

		$return = "CREATE TABLE $ns." . idf_escape($status['Name']) . " (\n    ";
		$table_literal = q("$ns." . idf_escape($status['Name'])); // the table name as expected by pg_get_serial_sequence()

		// fields' definitions
		foreach ($fields as $field) {
			$serial_sequence = "";
			if ($field['default'] == "nextval('$status[Name]_$field[field]_seq')") {
				$serial_sequence = "$ns." . idf_escape("$status[Name]_$field[field]_seq"); // the serial type re-creates it under the same name
				$field['default'] = null;
				$field['full_type'] = preg_replace('~int(eger)?~', 'serial', $field['full_type']);
			}

			$part = idf_escape($field['field']) . ' ' . $field['full_type']
				. preg_replace('~(nextval\(\')([^.\']+\')~', '\1' . str_replace("'", "''", $status['nspname']) . '.\2', default_value($field))
				. ($field['null'] ? "" : " NOT NULL");
			$return_parts[] = $part;

			// sequences for fields
			if (preg_match('~nextval\(\'([^\']+)\'\)~', $field['default'], $matches)) {
				$sequence_name = $matches[1];
				$sq = first(get_rows((min_version(10)
					? "SELECT *, cache_size AS cache_value FROM pg_sequences WHERE schemaname = current_schema() AND sequencename = " . q(idf_unescape($sequence_name))
					: "SELECT * FROM $sequence_name"
				), null, "-- "));
				$sequences[] = ($style == "DROP+CREATE" ? "DROP SEQUENCE IF EXISTS $ns.$sequence_name;\n" : "")
					. "CREATE SEQUENCE $ns.$sequence_name INCREMENT $sq[increment_by] MINVALUE $sq[min_value] MAXVALUE $sq[max_value]"
					. " CACHE $sq[cache_value];"
				;
				if (get_val("SELECT pg_get_serial_sequence($table_literal, " . q($field['field']) . ")")) {
					// the sequence is owned by the column but CREATE SEQUENCE can't say it before the table is created
					$sequences_owned[] = "\n\nALTER SEQUENCE $ns.$sequence_name OWNED BY $ns." . idf_escape($status['Name']) . "." . idf_escape($field['field']) . ";";
				}
				if ($auto_increment) {
					$sequence_values[] = "$ns.$sequence_name";
				}
			} elseif ($auto_increment && $field['auto_increment']) {
				// the sequence of a serial or identity column is created by CREATE TABLE
				// a column can own more sequences than the one in its default so pg_get_serial_sequence() is used only without a default
				$sequence_values[] = ($serial_sequence ?: get_val("SELECT pg_get_serial_sequence($table_literal, " . q($field['field']) . ")"));
			}
		}

		// adding sequences before table definition
		if (!empty($sequences)) {
			$return = implode("\n\n", $sequences) . "\n\n$return";
		}

		$primary = "";
		foreach (indexes($table) as $index_name => $index) {
			if ($index['type'] == 'PRIMARY') {
				$primary = $index_name;
				$return_parts[] = "CONSTRAINT " . idf_escape($index_name) . " PRIMARY KEY (" . implode(', ', array_map('Adminer\idf_escape', $index['columns'])) . ")";
			}
		}

		foreach (driver()->checkConstraints($table) as $conname => $consrc) {
			$return_parts[] = "CONSTRAINT " . idf_escape($conname) . " CHECK ($consrc)";
		}
		$return .= implode(",\n    ", $return_parts) . "\n)";

		$partition = driver()->partitionsInfo($status['Name']);
		if ($partition) {
			$return .= "\nPARTITION BY $partition[partition_by]($partition[partition])";
		}
		//! parse pg_class.relpartbound to create PARTITION OF
		//! don't insert partitioned data twice

		$return .= "\nWITH (oids = " . ($status['Oid'] ? 'true' : 'false') . ");";
		$return .= implode($sequences_owned);

		// comments for table & fields
		if ($status['Comment']) {
			$return .= "\n\nCOMMENT ON TABLE $ns." . idf_escape($status['Name']) . " IS " . q($status['Comment']) . ";";
		}

		foreach ($fields as $field_name => $field) {
			if ($field['comment']) {
				$return .= "\n\nCOMMENT ON COLUMN $ns." . idf_escape($status['Name']) . "." . idf_escape($field_name) . " IS " . q($field['comment']) . ";";
			}
		}

		$return .= indexes_sql($table, $primary);

		foreach (array_filter($sequence_values) as $sequence) {
			$sq = first(get_rows("SELECT last_value, is_called::int FROM $sequence", null, "-- "));
			if ($sq['is_called']) { // an unused sequence has nothing to restore
				// setval() in a DO block - a plain SELECT would print a result in the import, ALTER SEQUENCE RESTART would leave is_called false
				$return .= "\n\nDO \$\$ BEGIN PERFORM setval(" . q($sequence) . ", $sq[last_value]); END \$\$;";
			}
		}

		return rtrim($return, ';');
	}

	function truncate_sql(string $table): string {
		return "TRUNCATE " . table($table);
	}

	/** Get SQL command truncating all tables whose data is exported
	* @param list<string> $tables
	* @return string truncating the tables by a single command succeeds even if they reference each other, one by one it fails
	*/
	function truncate_all_sql(array $tables): string {
		return ($tables ? "TRUNCATE " . implode(", ", array_map('Adminer\table', $tables)) . ";\n\n" : "");
	}

	function trigger_sql(string $table): string {
		$status = table_status1($table);
		$return = "";
		foreach (triggers($table) as $trg_id => $trg) {
			$trigger = trigger($trg_id, $status['Name']);
			$return .= "\nCREATE TRIGGER " . idf_escape($trigger['Trigger']) . " $trigger[Timing] $trigger[Event] ON "
				. idf_escape($status["nspname"]) . "." . idf_escape($status['Name']) . " $trigger[Type] $trigger[Statement];;\n";
		}
		return $return;
	}


	function use_sql(string $database, string $style = ""): string {
		$name = idf_escape($database);
		$return = "";
		if (preg_match('~CREATE~', $style)) {
			if ($style == "DROP+CREATE") {
				$return = "DROP DATABASE IF EXISTS $name;\n";
			}
			$return .= "CREATE DATABASE $name;\n"; //! get info from pg_database
		}
		return "$return\\connect $name";
	}

	function show_variables(): array {
		return get_rows("SHOW ALL");
	}

	function process_list(): array {
		return get_rows("SELECT * FROM pg_stat_activity ORDER BY " . (min_version(9.2) ? "pid" : "procpid"));
	}

	function convert_field(array $field) {
	}

	function unconvert_field(array $field, string $return): string {
		// a literal compared with a composite value would be an anonymous record: input of anonymous composite types is not implemented
		return ($field["composite"] ? "$return::$field[type]" : $return); // type is the result of format_type(), quoted and schema qualified if needed
	}

	function support(string $feature): bool {
		return preg_match('~^(check|columns|comment|database|drop_col|dump|descidx|fast_status|indexes|kill|partial_indexes|routine|scheme|sequence|sql|table'
			. '|transaction_ddl|trigger|type|variables|view'
			. (min_version(9.3) ? '|materializedview' : '')
			. (min_version(11) ? '|procedure' : '')
			. (connection()->flavor == 'cockroach' ? '' : '|deferrable') // https://github.com/cockroachdb/cockroach/issues/31632
			. (connection()->flavor == 'cockroach' ? '' : '|processlist') // https://github.com/cockroachdb/cockroach/issues/24745
			. ')$~', $feature)
		;
	}

	function kill_process(string $id) {
		return queries("SELECT pg_terminate_backend(" . number($id) . ")");
	}

	function connection_id(): string {
		return "SELECT pg_backend_pid()";
	}

	function max_connections(): string {
		return get_val("SHOW max_connections");
	}
}
