<?php
namespace Adminer;

// this is matched by compile.php
include DIR . "include/coverage.inc.php"; // must be first, the coverage doesn't cover the files compiled before it starts
include DIR . "include/version.inc.php";
include DIR . "include/errors.inc.php";

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

// Adminer sends only string cookies, an array would break the code reading them and the user couldn't get rid of it from inside Adminer
$_COOKIE = array_filter($_COOKIE, 'is_scalar');

if (function_exists("mb_internal_encoding")) {
	mb_internal_encoding("8bit");
}

include DIR . "include/functions.inc.php";
include DIR . "include/decompress.inc.php";
include DIR . "include/html.inc.php";

// used only in compiled file
if (isset($_GET["file"])) {
	include DIR . "file.inc.php";
}

// Adminer doesn't use any global variables; they used to be declared here

if (preg_match('~^/[-\w.]~', $_SERVER["HTTP_X_FORWARDED_PREFIX"])) {
	$_SERVER["REQUEST_URI"] = $_SERVER["HTTP_X_FORWARDED_PREFIX"] . $_SERVER["REQUEST_URI"];
}
// session.cookie_secure could be set on HTTP if we are behind a reverse proxy
define('Adminer\HTTPS', ($_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off")) || ini_bool("session.cookie_secure"));

ini_set("session.use_trans_sid", '0'); // protect links in export
ini_set("arg_separator.output", "&"); // some hosts set it to "&amp;" which would break http_build_query()
// arg_separator.input is not checked - it is PHP_INI_PERDIR so we couldn't fix it and a value without & would break almost every PHP application
if (!defined("SID")) {
	session_cache_limiter(""); // to allow restarting session
	session_name("adminer_sid"); // use specific session name to get own namespace
	// ini_set() may be disabled
	if (PHP_VERSION_ID >= 70300) {
		session_set_cookie_params(array('lifetime' => 0, 'path' => cookie_path(), 'domain' => '', 'secure' => HTTPS, 'httponly' => true, 'samesite' => 'lax'));
	} else {
		session_set_cookie_params(0, cookie_path() . "; SameSite=lax", "", HTTPS, true);
	}
	session_start();
}

// disable magic quotes to be able to use database escaping function
if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
	$_GET = remove_slashes($_GET, $filter);
	$_POST = remove_slashes($_POST, $filter);
	$_COOKIE = remove_slashes($_COOKIE, $filter);
}
if (function_exists("get_magic_quotes_runtime") && get_magic_quotes_runtime()) {
	set_magic_quotes_runtime(false);
}
if (function_exists('set_time_limit')) { // can be disabled
	set_time_limit(0);
}
ini_set("precision", '16'); // 16 - IEEE 754 has 15.95 decimal digits for double

include DIR . "include/lang.inc.php";
include DIR . "lang/" . LANG . ".inc.php";
include DIR . "include/db.inc.php";
include DIR . "include/pdo.inc.php";
include DIR . "include/driver.inc.php";
include DIR . "drivers/pgsql.inc.php";
include DIR . "drivers/sqlite.inc.php";
include DIR . "drivers/mssql.inc.php";
include DIR . "drivers/oracle.inc.php";
include "./include/adminer.inc.php";
include DIR . "include/plugins.inc.php";
include DIR . "include/plugin.inc.php";

Adminer::$instance =
	(function_exists('adminer_object') ? adminer_object() :
	(is_dir("adminer-plugins") || file_exists("adminer-plugins.php") ? new Plugins(null) :
	new Adminer
));

// this is matched by compile.php
include DIR . "drivers/mysql.inc.php"; // must be included as last driver

define('Adminer\JUSH', Driver::$jush);
define('Adminer\SERVER', "" . $_GET[DRIVER]); // read from pgsql=localhost, '' means default server
define('Adminer\DB', "$_GET[db]"); // for the sake of speed and size
define(
	'Adminer\ME',
	preg_replace('~\?.*~', '', relative_uri()) . '?'
		. (sid() ? SID . '&' : '')
		. ($_GET["ext"] ? "ext=" . url_escape($_GET["ext"]) . '&' : '')
		. (isset($_GET[DRIVER]) ? DRIVER . "=" . url_escape(SERVER) . '&' : '') // no parameter means the default driver at the default server
		. (isset($_GET["username"]) ? "username=" . url_escape($_GET["username"]) . '&' : '')
		// an empty db= means the list of databases in a driver with a single database, the same way as an empty ns= means the database overview
		. (isset($_GET["db"]) ? 'db=' . url_escape(DB) . '&' . (isset($_GET["ns"]) ? "ns=" . url_escape($_GET["ns"]) . "&" : "") : '')
);

include DIR . "include/design.inc.php";
include DIR . "include/xxtea.inc.php";
include DIR . "include/auth.inc.php";
include "./include/editing.inc.php";
include "./include/connect.inc.php";

adminer()->afterConnect();
