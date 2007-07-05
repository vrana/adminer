<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);
$SELF = preg_replace('~^[^?]*/([^?]*).*~', '\\1?', $_SERVER["REQUEST_URI"]) . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (strlen($_GET["db"]) ? 'db=' . urlencode($_GET["db"]) . '&' : '');
include "./functions.inc.php";
include "./design.inc.php";
include "./auth.inc.php";
include "./connect.inc.php";

if (isset($_GET["sql"])) {
	include "./sql.inc.php";
} elseif (isset($_GET["table"])) {
	include "./table.inc.php";
} elseif (isset($_GET["select"])) {
	include "./select.inc.php";
} elseif (isset($_GET["edit"])) {
	include "./edit.inc.php";
} elseif (isset($_GET["create"])) {
	include "./create.inc.php";
} elseif (isset($_GET["indexes"])) {
	include "./indexes.inc.php";
} elseif (isset($_GET["dump"])) {
	include "./dump.inc.php";
} elseif (isset($_GET["view"])) {
	include "./view.inc.php";
} elseif (isset($_GET["database"])) {
	include "./database.inc.php";
} else {
	page_header(htmlspecialchars(lang('Database') . ": " . $_GET["db"]));
	echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Alter database') . "</a></p>\n";
	$result = mysql_query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '" . mysql_real_escape_string($_GET["db"]) . "'");
	if (mysql_num_rows($result)) {
		echo "<h2>" . lang('Routines') . "</h2>\n";
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		while ($row = mysql_fetch_assoc($result)) {
			echo "<tr>";
			echo "<td>" . htmlspecialchars($row["ROUTINE_TYPE"]) . "</td>";
			echo "<th>" . htmlspecialchars($row["ROUTINE_NAME"]) . "</th>"; //! parameters from SHOW CREATE {PROCEDURE|FUNCTION}
			echo "<td>" . nl2br(htmlspecialchars($row["ROUTINE_DEFINITION"])) . "</td>";
			echo "</tr>\n";
			//! call, drop, replace
		}
		echo "</table>\n";
	}
	mysql_free_result($result);
}

page_footer();
