<?php
namespace Adminer;

include "../adminer/include/version.inc.php";
include "../adminer/include/errors.inc.php";
// this is matched by compile.php
include "../adminer/include/coverage.inc.php";

// disable filter.default
$filter = !preg_match('~^(unsafe_raw)?$~', ini_get("filter.default"));
if ($filter || ini_get("filter.default_flags")) {
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER') as $val) {
		$unsafe = filter_input_array(constant("INPUT$val"), FILTER_UNSAFE_RAW);
		if ($unsafe) {
			$$val = $unsafe;
		}
	}
}

if (function_exists("mb_internal_encoding")) {
	mb_internal_encoding("8bit");
}

include "../adminer/include/functions.inc.php";
include "../adminer/include/html.inc.php";

// used only in compiled file
if (isset($_GET["file"])) {
	include "../adminer/file.inc.php";
}

if ($_GET["script"] == "version") {
	$filename = get_temp_dir() . "/adminer.version";
	@unlink($filename); // it may not be writable by us, @ - it may not exist
	$fp = file_open_lock($filename);
	if ($fp) {
		file_write_unlock($fp, serialize(array("signature" => $_POST["signature"], "version" => $_POST["version"])));
	}
	exit;
}

// Adminer doesn't use any global variables; they used to be declared here

if (!$_SERVER["REQUEST_URI"]) { // IIS 5 compatibility
	$_SERVER["REQUEST_URI"] = $_SERVER["ORIG_PATH_INFO"];
}
if (!strpos($_SERVER["REQUEST_URI"], '?') && $_SERVER["QUERY_STRING"] != "") { // IIS 7 compatibility
	$_SERVER["REQUEST_URI"] .= "?$_SERVER[QUERY_STRING]";
}
if ($_SERVER["HTTP_X_FORWARDED_PREFIX"]) {
	$_SERVER["REQUEST_URI"] = $_SERVER["HTTP_X_FORWARDED_PREFIX"] . $_SERVER["REQUEST_URI"];
}
define('Adminer\HTTPS', ($_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off")) || ini_bool("session.cookie_secure")); // session.cookie_secure could be set on HTTP if we are behind a reverse proxy

@ini_set("session.use_trans_sid", '0'); // protect links in export, @ - may be disabled
if (!defined("SID")) {
	session_cache_limiter(""); // to allow restarting session
	session_name("adminer_sid"); // use specific session name to get own namespace
	session_set_cookie_params(0, preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"]), "", HTTPS, true); // ini_set() may be disabled
	session_start();
}

// disable magic quotes to be able to use database escaping function
remove_slashes(array(&$_GET, &$_POST, &$_COOKIE), $filter);
if (function_exists("get_magic_quotes_runtime") && get_magic_quotes_runtime()) {
	set_magic_quotes_runtime(false);
}
@set_time_limit(0); // @ - can be disabled
@ini_set("precision", '15'); // @ - can be disabled, 15 - internal PHP precision

include "../adminer/include/lang.inc.php";
include "../adminer/lang/" . LANG . ".inc.php";
include "../adminer/include/db.inc.php";
include "../adminer/include/pdo.inc.php";
include "../adminer/include/driver.inc.php";
include "../adminer/drivers/sqlite.inc.php";
include "../adminer/drivers/pgsql.inc.php";
include "../adminer/drivers/oracle.inc.php";
include "../adminer/drivers/mssql.inc.php";
include "./include/adminer.inc.php";
include "../adminer/include/plugins.inc.php";

if (function_exists('adminer_object')) {
	Adminer::$instance = adminer_object();
} elseif (is_dir("adminer-plugins") || file_exists("adminer-plugins.php")) {
	Adminer::$instance = new Plugins(null);
} else {
	Adminer::$instance = new Adminer;
}

// this is matched by compile.php
include "../adminer/drivers/mysql.inc.php"; // must be included as last driver

define('Adminer\JUSH', Driver::$jush);
define('Adminer\SERVER', $_GET[DRIVER]); // read from pgsql=localhost, '' means default server, null means no server
define('Adminer\DB', $_GET["db"]); // for the sake of speed and size
define(
	'Adminer\ME',
	preg_replace('~\?.*~', '', relative_uri()) . '?'
		. (sid() ? SID . '&' : '')
		. (SERVER !== null ? DRIVER . "=" . urlencode(SERVER) . '&' : '')
		. ($_GET["ext"] ? "ext=" . urlencode($_GET["ext"]) . '&' : '')
		. (isset($_GET["username"]) ? "username=" . urlencode($_GET["username"]) . '&' : '')
		. (DB != "" ? 'db=' . urlencode(DB) . '&' . (isset($_GET["ns"]) ? "ns=" . urlencode($_GET["ns"]) . "&" : "") : '')
);

include "../adminer/include/design.inc.php";
include "../adminer/include/xxtea.inc.php";
include "../adminer/include/auth.inc.php";
include "./include/editing.inc.php";
include "./include/connect.inc.php";
