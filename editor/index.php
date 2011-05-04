<?php
/** Adminer Editor - Compact database editor
* @link http://www.adminer.org/
* @author Jakub Vrana, http://www.vrana.cz/
* @copyright 2009 Jakub Vrana
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/

include "../adminer/include/bootstrap.inc.php";
$drivers[DRIVER] = lang('Login');

if (isset($_GET["select"]) && ($_POST["edit"] || $_POST["clone"]) && !$_POST["save"]) {
	$_GET["edit"] = $_GET["select"];
}
if (isset($_GET["download"])) {
	include "../adminer/download.inc.php";
} elseif (isset($_GET["edit"])) {
	include "../adminer/edit.inc.php";
} elseif (isset($_GET["select"])) {
	include "../adminer/select.inc.php";
} elseif (isset($_GET["script"])) {
	include "./script.inc.php";
} else {
	include "./db.inc.php";
}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
