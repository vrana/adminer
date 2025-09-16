<?php
namespace Adminer;

// This file is used both in Adminer and Adminer Editor.

/** Get database connection
* @param ?Db $connection2 custom connection to use instead of the default
* @return Db
*/
function connection(?Db $connection2 = null) {
	// can be used in customization, Db::$instance is minified
	return ($connection2 ?: Db::$instance);
}

/** Get Adminer object
* @return Adminer|Plugins
*/
function adminer() {
	return Adminer::$instance;
}

/** Get Driver object */
function driver(): Driver {
	return Driver::$instance;
}

/** Connect to the database */
function connect(): ?Db {
	$credentials = adminer()->credentials();
	$return = Driver::connect($credentials[0], $credentials[1], $credentials[2]);
	return (is_object($return) ? $return : null);
}

/** Unescape database identifier
* @param string $idf text inside ``
*/
function idf_unescape(string $idf): string {
	if (!preg_match('~^[`\'"[]~', $idf)) {
		return $idf;
	}
	$last = substr($idf, -1);
	return str_replace($last . $last, $last, substr($idf, 1, -1));
}

/** Shortcut for connection()->quote($string) */
function q(string $string): string {
	return connection()->quote($string);
}

/** Escape string to use inside '' */
function escape_string(string $val): string {
	return substr(q($val), 1, -1);
}

/** Get a possibly missing item from a possibly missing array
* idx($row, $key) is better than $row[$key] ?? null because PHP will report error for undefined $row
* @param ?mixed[] $array
* @param array-key $key
* @param mixed $default
* @return mixed
*/
function idx(?array $array, $key, $default = null) {
	return ($array && array_key_exists($key, $array) ? $array[$key] : $default);
}

/** Remove non-digits from a string; used instead of intval() to not corrupt big numbers
* @return numeric-string
*/
function number(string $val): string {
	return preg_replace('~[^0-9]+~', '', $val);
}

/** Get regular expression to match numeric types */
function number_type(): string {
	return '((?<!o)int(?!er)|numeric|real|float|double|decimal|money)'; // not point, not interval
}

/** Disable magic_quotes_gpc
* @param list<array> $process e.g. [&$_GET, &$_POST, &$_COOKIE]
* @param bool $filter whether to leave values as is
* @return void modified in place
*/
function remove_slashes(array $process, bool $filter = false): void {
	if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
		while (list($key, $val) = each($process)) {
			foreach ($val as $k => $v) {
				unset($process[$key][$k]);
				if (is_array($v)) {
					$process[$key][stripslashes($k)] = $v;
					$process[] = &$process[$key][stripslashes($k)];
				} else {
					$process[$key][stripslashes($k)] = ($filter ? $v : stripslashes($v));
				}
			}
		}
	}
}

/** Escape or unescape string to use inside form [] */
function bracket_escape(string $idf, bool $back = false): string {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3', '"' => ':4');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

/** Check if connection has at least the given version
* @param string|float $version required version
* @param string|float $maria_db required MariaDB version
*/
function min_version($version, $maria_db = "", ?Db $connection2 = null): bool {
	$connection2 = connection($connection2);
	$server_info = $connection2->server_info;
	if ($maria_db && preg_match('~([\d.]+)-MariaDB~', $server_info, $match)) {
		$server_info = $match[1];
		$version = $maria_db;
	}
	return $version && version_compare($server_info, $version) >= 0;
}

/** Get connection charset */
function charset(Db $connection): string {
	return (min_version("5.5.3", 0, $connection) ? "utf8mb4" : "utf8"); // SHOW CHARSET would require an extra query
}

/** Get INI boolean value */
function ini_bool(string $ini): bool {
	$val = ini_get($ini);
	return (preg_match('~^(on|true|yes)$~i', $val) || (int) $val); // boolean values set by php_value are strings
}

/** Get INI bytes value */
function ini_bytes(string $ini): int {
	$val = ini_get($ini);
	switch (strtolower(substr($val, -1))) {
		case 'g':
			$val = (int) $val * 1024; // no break
		case 'm':
			$val = (int) $val * 1024; // no break
		case 'k':
			$val = (int) $val * 1024;
	}
	return $val;
}

/** Check if SID is necessary */
function sid(): bool {
	static $return;
	if ($return === null) { // restart_session() defines SID
		$return = (SID && !($_COOKIE && ini_bool("session.use_cookies"))); // $_COOKIE - don't pass SID with permanent login
	}
	return $return;
}

/** Set password to session */
function set_password(string $vendor, ?string $server, string $username, ?string $password): void {
	$_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"] && is_string($password)
		? array(encrypt_string($password, $_COOKIE["adminer_key"]))
		: $password
	);
}

/** Get password from session
* @return string|false|null null for missing password, false for expired password
*/
function get_password() {
	$return = get_session("pwds");
	if (is_array($return)) {
		$return = ($_COOKIE["adminer_key"]
			? decrypt_string($return[0], $_COOKIE["adminer_key"])
			: false
		);
	}
	return $return;
}

/** Get single value from database
* @return string|false false if error
*/
function get_val(string $query, int $field = 0, ?Db $conn = null) {
	$conn = connection($conn);
	$result = $conn->query($query);
	if (!is_object($result)) {
		return false;
	}
	$row = $result->fetch_row();
	return ($row ? $row[$field] : false);
}

/** Get list of values from database
* @param array-key $column
* @return list<string>
*/
function get_vals(string $query, $column = 0): array {
	$return = array();
	$result = connection()->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
	}
	return $return;
}

/** Get keys from first column and values from second
* @return string[]
*/
function get_key_vals(string $query, ?Db $connection2 = null, bool $set_keys = true): array {
	$connection2 = connection($connection2);
	$return = array();
	$result = $connection2->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			if ($set_keys) {
				$return[$row[0]] = $row[1];
			} else {
				$return[] = $row[0];
			}
		}
	}
	return $return;
}

/** Get all rows of result
* @return list<string[]> of associative arrays
*/
function get_rows(string $query, ?Db $connection2 = null, string $error = "<p class='error'>"): array {
	$conn = connection($connection2);
	$return = array();
	$result = $conn->query($query);
	if (is_object($result)) { // can return true
		while ($row = $result->fetch_assoc()) {
			$return[] = $row;
		}
	} elseif (!$result && !$connection2 && $error && (defined('Adminer\PAGE_HEADER') || $error == "-- ")) {
		echo $error . error() . "\n";
	}
	return $return;
}

/** Find unique identifier of a row
* @param string[] $row
* @param Index[] $indexes
* @return string[]|void null if there is no unique identifier
*/
function unique_array(?array $row, array $indexes) {
	foreach ($indexes as $index) {
		if (preg_match("~PRIMARY|UNIQUE~", $index["type"])) {
			$return = array();
			foreach ($index["columns"] as $key) {
				if (!isset($row[$key])) { // NULL is ambiguous
					continue 2;
				}
				$return[$key] = $row[$key];
			}
			return $return;
		}
	}
}

/** Escape column key used in where() */
function escape_key(string $key): string {
	if (preg_match('(^([\w(]+)(' . str_replace("_", ".*", preg_quote(idf_escape("_"))) . ')([ \w)]+)$)', $key, $match)) { //! columns looking like functions
		return $match[1] . idf_escape(idf_unescape($match[2])) . $match[3]; //! SQL injection
	}
	return idf_escape($key);
}

/** Create SQL condition from parsed query string
* @param array{where:string[], null:list<string>} $where parsed query string
* @param Field[] $fields
*/
function where(array $where, array $fields = array()): string {
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, true); // true - back
		$column = escape_key($key);
		$field = idx($fields, $key, array());
		$field_type = $field["type"];
		$return[] = $column
			. (JUSH == "sql" && $field_type == "json" ? " = CAST(" . q($val) . " AS JSON)"
				: (JUSH == "pgsql" && preg_match('~^json~', $field_type) ? "::jsonb = " . q($val) . "::jsonb"
				: (JUSH == "sql" && is_numeric($val) && preg_match('~\.~', $val) ? " LIKE " . q($val) // LIKE because of floats but slow with ints
				: (JUSH == "mssql" && strpos($field_type, "datetime") === false ? " LIKE " . q(preg_replace('~[_%[]~', '[\0]', $val)) // LIKE because of text but it does not work with datetime
				: " = " . unconvert_field($field, q($val))))))
		; //! enum and set
		if (JUSH == "sql" && preg_match('~char|text~', $field_type) && preg_match("~[^ -@]~", $val)) { // not just [a-z] to catch non-ASCII characters
			$return[] = "$column = " . q($val) . " COLLATE " . charset(connection()) . "_bin";
		}
	}
	foreach ((array) $where["null"] as $key) {
		$return[] = escape_key($key) . " IS NULL";
	}
	return implode(" AND ", $return);
}

/** Create SQL condition from query string
* @param Field[] $fields
*/
function where_check(string $val, array $fields = array()): string {
	parse_str($val, $check);
	remove_slashes(array(&$check));
	return where($check, $fields);
}

/** Create query string where condition from value
* @param int $i condition order
* @param string $column column identifier
*/
function where_link(int $i, string $column, ?string $value, string $operator = "="): string {
	return "&where%5B$i%5D%5Bcol%5D=" . urlencode($column) . "&where%5B$i%5D%5Bop%5D=" . urlencode(($value !== null ? $operator : "IS NULL")) . "&where%5B$i%5D%5Bval%5D=" . urlencode($value);
}

/** Get select clause for convertible fields
* @param mixed[] $columns only keys are used
* @param Field[] $fields
* @param list<string> $select
*/
function convert_fields(array $columns, array $fields, array $select = array()): string {
	$return = "";
	foreach ($columns as $key => $val) {
		if ($select && !in_array(idf_escape($key), $select)) {
			continue;
		}
		$as = convert_field($fields[$key]);
		if ($as) {
			$return .= ", $as AS " . idf_escape($key);
		}
	}
	return $return;
}

/** Set cookie valid on current path
* @param int $lifetime number of seconds, 0 for session cookie, 2592000 - 30 days
*/
function cookie(string $name, ?string $value, int $lifetime = 2592000): void {
	header(
		"Set-Cookie: $name=" . urlencode($value)
			. ($lifetime ? "; expires=" . gmdate("D, d M Y H:i:s", time() + $lifetime) . " GMT" : "")
			. "; path=" . preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"])
			. (HTTPS ? "; secure" : "")
			. "; HttpOnly; SameSite=lax",
		false
	);
}

/** Get settings stored in a cookie
* @return mixed[]
*/
function get_settings(string $cookie): array {
	parse_str($_COOKIE[$cookie], $settings);
	return $settings;
}

/** Get setting stored in a cookie
* @param mixed $default
* @return mixed
*/
function get_setting(string $key, string $cookie = "adminer_settings", $default = null) {
	return idx(get_settings($cookie), $key, $default);
}

/** Store settings to a cookie
* @param mixed[] $settings
*/
function save_settings(array $settings, string $cookie = "adminer_settings"): void {
	$value = http_build_query($settings + get_settings($cookie));
	cookie($cookie, $value);
	$_COOKIE[$cookie] = $value;
}

/** Restart stopped session */
function restart_session(): void {
	if (!ini_bool("session.use_cookies") && (!function_exists('session_status') || session_status() == 1)) { // 1 - PHP_SESSION_NONE, session_status() available since PHP 5.4
		session_start();
	}
}

/** Stop session if possible */
function stop_session(bool $force = false): void {
	$use_cookies = ini_bool("session.use_cookies");
	if (!$use_cookies || $force) {
		session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
		if ($use_cookies && @ini_set("session.use_cookies", '0') === false) { // @ - may be disabled
			session_start();
		}
	}
}

/** Get session variable for current server
* @return mixed
*/
function &get_session(string $key) {
	return $_SESSION[$key][DRIVER][SERVER][$_GET["username"]];
}

/** Set session variable for current server
* @param mixed $val
* @return mixed
*/
function set_session(string $key, $val) {
	$_SESSION[$key][DRIVER][SERVER][$_GET["username"]] = $val; // used also in auth.inc.php
}

/** Get authenticated URL */
function auth_url(string $vendor, ?string $server, string $username, ?string $db = null): string {
	$uri = remove_from_uri(implode("|", array_keys(SqlDriver::$drivers))
		. "|username|ext|"
		. ($db !== null ? "db|" : "")
		. ($vendor == 'mssql' || $vendor == 'pgsql' ? "" : "ns|") // we don't have access to support() here
		. session_name())
	;
	preg_match('~([^?]*)\??(.*)~', $uri, $match);
	return "$match[1]?"
		. (sid() ? SID . "&" : "")
		. ($vendor != "server" || $server != "" ? urlencode($vendor) . "=" . urlencode($server) . "&" : "")
		. ($_GET["ext"] ? "ext=" . urlencode($_GET["ext"]) . "&" : "")
		. "username=" . urlencode($username)
		. ($db != "" ? "&db=" . urlencode($db) : "")
		. ($match[2] ? "&$match[2]" : "")
	;
}

/** Find whether it is an AJAX request */
function is_ajax(): bool {
	return ($_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest");
}

/** Send Location header and exit
* @param ?string $location null to only set a message
*/
function redirect(?string $location, ?string $message = null): void {
	if ($message !== null) {
		restart_session();
		$_SESSION["messages"][preg_replace('~^[^?]*~', '', ($location !== null ? $location : $_SERVER["REQUEST_URI"]))][] = $message;
	}
	if ($location !== null) {
		if ($location == "") {
			$location = ".";
		}
		header("Location: $location");
		exit;
	}
}

/** Execute query and redirect if successful
* @param bool $redirect
*/
function query_redirect(string $query, ?string $location, string $message, $redirect = true, bool $execute = true, bool $failed = false, string $time = ""): bool {
	if ($execute) {
		$start = microtime(true);
		$failed = !connection()->query($query);
		$time = format_time($start);
	}
	$sql = ($query ? adminer()->messageQuery($query, $time, $failed) : "");
	if ($failed) {
		adminer()->error .= error() . $sql . script("messagesPrint();") . "<br>";
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

class Queries {
	/** @var string[] */ static array $queries = array();
	static float $start = 0;
}

/** Execute and remember query
* @param string $query end with ';' to use DELIMITER
* @return Result|bool
*/
function queries(string $query) {
	if (!Queries::$start) {
		Queries::$start = microtime(true);
	}
	Queries::$queries[] = (preg_match('~;$~', $query) ? "DELIMITER ;;\n$query;\nDELIMITER " : $query) . ";";
	return connection()->query($query);
}

/** Apply command to all array items
* @param list<string> $tables
* @param callable(string):string $escape
*/
function apply_queries(string $query, array $tables, $escape = 'Adminer\table'): bool {
	foreach ($tables as $table) {
		if (!queries("$query " . $escape($table))) {
			return false;
		}
	}
	return true;
}

/** Redirect by remembered queries
* @param bool $redirect
*/
function queries_redirect(?string $location, string $message, $redirect): bool {
	$queries = implode("\n", Queries::$queries);
	$time = format_time(Queries::$start);
	return query_redirect($queries, $location, $message, $redirect, false, !$redirect, $time);
}

/** Format elapsed time
* @param float $start output of microtime(true)
* @return string HTML code
*/
function format_time(float $start): string {
	return lang('%.3f s', max(0, microtime(true) - $start));
}

/** Get relative REQUEST_URI */
function relative_uri(): string {
	return str_replace(":", "%3a", preg_replace('~^[^?]*/([^?]*)~', '\1', $_SERVER["REQUEST_URI"]));
}

/** Remove parameter from query string */
function remove_from_uri(string $param = ""): string {
	return substr(preg_replace("~(?<=[?&])($param" . (SID ? "" : "|" . session_name()) . ")=[^&]*&~", '', relative_uri() . "&"), 0, -1);
}

/** Get file contents from $_FILES
* @return mixed int for error, string otherwise
*/
function get_file(string $key, bool $decompress = false, string $delimiter = "") {
	$file = $_FILES[$key];
	if (!$file) {
		return null;
	}
	foreach ($file as $key => $val) {
		$file[$key] = (array) $val;
	}
	$return = '';
	foreach ($file["error"] as $key => $error) {
		if ($error) {
			return $error;
		}
		$name = $file["name"][$key];
		$tmp_name = $file["tmp_name"][$key];
		$content = file_get_contents(
			$decompress && preg_match('~\.gz$~', $name)
			? "compress.zlib://$tmp_name"
			: $tmp_name
		); //! may not be reachable because of open_basedir
		if ($decompress) {
			$start = substr($content, 0, 3);
			if (function_exists("iconv") && preg_match("~^\xFE\xFF|^\xFF\xFE~", $start)) { // not ternary operator to save memory
				$content = iconv("utf-16", "utf-8", $content);
			} elseif ($start == "\xEF\xBB\xBF") { // UTF-8 BOM
				$content = substr($content, 3);
			}
		}
		$return .= $content;
		if ($delimiter) {
			$return .= (preg_match("($delimiter\\s*\$)", $content) ? "" : $delimiter) . "\n\n";
		}
	}
	return $return;
}

/** Determine upload error */
function upload_error(int $error): string {
	$max_size = ($error == UPLOAD_ERR_INI_SIZE ? ini_get("upload_max_filesize") : 0); // post_max_size is checked in index.php
	return ($error ? lang('Unable to upload a file.') . ($max_size ? " " . lang('Maximum allowed file size is %sB.', $max_size) : "") : lang('File does not exist.'));
}

/** Create repeat pattern for preg */
function repeat_pattern(string $pattern, int $length): string {
	// fix for Compilation failed: number too big in {} quantifier
	return str_repeat("$pattern{0,65535}", $length / 65535) . "$pattern{0," . ($length % 65535) . "}"; // can create {0,0} which is OK
}

/** Check whether the string is in UTF-8 */
function is_utf8(?string $val): bool {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\0-\x8\xB\xC\xE-\x1F]~', $val));
}

/** Format decimal number
* @param float|numeric-string $val
*/
function format_number($val): string {
	return strtr(number_format($val, 0, ".", lang(',')), preg_split('~~u', lang('0123456789'), -1, PREG_SPLIT_NO_EMPTY));
}

/** Generate friendly URL */
function friendly_url(string $val): string {
	// used for blobs and export
	return preg_replace('~\W~i', '-', $val);
}

/** Get status of a single table and fall back to name on error
* @return TableStatus one element from table_status()
*/
function table_status1(string $table, bool $fast = false): array {
	$return = table_status($table, $fast);
	return ($return ? reset($return) : array("Name" => $table));
}

/** Find out foreign keys for each column
* @return list<ForeignKey>[] [$col => []]
*/
function column_foreign_keys(string $table): array {
	$return = array();
	foreach (adminer()->foreignKeys($table) as $foreign_key) {
		foreach ($foreign_key["source"] as $val) {
			$return[$val][] = $foreign_key;
		}
	}
	return $return;
}

/** Compute fields() from $_POST edit data; used by Mongo and SimpleDB
* @return Field[] same as fields()
*/
function fields_from_edit(): array {
	$return = array();
	foreach ((array) $_POST["field_keys"] as $key => $val) {
		if ($val != "") {
			$val = bracket_escape($val);
			$_POST["function"][$val] = $_POST["field_funs"][$key];
			$_POST["fields"][$val] = $_POST["field_vals"][$key];
		}
	}
	foreach ((array) $_POST["fields"] as $key => $val) {
		$name = bracket_escape($key, true); // true - back
		$return[$name] = array(
			"field" => $name,
			"privileges" => array("insert" => 1, "update" => 1, "where" => 1, "order" => 1),
			"null" => 1,
			"auto_increment" => ($key == driver()->primary),
		);
	}
	return $return;
}

/** Send headers for export
* @return string extension
*/
function dump_headers(string $identifier, bool $multi_table = false): string {
	$return = adminer()->dumpHeaders($identifier, $multi_table);
	$output = $_POST["output"];
	if ($output != "text") {
		header("Content-Disposition: attachment; filename=" . adminer()->dumpFilename($identifier) . ".$return" . ($output != "file" && preg_match('~^[0-9a-z]+$~', $output) ? ".$output" : ""));
	}
	session_write_close();
	if (!ob_get_level()) {
		ob_start(null, 4096);
	}
	ob_flush();
	flush();
	return $return;
}

/** Print CSV row
* @param string[] $row
*/
function dump_csv(array $row): void {
	foreach ($row as $key => $val) {
		if (preg_match('~["\n,;\t]|^0.|\.\d*0$~', $val) || $val === "") {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(($_POST["format"] == "csv" ? "," : ($_POST["format"] == "tsv" ? "\t" : ";")), $row) . "\r\n";
}

/** Apply SQL function
* @param string $column escaped column identifier
*/
function apply_sql_function(?string $function, string $column): string {
	return ($function ? ($function == "unixepoch" ? "DATETIME($column, '$function')" : ($function == "count distinct" ? "COUNT(DISTINCT " : strtoupper("$function(")) . "$column)") : $column);
}

/** Get path of the temporary directory */
function get_temp_dir(): string {
	$return = ini_get("upload_tmp_dir"); // session_save_path() may contain other storage path
	if (!$return) {
		if (function_exists('sys_get_temp_dir')) {
			$return = sys_get_temp_dir();
		} else {
			$filename = @tempnam("", ""); // @ - temp directory can be disabled by open_basedir
			if (!$filename) {
				return '';
			}
			$return = dirname($filename);
			unlink($filename);
		}
	}
	return $return;
}

/** Open and exclusively lock a file
* @return resource|void null for error
*/
function file_open_lock(string $filename) {
	if (is_link($filename)) {
		return; // https://cwe.mitre.org/data/definitions/61.html
	}
	$fp = @fopen($filename, "c+"); // @ - may not be writable
	if (!$fp) {
		return;
	}
	@chmod($filename, 0660); // @ - may not be permitted
	if (!flock($fp, LOCK_EX)) {
		fclose($fp);
		return;
	}
	return $fp;
}

/** Write and unlock a file
* @param resource $fp
*/
function file_write_unlock($fp, string $data): void {
	rewind($fp);
	fwrite($fp, $data);
	ftruncate($fp, strlen($data));
	file_unlock($fp);
}

/** Unlock and close a file
* @param resource $fp
*/
function file_unlock($fp): void {
	flock($fp, LOCK_UN);
	fclose($fp);
}

/** Get first element of an array
* @param mixed[] $array
* @return mixed if not found
*/
function first(array $array) {
	// reset(f()) triggers a notice
	return reset($array);
}

/** Read password from file adminer.key in temporary directory or create one
* @return string '' if the file can not be created
*/
function password_file(bool $create): string {
	$filename = get_temp_dir() . "/adminer.key";
	if (!$create && !file_exists($filename)) {
		return '';
	}
	$fp = file_open_lock($filename);
	if (!$fp) {
		return '';
	}
	$return = stream_get_contents($fp);
	if (!$return) {
		$return = rand_string();
		file_write_unlock($fp, $return);
	} else {
		file_unlock($fp);
	}
	return $return;
}

/** Get a random string
* @return string 32 hexadecimal characters
*/
function rand_string(): string {
	return md5(uniqid(strval(mt_rand()), true));
}

/** Format value to use in select
* @param string|string[] $val
* @param Field $field
* @param ?numeric-string $text_length
* @return string HTML
*/
function select_value($val, string $link, array $field, ?string $text_length): string {
	if (is_array($val)) {
		$return = "";
		foreach ($val as $k => $v) {
			$return .= "<tr>"
				. ($val != array_values($val) ? "<th>" . h($k) : "")
				. "<td>" . select_value($v, $link, $field, $text_length)
			;
		}
		return "<table>$return</table>";
	}
	if (!$link) {
		$link = adminer()->selectLink($val, $field);
	}
	if ($link === null) {
		if (is_mail($val)) {
			$link = "mailto:$val";
		}
		if (is_url($val)) {
			$link = $val; // IE 11 and all modern browsers hide referrer
		}
	}
	$return = adminer()->editVal($val, $field);
	if ($return !== null) {
		if (!is_utf8($return)) {
			$return = "\0"; // htmlspecialchars of binary data returns an empty string
		} elseif ($text_length != "" && is_shortable($field)) {
			$return = shorten_utf8($return, max(0, +$text_length)); // usage of LEFT() would reduce traffic but complicate query - expected average speedup: .001 s VS .01 s on local network
		} else {
			$return = h($return);
		}
	}
	return adminer()->selectVal($return, $link, $field, $val);
}

/** Check whether the field type is blob or equivalent
* @param Field $field
*/
function is_blob(array $field): bool {
	return preg_match('~blob|bytea|raw|file~', $field["type"]) && !in_array($field["type"], idx(driver()->structuredTypes(), lang('User types'), array()));
}

/** Check whether the string is e-mail address */
function is_mail(?string $email): bool {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	$pattern = "$atom+(\\.$atom+)*@($domain?\\.)+$domain";
	return is_string($email) && preg_match("(^$pattern(,\\s*$pattern)*\$)i", $email);
}

/** Check whether the string is URL address */
function is_url(?string $string): bool {
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component //! IDN
	return preg_match("~^(https?)://($domain?\\.)+$domain(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i", $string); //! restrict path, query and fragment characters
}

/** Check if field should be shortened
* @param Field $field
*/
function is_shortable(array $field): bool {
	return preg_match('~char|text|json|lob|geometry|point|linestring|polygon|string|bytea|hstore~', $field["type"]);
}

/** Split server into host and (port or socket)
* @return array{0: string, 1: string}
*/
function host_port(string $server) {
	return (preg_match('~^(\[(.+)]|([^:]+)):([^:]+)$~', $server, $match) // [a:b] - IPv6
		? array($match[2] . $match[3], $match[4])
		: array($server, '')
	);
}

/** Get query to compute number of found rows
* @param list<string> $where
* @param list<string> $group
*/
function count_rows(string $table, array $where, bool $is_group, array $group): string {
	$query = " FROM " . table($table) . ($where ? " WHERE " . implode(" AND ", $where) : "");
	return ($is_group && (JUSH == "sql" || count($group) == 1)
		? "SELECT COUNT(DISTINCT " . implode(", ", $group) . ")$query"
		: "SELECT COUNT(*)" . ($is_group ? " FROM (SELECT 1$query GROUP BY " . implode(", ", $group) . ") x" : $query)
	);
}

/** Run query which can be killed by AJAX call after timing out
* @return string[]
*/
function slow_query(string $query): array {
	$db = adminer()->database();
	$timeout = adminer()->queryTimeout();
	$slow_query = driver()->slowQuery($query, $timeout);
	$connection2 = null;
	if (!$slow_query && support("kill")) {
		$connection2 = connect();
		if ($connection2 && ($db == "" || $connection2->select_db($db))) {
			$kill = get_val(connection_id(), 0, $connection2); // MySQL and MySQLi can use thread_id but it's not in PDO_MySQL
			echo script("const timeout = setTimeout(() => { ajax('" . js_escape(ME) . "script=kill', function () {}, 'kill=$kill&token=" . get_token() . "'); }, 1000 * $timeout);");
		}
	}
	ob_flush();
	flush();
	$return = @get_key_vals(($slow_query ?: $query), $connection2, false); // @ - may be killed
	if ($connection2) {
		echo script("clearTimeout(timeout);");
		ob_flush();
		flush();
	}
	return $return;
}

/** Generate BREACH resistant CSRF token */
function get_token(): string {
	$rand = rand(1, 1e6);
	return ($rand ^ $_SESSION["token"]) . ":$rand";
}

/** Verify if supplied CSRF token is valid */
function verify_token(): bool {
	list($token, $rand) = explode(":", $_POST["token"]);
	return ($rand ^ $_SESSION["token"]) == $token;
}

// used in compiled version
function lzw_decompress(string $binary): string {
	// convert binary string to codes
	$dictionary_count = 256;
	$bits = 8; // ceil(log($dictionary_count, 2))
	$codes = array();
	$rest = 0;
	$rest_length = 0;
	for ($i=0; $i < strlen($binary); $i++) {
		$rest = ($rest << 8) + ord($binary[$i]);
		$rest_length += 8;
		if ($rest_length >= $bits) {
			$rest_length -= $bits;
			$codes[] = $rest >> $rest_length;
			$rest &= (1 << $rest_length) - 1;
			$dictionary_count++;
			if ($dictionary_count >> $bits) {
				$bits++;
			}
		}
	}
	// decompression
	/** @var list<?string> */
	$dictionary = range("\0", "\xFF");
	$return = "";
	$word = "";
	foreach ($codes as $i => $code) {
		$element = $dictionary[$code];
		if (!isset($element)) {
			$element = $word . $word[0];
		}
		$return .= $element;
		if ($i) {
			$dictionary[] = $word . $element[0];
		}
		$word = $element;
	}
	return $return;
}
