<?php
namespace Adminer;

// This file is used both in Adminer and Adminer Editor.

/** Get database connection
* @return Db
*/
function connection() {
	// can be used in customization, $connection is minified
	global $connection;
	return $connection;
}

/** Get Adminer object
* @return Adminer
*/
function adminer() {
	global $adminer;
	return $adminer;
}

/** Get Driver object
* @return Driver
*/
function driver() {
	global $driver;
	return $driver;
}

/** Get Adminer version
* @return string
*/
function version() {
	global $VERSION;
	return $VERSION;
}

/** Unescape database identifier
* @param string text inside ``
* @return string
*/
function idf_unescape($idf) {
	if (!preg_match('~^[`\'"[]~', $idf)) {
		return $idf;
	}
	$last = substr($idf, -1);
	return str_replace($last . $last, $last, substr($idf, 1, -1));
}

/** Shortcut for $connection->quote($string)
* @param string
* @return string
*/
function q($string) {
	global $connection;
	return $connection->quote($string);
}

/** Escape string to use inside ''
* @param string
* @return string
*/
function escape_string($val) {
	return substr(q($val), 1, -1);
}

/** Remove non-digits from a string
* @param string
* @return string
*/
function number($val) {
	return preg_replace('~[^0-9]+~', '', $val);
}

/** Get regular expression to match numeric types
* @return string
*/
function number_type() {
	return '((?<!o)int(?!er)|numeric|real|float|double|decimal|money)'; // not point, not interval
}

/** Disable magic_quotes_gpc
* @param array e.g. (&$_GET, &$_POST, &$_COOKIE)
* @param bool whether to leave values as is
* @return null modified in place
*/
function remove_slashes($process, $filter = false) {
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

/** Escape or unescape string to use inside form []
* @param string
* @param bool
* @return string
*/
function bracket_escape($idf, $back = false) {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3', '"' => ':4');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

/** Check if connection has at least the given version
* @param string required version
* @param string required MariaDB version
* @param Db defaults to $connection
* @return bool
*/
function min_version($version, $maria_db = "", $connection2 = null) {
	global $connection;
	if (!$connection2) {
		$connection2 = $connection;
	}
	$server_info = $connection2->server_info;
	if ($maria_db && preg_match('~([\d.]+)-MariaDB~', $server_info, $match)) {
		$server_info = $match[1];
		$version = $maria_db;
	}
	return $version && version_compare($server_info, $version) >= 0;
}

/** Get connection charset
* @param Db
* @return string
*/
function charset($connection) {
	return (min_version("5.5.3", 0, $connection) ? "utf8mb4" : "utf8"); // SHOW CHARSET would require an extra query
}

/** Get INI boolean value
* @param string
* @return bool
*/
function ini_bool($ini) {
	$val = ini_get($ini);
	return (preg_match('~^(on|true|yes)$~i', $val) || (int) $val); // boolean values set by php_value are strings
}

/** Check if SID is necessary
* @return bool
*/
function sid() {
	static $return;
	if ($return === null) { // restart_session() defines SID
		$return = (SID && !($_COOKIE && ini_bool("session.use_cookies"))); // $_COOKIE - don't pass SID with permanent login
	}
	return $return;
}

/** Set password to session
* @param string
* @param string
* @param string
* @param string
* @return null
*/
function set_password($vendor, $server, $username, $password) {
	$_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"] && is_string($password)
		? array(encrypt_string($password, $_COOKIE["adminer_key"]))
		: $password
	);
}

/** Get password from session
* @return string or null for missing password or false for expired password
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
* @param string
* @param int
* @return string or false if error
*/
function get_val($query, $field = 0) {
	global $connection;
	return $connection->result($query, $field);
}

/** Get list of values from database
* @param string
* @param mixed
* @return list<string>
*/
function get_vals($query, $column = 0) {
	global $connection;
	$return = array();
	$result = $connection->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
	}
	return $return;
}

/** Get keys from first column and values from second
* @param string
* @param Db
* @param bool
* @return string[]
*/
function get_key_vals($query, $connection2 = null, $set_keys = true) {
	global $connection;
	if (!is_object($connection2)) {
		$connection2 = $connection;
	}
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
* @param string
* @param Db
* @param string
* @return list<string[]> of associative arrays
*/
function get_rows($query, $connection2 = null, $error = "<p class='error'>") {
	global $connection;
	$conn = (is_object($connection2) ? $connection2 : $connection);
	$return = array();
	$result = $conn->query($query);
	if (is_object($result)) { // can return true
		while ($row = $result->fetch_assoc()) {
			$return[] = $row;
		}
	} elseif (!$result && !is_object($connection2) && $error && (defined('Adminer\PAGE_HEADER') || $error == "-- ")) {
		echo $error . error() . "\n";
	}
	return $return;
}

/** Find unique identifier of a row
* @param array
* @param array result of indexes()
* @return string[] or null if there is no unique identifier
*/
function unique_array($row, $indexes) {
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

/** Escape column key used in where()
* @param string
* @return string
*/
function escape_key($key) {
	if (preg_match('(^([\w(]+)(' . str_replace("_", ".*", preg_quote(idf_escape("_"))) . ')([ \w)]+)$)', $key, $match)) { //! columns looking like functions
		return $match[1] . idf_escape(idf_unescape($match[2])) . $match[3]; //! SQL injection
	}
	return idf_escape($key);
}

/** Create SQL condition from parsed query string
* @param array parsed query string
* @param array
* @return string
*/
function where($where, $fields = array()) {
	global $connection;
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, 1); // 1 - back
		$column = escape_key($key);
		$field_type = $fields[$key]["type"];
		$return[] = $column
			. (JUSH == "sql" && $field_type == "json" ? " = CAST(" . q($val) . " AS JSON)"
				: (JUSH == "sql" && is_numeric($val) && preg_match('~\.~', $val) ? " LIKE " . q($val) // LIKE because of floats but slow with ints
				: (JUSH == "mssql" && strpos($field_type, "datetime") === false ? " LIKE " . q(preg_replace('~[_%[]~', '[\0]', $val)) // LIKE because of text but it does not work with datetime
				: " = " . unconvert_field($fields[$key], q($val)))))
		; //! enum and set
		if (JUSH == "sql" && preg_match('~char|text~', $field_type) && preg_match("~[^ -@]~", $val)) { // not just [a-z] to catch non-ASCII characters
			$return[] = "$column = " . q($val) . " COLLATE " . charset($connection) . "_bin";
		}
	}
	foreach ((array) $where["null"] as $key) {
		$return[] = escape_key($key) . " IS NULL";
	}
	return implode(" AND ", $return);
}

/** Create SQL condition from query string
* @param string
* @param array
* @return string
*/
function where_check($val, $fields = array()) {
	parse_str($val, $check);
	remove_slashes(array(&$check));
	return where($check, $fields);
}

/** Create query string where condition from value
* @param int condition order
* @param string column identifier
* @param string
* @param string
* @return string
*/
function where_link($i, $column, $value, $operator = "=") {
	return "&where%5B$i%5D%5Bcol%5D=" . urlencode($column) . "&where%5B$i%5D%5Bop%5D=" . urlencode(($value !== null ? $operator : "IS NULL")) . "&where%5B$i%5D%5Bval%5D=" . urlencode($value);
}

/** Get select clause for convertible fields
* @param array
* @param array
* @param array
* @return string
*/
function convert_fields($columns, $fields, $select = array()) {
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
* @param string
* @param string
* @param int number of seconds, 0 for session cookie, 2592000 - 30 days
* @return bool
*/
function cookie($name, $value, $lifetime = 2592000) {
	global $HTTPS;
	return header(
		"Set-Cookie: $name=" . urlencode($value)
			. ($lifetime ? "; expires=" . gmdate("D, d M Y H:i:s", time() + $lifetime) . " GMT" : "")
			. "; path=" . preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"])
			. ($HTTPS ? "; secure" : "")
			. "; HttpOnly; SameSite=lax",
		false
	);
}

/** Get settings stored in a cookie
* @param string
* @return mixed[]
*/
function get_settings($cookie) {
	parse_str($_COOKIE[$cookie], $settings);
	return $settings;
}

/** Get setting stored in a cookie
* @param string
* @param string
* @return mixed
*/
function get_setting($key, $cookie = "adminer_settings") {
	$settings = get_settings($cookie);
	return $settings[$key];
}

/** Store settings to a cookie
* @param mixed[]
* @param string
* @return bool
*/
function save_settings($settings, $cookie = "adminer_settings") {
	return cookie($cookie, http_build_query($settings + get_settings($cookie)));
}

/** Restart stopped session
* @return null
*/
function restart_session() {
	if (!ini_bool("session.use_cookies") && (!function_exists('session_status') || session_status() == 1)) { // 1 - PHP_SESSION_NONE, session_status() available since PHP 5.4
		session_start();
	}
}

/** Stop session if possible
* @param bool
* @return null
*/
function stop_session($force = false) {
	$use_cookies = ini_bool("session.use_cookies");
	if (!$use_cookies || $force) {
		session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
		if ($use_cookies && @ini_set("session.use_cookies", false) === false) { // @ - may be disabled
			session_start();
		}
	}
}

/** Get session variable for current server
* @param string
* @return mixed
*/
function &get_session($key) {
	return $_SESSION[$key][DRIVER][SERVER][$_GET["username"]];
}

/** Set session variable for current server
* @param string
* @param mixed
* @return mixed
*/
function set_session($key, $val) {
	$_SESSION[$key][DRIVER][SERVER][$_GET["username"]] = $val; // used also in auth.inc.php
}

/** Get authenticated URL
* @param string
* @param string
* @param string
* @param string
* @return string
*/
function auth_url($vendor, $server, $username, $db = null) {
	global $drivers;
	$uri = remove_from_uri(implode("|", array_keys($drivers))
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

/** Find whether it is an AJAX request
* @return bool
*/
function is_ajax() {
	return ($_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest");
}

/** Send Location header and exit
* @param string null to only set a message
* @param string
* @return null
*/
function redirect($location, $message = null) {
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
* @param string
* @param string
* @param string
* @param bool
* @param bool
* @param bool
* @param string
* @return bool
*/
function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false, $time = "") {
	global $connection, $error, $adminer;
	if ($execute) {
		$start = microtime(true);
		$failed = !$connection->query($query);
		$time = format_time($start);
	}
	$sql = "";
	if ($query) {
		$sql = $adminer->messageQuery($query, $time, $failed);
	}
	if ($failed) {
		$error = error() . $sql . script("messagesPrint();");
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

/** Execute and remember query
* @param string or null to return remembered queries, end with ';' to use DELIMITER
* @return Result or [$queries, $time] if $query = null
*/
function queries($query) {
	global $connection;
	static $queries = array();
	static $start;
	if (!$start) {
		$start = microtime(true);
	}
	if ($query === null) {
		// return executed queries
		return array(implode("\n", $queries), format_time($start));
	}
	$queries[] = (preg_match('~;$~', $query) ? "DELIMITER ;;\n$query;\nDELIMITER " : $query) . ";";
	return $connection->query($query);
}

/** Apply command to all array items
* @param string
* @param array
* @param callback
* @return bool
*/
function apply_queries($query, $tables, $escape = 'Adminer\table') {
	foreach ($tables as $table) {
		if (!queries("$query " . $escape($table))) {
			return false;
		}
	}
	return true;
}

/** Redirect by remembered queries
* @param string
* @param string
* @param bool
* @return bool
*/
function queries_redirect($location, $message, $redirect) {
	list($queries, $time) = queries(null);
	return query_redirect($queries, $location, $message, $redirect, false, !$redirect, $time);
}

/** Format elapsed time
* @param float output of microtime(true)
* @return string HTML code
*/
function format_time($start) {
	return lang('%.3f s', max(0, microtime(true) - $start));
}

/** Get relative REQUEST_URI
* @return string
*/
function relative_uri() {
	return str_replace(":", "%3a", preg_replace('~^[^?]*/([^?]*)~', '\1', $_SERVER["REQUEST_URI"]));
}

/** Remove parameter from query string
* @param string
* @return string
*/
function remove_from_uri($param = "") {
	return substr(preg_replace("~(?<=[?&])($param" . (SID ? "" : "|" . session_name()) . ")=[^&]*&~", '', relative_uri() . "&"), 0, -1);
}

/** Get file contents from $_FILES
* @param string
* @param bool
* @param string
* @return mixed int for error, string otherwise
*/
function get_file($key, $decompress = false, $delimiter = "") {
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

/** Determine upload error
* @param int
* @return string
*/
function upload_error($error) {
	$max_size = ($error == UPLOAD_ERR_INI_SIZE ? ini_get("upload_max_filesize") : 0); // post_max_size is checked in index.php
	return ($error ? lang('Unable to upload a file.') . ($max_size ? " " . lang('Maximum allowed file size is %sB.', $max_size) : "") : lang('File does not exist.'));
}

/** Create repeat pattern for preg
* @param string
* @param int
* @return string
*/
function repeat_pattern($pattern, $length) {
	// fix for Compilation failed: number too big in {} quantifier
	return str_repeat("$pattern{0,65535}", $length / 65535) . "$pattern{0," . ($length % 65535) . "}"; // can create {0,0} which is OK
}

/** Check whether the string is in UTF-8
* @param string
* @return bool
*/
function is_utf8($val) {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\0-\x8\xB\xC\xE-\x1F]~', $val));
}

/** Shorten UTF-8 string
* @param string
* @param int
* @param string
* @return string escaped string with appended ...
*/
function shorten_utf8($string, $length = 80, $suffix = "") {
	if (!preg_match("(^(" . repeat_pattern("[\t\r\n -\x{10FFFF}]", $length) . ")($)?)u", $string, $match)) { // ~s causes trash in $match[2] under some PHP versions, (.|\n) is slow
		preg_match("(^(" . repeat_pattern("[\t\r\n -~]", $length) . ")($)?)", $string, $match);
	}
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<i>…</i>");
}

/** Format decimal number
* @param int
* @return string
*/
function format_number($val) {
	return strtr(number_format($val, 0, ".", lang(',')), preg_split('~~u', lang('0123456789'), -1, PREG_SPLIT_NO_EMPTY));
}

/** Generate friendly URL
* @param string
* @return string
*/
function friendly_url($val) {
	// used for blobs and export
	return preg_replace('~\W~i', '-', $val);
}

/** Get status of a single table and fall back to name on error
* @param string
* @param bool
* @return array[]
*/
function table_status1($table, $fast = false) {
	$return = table_status($table, $fast);
	return ($return ?: array("Name" => $table));
}

/** Find out foreign keys for each column
* @param string
* @return list<string>[] [$col => []]
*/
function column_foreign_keys($table) {
	global $adminer;
	$return = array();
	foreach ($adminer->foreignKeys($table) as $foreign_key) {
		foreach ($foreign_key["source"] as $val) {
			$return[$val][] = $foreign_key;
		}
	}
	return $return;
}

/** Compute fields() from $_POST edit data
* @return array[]
*/
function fields_from_edit() {
	global $driver;
	$return = array();
	foreach ((array) $_POST["field_keys"] as $key => $val) {
		if ($val != "") {
			$val = bracket_escape($val);
			$_POST["function"][$val] = $_POST["field_funs"][$key];
			$_POST["fields"][$val] = $_POST["field_vals"][$key];
		}
	}
	foreach ((array) $_POST["fields"] as $key => $val) {
		$name = bracket_escape($key, 1); // 1 - back
		$return[$name] = array(
			"field" => $name,
			"privileges" => array("insert" => 1, "update" => 1, "where" => 1, "order" => 1),
			"null" => 1,
			"auto_increment" => ($key == $driver->primary),
		);
	}
	return $return;
}

/** Send headers for export
* @param string
* @param bool
* @return string extension
*/
function dump_headers($identifier, $multi_table = false) {
	global $adminer;
	$return = $adminer->dumpHeaders($identifier, $multi_table);
	$output = $_POST["output"];
	if ($output != "text") {
		header("Content-Disposition: attachment; filename=" . $adminer->dumpFilename($identifier) . ".$return" . ($output != "file" && preg_match('~^[0-9a-z]+$~', $output) ? ".$output" : ""));
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
* @param array
* @return null
*/
function dump_csv($row) {
	foreach ($row as $key => $val) {
		if (preg_match('~["\n,;\t]|^0|\.\d*0$~', $val) || $val === "") {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(($_POST["format"] == "csv" ? "," : ($_POST["format"] == "tsv" ? "\t" : ";")), $row) . "\r\n";
}

/** Apply SQL function
* @param string
* @param string escaped column identifier
* @return string
*/
function apply_sql_function($function, $column) {
	return ($function ? ($function == "unixepoch" ? "DATETIME($column, '$function')" : ($function == "count distinct" ? "COUNT(DISTINCT " : strtoupper("$function(")) . "$column)") : $column);
}

/** Get path of the temporary directory
* @return string
*/
function get_temp_dir() {
	$return = ini_get("upload_tmp_dir"); // session_save_path() may contain other storage path
	if (!$return) {
		if (function_exists('sys_get_temp_dir')) {
			$return = sys_get_temp_dir();
		} else {
			$filename = @tempnam("", ""); // @ - temp directory can be disabled by open_basedir
			if (!$filename) {
				return false;
			}
			$return = dirname($filename);
			unlink($filename);
		}
	}
	return $return;
}

/** Open and exclusively lock a file
* @param string
* @return resource or null for error
*/
function file_open_lock($filename) {
	if (is_link($filename)) {
		return; // https://cwe.mitre.org/data/definitions/61.html
	}
	$fp = @fopen($filename, "c+"); // @ - may not be writable
	if (!$fp) {
		return;
	}
	chmod($filename, 0660);
	if (!flock($fp, LOCK_EX)) {
		fclose($fp);
		return;
	}
	return $fp;
}

/** Write and unlock a file
* @param resource
* @param string
*/
function file_write_unlock($fp, $data) {
	rewind($fp);
	fwrite($fp, $data);
	ftruncate($fp, strlen($data));
	file_unlock($fp);
}

/** Unlock and close a file
* @param resource
*/
function file_unlock($fp) {
	flock($fp, LOCK_UN);
	fclose($fp);
}

/** Get first element of an array
* @param array
* @return mixed or false if not found
*/
function first($array) {
	// reset(f()) triggers a notice
	return reset($array);
}

/** Read password from file adminer.key in temporary directory or create one
* @param bool
* @return string or false if the file can not be created
*/
function password_file($create) {
	$filename = get_temp_dir() . "/adminer.key";
	if (!$create && !file_exists($filename)) {
		return false;
	}
	$fp = file_open_lock($filename);
	if (!$fp) {
		return false;
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
function rand_string() {
	return md5(uniqid(mt_rand(), true));
}

/** Format value to use in select
* @param string
* @param string
* @param array
* @param int
* @return string HTML
*/
function select_value($val, $link, $field, $text_length) {
	global $adminer;
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
		$link = $adminer->selectLink($val, $field);
	}
	if ($link === null) {
		if (is_mail($val)) {
			$link = "mailto:$val";
		}
		if (is_url($val)) {
			$link = $val; // IE 11 and all modern browsers hide referrer
		}
	}
	$return = $adminer->editVal($val, $field);
	if ($return !== null) {
		if (!is_utf8($return)) {
			$return = "\0"; // htmlspecialchars of binary data returns an empty string
		} elseif ($text_length != "" && is_shortable($field)) {
			$return = shorten_utf8($return, max(0, +$text_length)); // usage of LEFT() would reduce traffic but complicate query - expected average speedup: .001 s VS .01 s on local network
		} else {
			$return = h($return);
		}
	}
	return $adminer->selectVal($return, $link, $field, $val);
}

/** Check whether the string is e-mail address
* @param string
* @return bool
*/
function is_mail($email) {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	$pattern = "$atom+(\\.$atom+)*@($domain?\\.)+$domain";
	return is_string($email) && preg_match("(^$pattern(,\\s*$pattern)*\$)i", $email);
}

/** Check whether the string is URL address
* @param string
* @return bool
*/
function is_url($string) {
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component //! IDN
	return preg_match("~^(https?)://($domain?\\.)+$domain(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i", $string); //! restrict path, query and fragment characters
}

/** Check if field should be shortened
* @param array
* @return bool
*/
function is_shortable($field) {
	return preg_match('~char|text|json|lob|geometry|point|linestring|polygon|string|bytea~', $field["type"]);
}

/** Get query to compute number of found rows
* @param string
* @param array
* @param bool
* @param array
* @return string
*/
function count_rows($table, $where, $is_group, $group) {
	$query = " FROM " . table($table) . ($where ? " WHERE " . implode(" AND ", $where) : "");
	return ($is_group && (JUSH == "sql" || count($group) == 1)
		? "SELECT COUNT(DISTINCT " . implode(", ", $group) . ")$query"
		: "SELECT COUNT(*)" . ($is_group ? " FROM (SELECT 1$query GROUP BY " . implode(", ", $group) . ") x" : $query)
	);
}

/** Run query which can be killed by AJAX call after timing out
* @param string
* @return string[]
*/
function slow_query($query) {
	global $adminer, $token, $driver;
	$db = $adminer->database();
	$timeout = $adminer->queryTimeout();
	$slow_query = $driver->slowQuery($query, $timeout);
	$connection2 = null;
	if (!$slow_query && support("kill") && is_object($connection2 = connect($adminer->credentials())) && ($db == "" || $connection2->select_db($db))) {
		$kill = $connection2->result(connection_id()); // MySQL and MySQLi can use thread_id but it's not in PDO_MySQL
		echo script("const timeout = setTimeout(() => { ajax('" . js_escape(ME) . "script=kill', function () {}, 'kill=$kill&token=$token'); }, 1000 * $timeout);");
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

/** Generate BREACH resistant CSRF token
* @return string
*/
function get_token() {
	$rand = rand(1, 1e6);
	return ($rand ^ $_SESSION["token"]) . ":$rand";
}

/** Verify if supplied CSRF token is valid
* @return bool
*/
function verify_token() {
	list($token, $rand) = explode(":", $_POST["token"]);
	return ($rand ^ $_SESSION["token"]) == $token;
}

// used in compiled version
function lzw_decompress($binary) {
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
	$dictionary = range("\0", "\xFF");
	$return = "";
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
