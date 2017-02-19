<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, http://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/

include "./include/bootstrap.inc.php";
include "./include/tmpfile.inc.php";

$enum_length = "'(?:''|[^'\\\\]|\\\\.)*'";
$inout = "IN|OUT|INOUT";

if (isset($_GET["select"]) && ($_POST["edit"] || $_POST["clone"]) && !$_POST["save"]) {
	$_GET["edit"] = $_GET["select"];
}
if (isset($_GET["callf"])) {
	$_GET["call"] = $_GET["callf"];
}
if (isset($_GET["function"])) {
	$_GET["procedure"] = $_GET["function"];
}

$sections = explode("|", "download|table|schema|dump|privileges|sql|edit|create|indexes|database|scheme|call|foreign|view|event|proccedure|sequence|selectt|variables|script|db");
while (list(, $section) = each($sections)) {
	if (isset $_GET[$section] || $section === 'db') {
		include "./{$section}.inc.php";
		break;
	}
}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
