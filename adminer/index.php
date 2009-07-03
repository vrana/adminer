<?php
/** Adminer - Compact MySQL management
* @link http://www.adminer.org/
* @author Jakub Vrana, http://php.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
*/

include "./include/bootstrap.inc.php";
include "./include/version.inc.php";
include "./include/functions.inc.php";
include "./include/lang.inc.php";
include "./lang/$LANG.inc.php";
include "./include/adminer.inc.php";
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
