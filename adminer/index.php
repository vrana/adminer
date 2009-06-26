<?php
/** Adminer - Compact MySQL management
* @link http://www.adminer.org/
* @author Jakub Vrana, http://php.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
*/

error_reporting(E_ALL & ~E_NOTICE);

// used only in compiled file
if (isset($_GET["file"])) {
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
	if ($_GET["file"] == "favicon.ico") {
		header("Content-Type: image/x-icon");
		echo base64_decode("compile_file('favicon.ico', 'base64_encode')");
	} elseif ($_GET["file"] == "default.css") {
		header("Content-Type: text/css");
		?>compile_file('default.css', 'minify_css')<?php
	} elseif ($_GET["file"] == "functions.js") {
		header("Content-Type: text/javascript");
		?>compile_file('functions.js', 'JSMin::minify')<?php
	} else {
		header("Content-Type: image/gif");
		switch ($_GET["file"]) {
			case "plus.gif": echo base64_decode("compile_file('plus.gif', 'base64_encode')"); break;
			case "cross.gif": echo base64_decode("compile_file('cross.gif', 'base64_encode')"); break;
			case "up.gif": echo base64_decode("compile_file('up.gif', 'base64_encode')"); break;
			case "down.gif": echo base64_decode("compile_file('down.gif', 'base64_encode')"); break;
			case "arrow.gif": echo base64_decode("compile_file('arrow.gif', 'base64_encode')"); break;
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
if (isset($_SESSION["coverage"])) {
	// coverage is used in tests and removed in compilation
	function save_coverage() {
		foreach (xdebug_get_code_coverage() as $filename => $lines) {
			foreach ($lines as $l => $val) {
				if (!$_SESSION["coverage"][$filename][$l] || $val > 0) {
					$_SESSION["coverage"][$filename][$l] = $val;
				}
			}
		}
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	register_shutdown_function('save_coverage');
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
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}
set_magic_quotes_runtime(false);
$SELF = preg_replace('~^[^?]*/([^?]*).*~', '\\1?', $_SERVER["REQUEST_URI"]) . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (strlen($_GET["db"]) ? 'db=' . urlencode($_GET["db"]) . '&' : '');

include "./include/version.inc.php";
include "./include/functions.inc.php";
include "./include/lang.inc.php";
include "./lang/$LANG.inc.php";
include "./include/design.inc.php";
if (isset($_GET["coverage"])) {
	include "./coverage.inc.php";
}
include "./include/pdo.inc.php";
include "./include/mysql.inc.php";
include "./include/auth.inc.php";
include "./include/connect.inc.php";
include "./include/editing.inc.php";
include "./include/export.inc.php";

$on_actions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION");
$enum_length = '\'(?:\'\'|[^\'\\\\]+|\\\\.)*\'|"(?:""|[^"\\\\]+|\\\\.)*"';
$inout = array("IN", "OUT", "INOUT");
$confirm = " onclick=\"return confirm('" . lang('Are you sure?') . "');\"";
$error = "";

if (isset($_GET["download"])) {
	include "./download.inc.php";
} elseif (isset($_GET["table"])) {
	include "./table.inc.php";
} elseif (isset($_GET["view"])) {
	include "./view.inc.php";
} elseif (isset($_GET["schema"])) {
	include "./schema.inc.php";
} elseif (isset($_GET["dump"])) {
	include "./dump.inc.php";
} elseif (isset($_GET["privileges"])) {
	include "./privileges.inc.php";
} else { // uses CSRF token
	$token = $_SESSION["tokens"][$_GET["server"]];
	if ($_POST) {
		if ($_POST["token"] != $token) {
			$error = lang('Invalid CSRF token. Send the form again.');
		}
	} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
		// posted form with no data means exceeded post_max_size because Adminer always sends token at least
		$error = lang('Too big POST data. Reduce the data or increase the "post_max_size" configuration directive.');
	}
	if (isset($_GET["default"])) {
		// edit form is used for default values and distinguished by checking isset($_GET["default"]) in edit.inc.php
		$_GET["edit"] = $_GET["default"];
	}
	if (isset($_GET["select"]) && $_POST && (!$_POST["delete"] && !$_POST["export"] && !$_POST["import"] && !$_POST["save"])) {
		// POST form on select page is used to edit or clone data
		$_GET["edit"] = $_GET["select"];
	}
	if (isset($_GET["callf"])) {
		$_GET["call"] = $_GET["callf"];
	}
	if (isset($_GET["function"])) {
		$_GET["procedure"] = $_GET["function"];
	}
	if (isset($_GET["sql"])) {
		include "./sql.inc.php";
	} elseif (isset($_GET["edit"])) {
		include "./edit.inc.php";
	} elseif (isset($_GET["create"])) {
		include "./create.inc.php";
	} elseif (isset($_GET["indexes"])) {
		include "./indexes.inc.php";
	} elseif (isset($_GET["database"])) {
		include "./database.inc.php";
	} elseif (isset($_GET["call"])) {
		include "./call.inc.php";
	} elseif (isset($_GET["foreign"])) {
		include "./foreign.inc.php";
	} elseif (isset($_GET["createv"])) {
		include "./createv.inc.php";
	} elseif (isset($_GET["event"])) {
		include "./event.inc.php";
	} elseif (isset($_GET["procedure"])) {
		include "./procedure.inc.php";
	} elseif (isset($_GET["trigger"])) {
		include "./trigger.inc.php";
	} elseif (isset($_GET["user"])) {
		include "./user.inc.php";
	} elseif (isset($_GET["processlist"])) {
		include "./processlist.inc.php";
	} elseif (isset($_GET["select"])) {
		include "./select.inc.php";
	} elseif (isset($_GET["variables"])) {
		include "./variables.inc.php";
	} else {
		include "./db.inc.php";
	}
}
// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
