<?php
/**
* @author Jakub Cernohuby
* @author Vladimir Stastka
* @author Jakub Vrana
*/

namespace Adminer;

add_driver("mssql", "MS SQL");

if (isset($_GET["mssql"])) {
	define('Adminer\DRIVER', "mssql");

	if (extension_loaded("sqlsrv") && $_GET["ext"] != "pdo") {
		class Db extends SqlDb {
			public $extension = "sqlsrv";
			private $link, $result, $warnings;

			private function get_error(): void {
				$this->error = "";
				foreach (sqlsrv_errors() as $error) {
					$this->errno = $error["code"];
					$this->error .= "$error[message]\n";
				}
				$this->error = rtrim($this->error);
			}

			function attach(string $server, string $username, string $password): string {
				sqlsrv_configure("WarningsReturnAsErrors", 0); // a message from the server would stop sqlsrv_next_result(), e.g. between the result sets of sp_helpdb
				$connection_info = array("UID" => $username, "PWD" => $password, "CharacterSet" => "UTF-8");
				$ssl = adminer()->connectSsl();
				if (isset($ssl["Encrypt"])) {
					$connection_info["Encrypt"] = $ssl["Encrypt"];
				}
				if (isset($ssl["TrustServerCertificate"])) {
					$connection_info["TrustServerCertificate"] = $ssl["TrustServerCertificate"];
				}
				$db = adminer()->database();
				if ($db != "") {
					$connection_info["Database"] = $db;
				}
				list($host, $port) = host_port($server);
				$this->link = @sqlsrv_connect($host . ($port ? ",$port" : ""), $connection_info);
				if ($this->link) {
					$info = sqlsrv_server_info($this->link);
					$this->server_info = $info['SQLServerVersion'];
				} else {
					$this->get_error();
				}
				return ($this->link ? '' : $this->error);
			}

			function quote(string $string): string {
				$unicode = strlen($string) != strlen(utf8_decode($string));
				return ($unicode ? "N" : "") . "'" . str_replace("'", "''", $string) . "'";
			}

			function select_db(string $database) {
				return $this->query(use_sql($database));
			}

			function query(string $query, bool $unbuffered = false) {
				$result = sqlsrv_query($this->link, $query); //! , array(), ($unbuffered ? array() : array("Scrollable" => "keyset"))
				$this->error = "";
				if (!$result) {
					$this->get_error();
					return false;
				}
				return $this->store_result($result);
			}

			function multi_query(string $query) {
				$this->result = sqlsrv_query($this->link, $query);
				$this->error = "";
				if (!$this->result) {
					$this->get_error();
					return false;
				}
				return true;
			}

			function store_result($result = null) {
				if (!$result) {
					$result = $this->result;
				}
				if (!$result) {
					return false;
				}
				$this->warnings = sqlsrv_errors(SQLSRV_ERR_WARNINGS); // sqlsrv_field_metadata() discards them
				if (sqlsrv_field_metadata($result)) {
					return new Result($result);
				}
				$this->affected_rows = sqlsrv_rows_affected($result);
				return true;
			}

			function next_result(): bool {
				if (!$this->result) {
					return false;
				}
				$return = sqlsrv_next_result($this->result);
				if ($return === false) { // null is the end of the result sets, false is an error
					$this->get_error();
					$this->result = null; // report the error in the next iteration and stop there
					return true;
				}
				return !!$return;
			}

			/** Get the messages printed by the current result set
			* @return list<string>
			*/
			function warnings(): array {
				$return = array();
				foreach ((array) $this->warnings as $warning) {
					$return[] = $warning["message"];
				}
				return $return;
			}
		}

		class Result {
			public $num_rows;
			private $result, $offset = 0, $fields;

			function __construct($result) {
				$this->result = $result;
				// $this->num_rows = sqlsrv_num_rows($result); // available only in scrollable results
			}

			private function convert($row) {
				foreach ((array) $row as $key => $val) {
					if (is_a($val, 'DateTime')) {
						$row[$key] = $val->format("Y-m-d H:i:s");
					}
					//! stream
				}
				return $row;
			}

			function fetch_assoc() {
				return $this->convert(sqlsrv_fetch_array($this->result, SQLSRV_FETCH_ASSOC));
			}

			function fetch_row() {
				return $this->convert(sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC));
			}

			function fetch_field(): \stdClass {
				if (!$this->fields) {
					$this->fields = sqlsrv_field_metadata($this->result);
				}
				$field = $this->fields[$this->offset++];
				$return = new \stdClass;
				$return->name = $field["Name"];
				$return->type = ($field["Type"] == 1 ? 254 : 15);
				//! $return->native_type: http://msdn.microsoft.com/en-us/library/cc296197.aspx
				$return->charsetnr = (in_array($field["Type"], array(-2, -3, -4)) ? 63 : 0); // SQL_BINARY, SQL_VARBINARY, SQL_LONGVARBINARY
				return $return;
			}

			function seek($offset) {
				for ($i=0; $i < $offset; $i++) {
					sqlsrv_fetch($this->result); // SQLSRV_SCROLL_ABSOLUTE added in sqlsrv 1.1
				}
			}
		}

		function last_id($result): string {
			return (string) get_val("SELECT SCOPE_IDENTITY()"); // @@IDENTITY can return trigger INSERT; SCOPE_IDENTITY() is NULL if the table has no IDENTITY column
		}

		function explain(Db $connection, string $query) {
			$connection->query("SET SHOWPLAN_ALL ON");
			$return = $connection->query($query);
			$connection->query("SET SHOWPLAN_ALL OFF"); // connection is used also for indexes
			return $return;
		}

	} else {
		abstract class MssqlDb extends PdoDb {
			function select_db(string $database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query(use_sql($database));
			}

			function lastInsertId() {
				return $this->pdo->lastInsertId();
			}

			/** Get the messages printed by the current result set
			* @return list<string>
			*/
			function warnings(): array {
				/** @var PdoResult|bool */
				$result = $this->multi;
				if (!is_object($result)) {
					return array();
				}
				$error = $result->errorInfo(); // the messages are reported as a warning of the statement
				return array((string) $error[2]);
			}
		}

		function last_id($result): string {
			return connection()->lastInsertId();
		}

		function explain(Db $connection, string $query) {
		}

		if (extension_loaded("pdo_sqlsrv")) {
			class Db extends MssqlDb {
				public $extension = "PDO_SQLSRV";

				function attach(string $server, string $username, string $password): string {
					list($host, $port) = host_port($server);
					$dsn = "sqlsrv:Server=$host" . ($port ? ",$port" : "");
					$ssl = adminer()->connectSsl();
					foreach (array("Encrypt", "TrustServerCertificate") as $key) {
						if (isset($ssl[$key])) {
							$dsn .= ";$key=" . ($ssl[$key] ? 1 : 0); // the DSN accepts only 1 and 0
						}
					}
					// without SQLSRV_ATTR_DIRECT_QUERY, the queries run through sp_prepexec, which reverts SET IDENTITY_INSERT after each of them
					return $this->dsn($dsn, $username, $password, array(\PDO::SQLSRV_ATTR_DIRECT_QUERY => true));
				}
			}

		} elseif (extension_loaded("pdo_dblib")) {
			class Db extends MssqlDb {
				public $extension = "PDO_DBLIB";

				function attach(string $server, string $username, string $password): string {
					list($host, $port) = host_port($server);
					return $this->dsn("dblib:charset=utf8;host=$host" . ($port ? (is_numeric($port) ? ";port=" : ";unix_socket=") . $port : ""), $username, $password);
				}
			}
		}
	}


	class Driver extends SqlDriver {
		static $extensions = array("SQLSRV", "PDO_SQLSRV", "PDO_DBLIB");
		static $jush = "mssql";

		public $insertFunctions = array("date|time" => "getdate");
		public $editFunctions = array(
			"int|decimal|real|float|money|datetime" => "+/-",
			"char|text" => "+",
		);

		public $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL");
		public $functions = array("len", "lower", "round", "upper");
		public $grouping = array("avg", "count", "count distinct", "max", "min", "sum");
		public $generated = array("PERSISTED", "VIRTUAL");
		public $onActions = "NO ACTION|CASCADE|SET NULL|SET DEFAULT";

		static function connect(string $server, string $username, string $password) {
			if ($server == "") {
				$server = "localhost:1433";
			}
			return parent::connect($server, $username, $password);
		}

		function __construct(Db $connection) {
			parent::__construct($connection);
			$this->types = array( //! use sys.types
				lang('Numbers') => array("tinyint" => 3, "smallint" => 5, "int" => 10, "bigint" => 20, "bit" => 1, "decimal" => 0, "real" => 12, "float" => 53, "smallmoney" => 10, "money" => 20),
				lang('Date and time') => array("date" => 10, "smalldatetime" => 19, "datetime" => 19, "datetime2" => 19, "time" => 8, "datetimeoffset" => 10),
				lang('Strings') => array("char" => 8000, "varchar" => 8000, "text" => 2147483647, "nchar" => 4000, "nvarchar" => 4000, "ntext" => 1073741823),
				lang('Binary') => array("binary" => 8000, "varbinary" => 8000, "image" => 2147483647),
			);
		}

		function insertUpdate(string $table, array $rows, array $primary) {
			$fields = fields($table);
			$update = array();
			$where = array();
			$set = reset($rows);
			$columns = "c" . implode(", c", range(1, count($set)));
			$c = 0;
			$insert = array();
			foreach ($set as $key => $val) {
				$c++;
				$name = idf_unescape($key);
				if (!$fields[$name]["auto_increment"]) {
					$insert[$key] = "c$c";
				}
				if (isset($primary[$name])) {
					$where[] = "$key = c$c";
				} else {
					$update[] = "$key = c$c";
				}
			}
			$values = array();
			foreach ($rows as $set) {
				$values[] = "(" . implode(", ", $set) . ")";
			}
			if ($where) {
				$identity = queries("SET IDENTITY_INSERT " . table($table) . " ON");
				$return = queries(
					"MERGE " . table($table) . " USING (VALUES\n\t" . implode(",\n\t", $values) . "\n) AS source ($columns) ON " . implode(" AND ", $where) //! source, c1 - possible conflict
					. ($update ? "\nWHEN MATCHED THEN UPDATE SET " . implode(", ", $update) : "")
					// ; is mandatory
					. "\nWHEN NOT MATCHED THEN INSERT (" . implode(", ", array_keys($identity ? $set : $insert)) . ") VALUES (" . ($identity ? $columns : implode(", ", $insert)) . ");"
				);
				if ($identity) {
					queries("SET IDENTITY_INSERT " . table($table) . " OFF");
				}
			} else {
				$return = queries("INSERT INTO " . table($table) . " (" . implode(", ", array_keys($set)) . ") VALUES\n" . implode(",\n", $values));
			}
			return $return;
		}

		function begin() {
			return queries("BEGIN TRANSACTION");
		}

		function convertSearch(string $idf, array $val, array $field): string {
			// these types support no comparison operator, not even LIKE, or accept no text value; the other types are converted implicitly
			return (preg_match('~^(bit|n?text|xml|uniqueidentifier|sql_variant|hierarchyid|geography|geometry)$~', $field["type"])
				? "CAST($idf AS nvarchar(max))"
				: $idf
			);
		}

		function quoteBinary(string $s): string {
			return "0x" . bin2hex($s);
		}

		function warnings() {
			$return = array();
			foreach ($this->conn->warnings() as $message) {
				$message = trim(preg_replace('~^(\[[^]]+])+~', '', $message)); // the messages are prefixed by [Microsoft][ODBC Driver][SQL Server], sp_helpdb prints an empty one
				if ($message != "") {
					$return[] = $message;
				}
			}
			return nl_br(h(implode("\n", $return)));
		}

		function tableHelp(string $name, bool $is_view = false) {
			$links = array(
				"sys" => "catalog-views/sys-",
				"INFORMATION_SCHEMA" => "information-schema-views/",
			);
			$link = $links[get_schema()];
			if ($link) {
				return "relational-databases/system-$link" . preg_replace('~_~', '-', strtolower($name)) . "-transact-sql";
			}
		}
	}



	function idf_escape(string $idf): string {
		return "[" . str_replace("]", "]]", $idf) . "]";
	}

	function table(string $idf): string {
		return ($_GET["ns"] != "" ? idf_escape($_GET["ns"]) . "." : "") . idf_escape($idf);
	}

	function get_databases(bool $flush): array {
		return get_vals("SELECT name FROM sys.databases WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb')");
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return ($limit ? " TOP (" . ($limit + $offset) . ")" : "") . " $query$where"; // seek later
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return limit($query, $where, 1, 0, $separator);
	}

	function db_collation(string $db, array $collations): ?string {
		return get_val("SELECT collation_name FROM sys.databases WHERE name = " . q($db));
	}

	function logged_user(): string {
		return get_val("SELECT SUSER_NAME()");
	}

	function tables_list(): array {
		return get_key_vals("SELECT name, type_desc FROM sys.all_objects WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') ORDER BY name");
	}

	function count_tables(array $databases): array {
		$return = array();
		foreach ($databases as $db) {
			connection()->select_db($db);
			$return[$db] = get_val("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES");
		}
		return $return;
	}

	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		$sizes = array();
		// a page is 8 KB; sys.dm_db_partition_stats requires the VIEW DATABASE STATE permission so sizes are optional
		foreach (
			get_rows("SELECT object_id, SUM(CASE WHEN index_id < 2 THEN row_count ELSE 0 END) AS [Rows],
SUM(CASE WHEN index_id < 2 THEN used_page_count ELSE 0 END) * 8192 AS Data_length,
SUM(CASE WHEN index_id > 1 THEN used_page_count ELSE 0 END) * 8192 AS Index_length,
SUM(reserved_page_count - used_page_count) * 8192 AS Data_free
FROM sys.dm_db_partition_stats
GROUP BY object_id", null, "") as $row
		) {
			$object_id = $row["object_id"];
			unset($row["object_id"]);
			$sizes[$object_id] = $row;
		}
		foreach (
			get_rows("SELECT ao.object_id, ao.name AS Name, ao.type_desc AS Engine,
	(SELECT cast(value as varchar(max)) FROM fn_listextendedproperty(default, 'SCHEMA', schema_name(schema_id), 'TABLE', ao.name, null, null)) AS Comment
FROM sys.all_objects AS ao
WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row
		) {
			$object_id = $row["object_id"];
			unset($row["object_id"]);
			$return[$row["Name"]] = $row + idx($sizes, $object_id, array());
		}
		return $return;
	}

	function is_view(array $table_status): bool {
		return $table_status["Engine"] == "VIEW";
	}

	function fk_support(array $table_status): bool {
		return true;
	}

	function fields(string $table): array {
		$comments = get_key_vals("SELECT objname, cast(value as varchar(max)) FROM fn_listextendedproperty('MS_DESCRIPTION', 'schema', " . q(get_schema())
			. ", 'table', " . q($table) . ", 'column', NULL)");
		$return = array();
		$table_id = get_val("SELECT object_id FROM sys.all_objects WHERE schema_id = SCHEMA_ID(" . q(get_schema()) . ") AND type IN ('S', 'U', 'V') AND name = " . q($table));
		foreach (
			get_rows("SELECT c.max_length, c.precision, c.scale, c.name, c.is_nullable, c.is_identity, c.collation_name,
	COALESCE(bt.name, t.name) type, d.definition [default], d.name default_constraint, i.is_primary_key
FROM sys.all_columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
LEFT JOIN sys.types bt ON t.system_type_id = bt.user_type_id
LEFT JOIN sys.default_constraints d ON c.default_object_id = d.object_id
LEFT JOIN sys.index_columns ic ON c.object_id = ic.object_id AND c.column_id = ic.column_id
LEFT JOIN sys.indexes i ON ic.object_id = i.object_id AND ic.index_id = i.index_id
WHERE c.object_id = " . q($table_id)) as $row
		) {
			$type = $row["type"];
			$length = (preg_match("~char|binary~", $type)
				? intval($row["max_length"]) / ($type[0] == 'n' ? 2 : 1)
				: ($type == "decimal" ? "$row[precision],$row[scale]" : "")
			);
			$return[$row["name"]] = array(
				"field" => $row["name"],
				"full_type" => $type . ($length ? "($length)" : ""),
				"type" => $type,
				"length" => $length,
				"default" => (preg_match("~^\('(.*)'\)$~", $row["default"], $match) ? str_replace("''", "'", $match[1]) : $row["default"]),
				"default_constraint" => $row["default_constraint"],
				"null" => $row["is_nullable"],
				"auto_increment" => $row["is_identity"],
				"collation" => $row["collation_name"],
				"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
				"primary" => $row["is_primary_key"],
				"comment" => $comments[$row["name"]],
			);
		}
		foreach (get_rows("SELECT * FROM sys.computed_columns WHERE object_id = " . q($table_id)) as $row) {
			$return[$row["name"]]["generated"] = ($row["is_persisted"] ? "PERSISTED" : "VIRTUAL");
			$return[$row["name"]]["default"] = $row["definition"];
		}
		return $return;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		$return = array();
		// sp_statistics doesn't return information about primary key
		foreach (
			get_rows("SELECT i.name, key_ordinal, is_unique, is_primary_key, c.name AS column_name, is_descending_key
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE OBJECT_NAME(i.object_id) = " . q($table), $connection2) as $row
		) {
			$name = $row["name"];
			$return[$name]["type"] = ($row["is_primary_key"] ? "PRIMARY" : ($row["is_unique"] ? "UNIQUE" : "INDEX"));
			$return[$name]["lengths"] = array();
			$return[$name]["columns"][$row["key_ordinal"]] = $row["column_name"];
			$return[$name]["descs"][$row["key_ordinal"]] = ($row["is_descending_key"] ? '1' : null);
		}
		return $return;
	}

	function view(string $name): array {
		return array("select" => preg_replace(
			'~^(?:[^[]|\[[^]]*])*\s+AS\s+~isU',
			'',
			get_val("SELECT VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = " . q($name))
		));
	}

	function collations(): array {
		$return = array();
		foreach (get_vals("SELECT name FROM fn_helpcollations()") as $collation) {
			$return[preg_replace('~_.*~', '', $collation)][] = $collation;
		}
		return $return;
	}

	function information_schema(string $db, string $schema = ""): bool {
		return in_array($schema != "" ? $schema : get_schema(), array("INFORMATION_SCHEMA", "sys"));
	}

	function error(): string {
		return nl_br(h(preg_replace('~^(\[[^]]*])+~m', '', connection()->error)));
	}

	function create_database(string $db, string $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . (preg_match('~^[a-z0-9_]+$~i', $collation) ? " COLLATE $collation" : ""));
	}

	function drop_databases(array $databases): bool {
		return !!queries("DROP DATABASE " . implode(", ", array_map('Adminer\idf_escape', $databases)));
	}

	function rename_database(string $name, string $collation): bool {
		if (preg_match('~^[a-z0-9_]+$~i', $collation)) {
			queries("ALTER DATABASE " . idf_escape(DB) . " COLLATE $collation");
		}
		queries("ALTER DATABASE " . idf_escape(DB) . " MODIFY NAME = " . idf_escape($name));
		return true; //! false negative "The database name 'test2' has been set."
	}

	function auto_increment(): string {
		return " IDENTITY" . ($_POST["Auto_increment"] != "" ? "(" . number($_POST["Auto_increment"]) . ",1)" : "") . " PRIMARY KEY";
	}

	function alter_table(string $table, string $name, array $fields, array $foreign, ?string $comment, string $engine, string $collation, string $auto_increment, ?array $partitioning) {
		$alter = array();
		$comments = array();
		$orig_fields = fields($table);
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter["DROP"][] = " COLUMN $column";
			} else {
				$val[1] = preg_replace("~( COLLATE )'(\\w+)'~", '\1\2', $val[1]);
				$comments[$field[0]] = $val[5];
				unset($val[5]);
				if (preg_match('~ AS ~', $val[3])) {
					unset($val[1], $val[2]);
				}
				if ($field[0] == "") {
					$alter["ADD"][] = "\n  " . implode("", $val) . ($table == "" ? substr($foreign[$val[0]], 16 + strlen($val[0])) : ""); // 16 - strlen("  FOREIGN KEY ()")
				} else {
					$default = $val[3];
					unset($val[3]); // default values are set separately
					unset($val[6]); //! identity can't be removed
					if ($column != $val[0]) {
						queries("EXEC sp_rename " . q(table($table) . ".$column") . ", " . q(idf_unescape($val[0])) . ", 'COLUMN'");
					}
					$alter["ALTER COLUMN " . implode("", $val)][] = "";
					$orig_field = $orig_fields[$field[0]];
					if (default_value($orig_field) != $default) {
						if ($orig_field["default"] !== null) {
							$alter["DROP"][] = " " . idf_escape($orig_field["default_constraint"]);
						}
						if ($default) {
							$alter["ADD"][] = "\n $default FOR $column";
						}
					}
				}
			}
		}
		if ($table == "") {
			$add = (array) $alter["ADD"];
			foreach ($foreign as $key => $val) {
				if (!is_string($key)) { // string keys hold the foreign keys of a column, appended to it above
					$add[] = "\n$val";
				}
			}
			return queries("CREATE TABLE " . table($name) . " (" . implode(",", $add) . "\n)");
		}
		if ($table != $name) {
			queries("EXEC sp_rename " . q(table($table)) . ", " . q($name));
		}
		if ($foreign) {
			$alter[""] = $foreign;
		}
		foreach ($alter as $key => $val) {
			if (!queries("ALTER TABLE " . table($name) . " $key" . implode(",", $val))) {
				return false;
			}
		}
		foreach ($comments as $key => $val) {
			$comment = substr($val, 9); // 9 - strlen(" COMMENT ")
			queries("EXEC sp_dropextendedproperty @name = N'MS_Description', @level0type = N'Schema', @level0name = " . q(get_schema())
				. ", @level1type = N'Table', @level1name = " . q($name)
				. ", @level2type = N'Column', @level2name = " . q($key));
			queries("EXEC sp_addextendedproperty
@name = N'MS_Description',
@value = $comment,
@level0type = N'Schema',
@level0name = " . q(get_schema()) . ",
@level1type = N'Table',
@level1name = " . q($name) . ",
@level2type = N'Column',
@level2name = " . q($key))
			;
		}
		return true;
	}

	function alter_indexes(string $table, $alter) {
		$index = array();
		$drop = array();
		foreach ($alter as $val) {
			if ($val[2] == "DROP") {
				if ($val[0] == "PRIMARY") { //! sometimes used also for UNIQUE
					$drop[] = idf_escape($val[1]);
				} else {
					$index[] = idf_escape($val[1]) . " ON " . table($table);
				}
			} elseif (
				!queries(($val[0] != "PRIMARY"
					? "CREATE $val[0] " . ($val[0] != "INDEX" ? "INDEX " : "") . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table)
					: "ALTER TABLE " . table($table) . " ADD PRIMARY KEY"
				) . " (" . implode(", ", $val[2]) . ")")
			) {
				return false;
			}
		}
		return (!$index || queries("DROP INDEX " . implode(", ", $index)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP " . implode(", ", $drop)))
		;
	}

	function found_rows(array $table_status, array $where) {
	}

	function foreign_keys(string $table): array {
		$return = array();
		$on_actions = array("CASCADE", "NO ACTION", "SET NULL", "SET DEFAULT");
		$schema = get_schema();
		foreach (get_rows("EXEC sp_fkeys @fktable_name = " . q($table) . ", @fktable_owner = " . q($schema)) as $row) {
			$foreign_key = &$return[$row["FK_NAME"]];
			// sp_fkeys always returns the database and the schema, the other drivers leave them empty for the current ones
			$foreign_key["db"] = ($row["PKTABLE_QUALIFIER"] == DB ? "" : $row["PKTABLE_QUALIFIER"]);
			$foreign_key["ns"] = ($row["PKTABLE_OWNER"] == $schema ? "" : $row["PKTABLE_OWNER"]);
			$foreign_key["table"] = $row["PKTABLE_NAME"];
			$foreign_key["on_update"] = $on_actions[$row["UPDATE_RULE"]];
			$foreign_key["on_delete"] = $on_actions[$row["DELETE_RULE"]];
			$foreign_key["source"][] = $row["FKCOLUMN_NAME"];
			$foreign_key["target"][] = $row["PKCOLUMN_NAME"];
		}
		return $return;
	}

	function truncate_tables(array $tables): bool {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	function drop_views(array $views) {
		return queries("DROP VIEW " . implode(", ", array_map('Adminer\table', $views)));
	}

	function drop_tables(array $tables) {
		return queries("DROP TABLE " . implode(", ", array_map('Adminer\table', $tables)));
	}

	function move_tables(array $tables, array $views, string $target): bool {
		return apply_queries("ALTER SCHEMA " . idf_escape($target) . " TRANSFER", array_merge($tables, $views));
	}

	function trigger(string $name, string $table): array {
		if ($name == "") {
			return array();
		}
		$rows = get_rows(
			"SELECT s.name [Trigger],
CASE WHEN OBJECTPROPERTY(s.id, 'ExecIsInsertTrigger') = 1 THEN 'INSERT'
	WHEN OBJECTPROPERTY(s.id, 'ExecIsUpdateTrigger') = 1 THEN 'UPDATE'
	WHEN OBJECTPROPERTY(s.id, 'ExecIsDeleteTrigger') = 1 THEN 'DELETE' END [Event],
CASE WHEN OBJECTPROPERTY(s.id, 'ExecIsInsteadOfTrigger') = 1 THEN 'INSTEAD OF' ELSE 'AFTER' END [Timing],
c.text
FROM sysobjects s
JOIN syscomments c ON s.id = c.id
WHERE s.xtype = 'TR' AND s.name = " . q($name)
		); // triggers are not schema-scoped
		$return = reset($rows);
		if ($return) {
			$return["Statement"] = preg_replace('~^.+\s+AS\s+~isU', '', $return["text"]); //! identifiers, comments
		}
		return $return;
	}

	function triggers(string $table): array {
		$return = array();
		foreach (
			get_rows("SELECT sys1.name,
CASE WHEN OBJECTPROPERTY(sys1.id, 'ExecIsInsertTrigger') = 1 THEN 'INSERT'
	WHEN OBJECTPROPERTY(sys1.id, 'ExecIsUpdateTrigger') = 1 THEN 'UPDATE'
	WHEN OBJECTPROPERTY(sys1.id, 'ExecIsDeleteTrigger') = 1 THEN 'DELETE' END [Event],
CASE WHEN OBJECTPROPERTY(sys1.id, 'ExecIsInsteadOfTrigger') = 1 THEN 'INSTEAD OF' ELSE 'AFTER' END [Timing]
FROM sysobjects sys1
JOIN sysobjects sys2 ON sys1.parent_obj = sys2.id
WHERE sys1.xtype = 'TR' AND sys2.name = " . q($table)) as $row
		) { // triggers are not schema-scoped
			$return[$row["name"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}

	function trigger_options(): array {
		return array(
			"Timing" => array("AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("AS"),
		);
	}

	function schemas(): array {
		return get_vals("SELECT name FROM sys.schemas");
	}

	function get_schema(): string {
		if ($_GET["ns"] != "") {
			return $_GET["ns"];
		}
		return get_val("SELECT SCHEMA_NAME()");
	}

	function set_schema(string $schema, ?Db $connection2 = null): bool {
		$_GET["ns"] = $schema;
		return true; // ALTER USER is permanent
	}

	function create_sql(string $table, ?bool $auto_increment, string $style): string {
		if (is_view(table_status1($table))) {
			$view = view($table);
			return "CREATE VIEW " . table($table) . " AS $view[select]";
		}
		$fields = array();
		$primary = false;
		foreach (fields($table) as $name => $field) {
			$val = process_field($field, $field);
			if ($val[6]) {
				$primary = true;
			}
			$fields[] = implode("", $val);
		}
		foreach (indexes($table) as $name => $index) {
			if (!$primary || $index["type"] != "PRIMARY") {
				$columns = array();
				foreach ($index["columns"] as $key => $val) {
					$columns[] = idf_escape($val) . ($index["descs"][$key] ? " DESC" : "");
				}
				$name = idf_escape($name);
				$fields[] = ($index["type"] == "INDEX" ? "INDEX $name" : "CONSTRAINT $name " . ($index["type"] == "UNIQUE" ? "UNIQUE" : "PRIMARY KEY")) . " (" . implode(", ", $columns) . ")";
			}
		}
		foreach (driver()->checkConstraints($table) as $name => $check) {
			$fields[] = "CONSTRAINT " . idf_escape($name) . " CHECK ($check)";
		}
		return "CREATE TABLE " . table($table) . " (\n\t" . implode(",\n\t", $fields) . "\n)";
	}

	function foreign_keys_sql(string $table): string {
		$fields = array();
		foreach (foreign_keys($table) as $foreign) {
			$fields[] = ltrim(format_foreign_key($foreign));
		}
		return ($fields ? "ALTER TABLE " . table($table) . " ADD\n\t" . implode(",\n\t", $fields) . ";\n\n" : "");
	}

	function truncate_sql(string $table): string {
		return "TRUNCATE TABLE " . table($table);
	}

	function use_sql(string $database, string $style = ""): string {
		return "USE " . idf_escape($database);
	}

	function trigger_sql(string $table): string {
		$return = "";
		foreach (triggers($table) as $name => $trigger) {
			$return .= create_trigger(" ON " . table($table), trigger($name, $table)) . ";";
		}
		return $return;
	}

	function convert_field(array $field) {
	}

	function unconvert_field(array $field, string $return): string {
		return $return;
	}

	function support(string $feature): bool {
		return preg_match('~^(check|comment|columns|database|drop_col|dump|fast_status|indexes|descidx|scheme|sql|table|transaction_ddl|trigger|view|view_trigger)$~', $feature); //! routine|
	}
}
