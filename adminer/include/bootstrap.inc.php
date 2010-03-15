<?php
error_reporting(6135); // errors and warnings

include "../adminer/include/coverage.inc.php";

// disable filter.default
$filter = (!ereg('^(unsafe_raw)?$', ini_get("filter.default")) || ini_get("filter.default_flags"));
if ($filter) {
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER') as $val) {
		$unsafe = filter_input_array(constant("INPUT$val"), FILTER_UNSAFE_RAW);
		if ($unsafe) {
			$$val = $unsafe;
		}
	}
}

// used only in compiled file
if (isset($_GET["file"])) {
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
	if ($_GET["file"] == "favicon.ico") {
		header("Content-Type: image/x-icon");
		echo base64_decode("compile_file('../adminer/static/favicon.ico', 'base64_encode');");
	} elseif ($_GET["file"] == "default.css") {
		header("Content-Type: text/css");
		?>compile_file('../adminer/static/default.css', 'minify_css');<?php
	} elseif ($_GET["file"] == "functions.js") {
		header("Content-Type: text/javascript");
		?>compile_file('../adminer/static/functions.js', 'JSMin::minify');compile_file('static/editing.js', 'JSMin::minify');<?php
	} else {
		header("Content-Type: image/gif");
		switch ($_GET["file"]) {
			case "plus.gif": echo base64_decode("compile_file('../adminer/static/plus.gif', 'base64_encode');"); break;
			case "cross.gif": echo base64_decode("compile_file('../adminer/static/cross.gif', 'base64_encode');"); break;
			case "up.gif": echo base64_decode("compile_file('../adminer/static/up.gif', 'base64_encode');"); break;
			case "down.gif": echo base64_decode("compile_file('../adminer/static/down.gif', 'base64_encode');"); break;
			case "arrow.gif": echo base64_decode("compile_file('../adminer/static/arrow.gif', 'base64_encode');"); break;
		}
	}
	exit;
}

if (!isset($_SERVER["REQUEST_URI"])) {
	$_SERVER["REQUEST_URI"] = $_SERVER["ORIG_PATH_INFO"] . ($_SERVER["QUERY_STRING"] != "" ? "?$_SERVER[QUERY_STRING]" : "");
}

session_write_close(); // disable session.auto_start
@ini_set("session.use_trans_sid", false); // protect links in export, @ - may be disabled
session_name("adminer_sid"); // use specific session name to get own namespace
$params = array(0, preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]), "", $_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off"));
if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
	$params[] = true; // HttpOnly
}
call_user_func_array('session_set_cookie_params', $params); // ini_set() may be disabled
session_start();

// disable magic quotes to be able to use database escaping function
if (get_magic_quotes_gpc()) {
	$process = array(&$_GET, &$_POST, &$_COOKIE);
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
	unset($process);
}
if (function_exists("set_magic_quotes_runtime")) {
	set_magic_quotes_runtime(false);
}
@set_time_limit(0); // @ - can be disabled

include "../adminer/include/version.inc.php";
include "../adminer/include/functions.inc.php";

define("DB", $_GET["db"]); // for the sake of speed and size
define("SID_FORM", SID && !ini_get("session.use_only_cookies") ? '<input type="hidden" name="' . session_name() . '" value="' . h(session_id()) . '">' : '');
define("ME", preg_replace('~^[^?]*/([^?]*).*~', '\\1', $_SERVER["REQUEST_URI"]) . '?' . (SID_FORM ? SID . '&' : '') . ($_GET["server"] != "" ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (DB != "" ? 'db=' . urlencode(DB) . '&' : ''));

include "../adminer/include/lang.inc.php";
include "../adminer/lang/$LANG.inc.php";
include "./include/adminer.inc.php";
include "../adminer/include/design.inc.php";
include "../adminer/include/pdo.inc.php";
include "../adminer/include/mysql.inc.php";
include "../adminer/include/xxtea.inc.php";
include "../adminer/include/auth.inc.php";
include "./include/connect.inc.php";
include "./include/editing.inc.php";
include "./include/export.inc.php";

session_cache_limiter(""); // to allow restarting session
if (!ini_get("session.use_cookies") || @ini_set("session.use_cookies", false) !== false) { // @ - may be disabled
	session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
}

$on_actions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION"); ///< @var array used in foreign_keys()
$confirm = " onclick=\"return confirm('" . lang('Are you sure?') . "');\""; ///< @var string
