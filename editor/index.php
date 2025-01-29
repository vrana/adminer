<?php
/**
 * AdminerNeo Editor - Compact database editor for end-users
 *
 * @link https://github.com/adminerneo/adminerneo
 * @author Jakub Vrana (https://www.vrana.cz/)
 * @author Peter Knut
 * @copyright 2009-2021 Jakub Vrana, 2024 Peter Knut
 * @license Apache License, Version 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * @license GNU General Public License, version 2 (https://www.gnu.org/licenses/gpl-2.0.html)
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
