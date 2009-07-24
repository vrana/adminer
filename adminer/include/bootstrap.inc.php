<?php
error_reporting(4343); // errors and warnings

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
		echo base64_decode("compile_file('../adminer/favicon.ico', 'base64_encode');");
	} elseif ($_GET["file"] == "default.css") {
		header("Content-Type: text/css");
		?>compile_file('../adminer/default.css', 'minify_css');<?php
	} elseif ($_GET["file"] == "functions.js") {
		header("Content-Type: text/javascript");
		?>compile_file('../adminer/functions.js', 'JSMin::minify');compile_file('editing.js', 'JSMin::minify');<?php
	} else {
		header("Content-Type: image/gif");
		switch ($_GET["file"]) {
			case "plus.gif": echo base64_decode("compile_file('../adminer/plus.gif', 'base64_encode');"); break;
			case "cross.gif": echo base64_decode("compile_file('../adminer/cross.gif', 'base64_encode');"); break;
			case "up.gif": echo base64_decode("compile_file('../adminer/up.gif', 'base64_encode');"); break;
			case "down.gif": echo base64_decode("compile_file('../adminer/down.gif', 'base64_encode');"); break;
			case "arrow.gif": echo base64_decode("compile_file('../adminer/arrow.gif', 'base64_encode');"); break;
		}
	}
	exit;
}

if (!ini_get("session.auto_start")) {
	// use specific session name to get own namespace
	session_name("adminer_sid");
	session_set_cookie_params(0, preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"])); //! use HttpOnly in PHP 5
	session_start();
}

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
set_magic_quotes_runtime(false);

$SELF = (isset($_SERVER["REQUEST_URI"]) ? preg_replace('~^[^?]*/([^?]*).*~', '\\1', $_SERVER["REQUEST_URI"]) : $_SERVER["ORIG_PATH_INFO"]) . '?' . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (strlen($_GET["db"]) ? 'db=' . urlencode($_GET["db"]) . '&' : '');
$on_actions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION"); // used in foreign_keys()

include "../adminer/include/version.inc.php";
include "../adminer/include/functions.inc.php";
include "../adminer/include/lang.inc.php";
include "../adminer/lang/$LANG.inc.php";
include "./include/adminer.inc.php";
include "../adminer/include/design.inc.php";
include "../adminer/include/pdo.inc.php";
include "../adminer/include/mysql.inc.php";
include "../adminer/include/auth.inc.php";
include "./include/connect.inc.php";
include "./include/editing.inc.php";
include "./include/export.inc.php";

$confirm = " onclick=\"return confirm('" . lang('Are you sure?') . "');\"";
$token = $_SESSION["tokens"][$_GET["server"]];
$error = ($_POST
	? ($_POST["token"] == $token ? "" : lang('Invalid CSRF token. Send the form again.'))
	: ($_SERVER["REQUEST_METHOD"] != "POST" ? "" : lang('Too big POST data. Reduce the data or increase the "post_max_size" configuration directive.')) // posted form with no data means that post_max_size exceeded because Adminer always sends token at least
);
