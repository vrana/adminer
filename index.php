<?php
// Copyright 2007 Jakub Vrana http://phpminadmin.sourceforge.net, licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.

session_start();
error_reporting(E_ALL & ~E_NOTICE);
$SELF = preg_replace('~^[^?]*/([^?]*).*~', '\\1?', $_SERVER["REQUEST_URI"]) . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (strlen($_GET["db"]) ? 'db=' . urlencode($_GET["db"]) . '&' : '');
$TOKENS = &$_SESSION["tokens"][$_GET["server"]][preg_replace('~([?&]sql=)upload~', '\\1', $_SERVER["REQUEST_URI"])];
include "./lang.inc.php";
include "./functions.inc.php";
include "./design.inc.php";
include "./auth.inc.php";
include "./connect.inc.php";

if (isset($_GET["dump"])) {
	include "./dump.inc.php";
} elseif (isset($_GET["download"])) {
	include "./download.inc.php";
} else {
	if (isset($_GET["table"])) {
		include "./table.inc.php";
	} elseif (isset($_GET["select"])) {
		include "./select.inc.php";
	} elseif (isset($_GET["view"])) {
		include "./view.inc.php";
	} else {
		if ($_POST) {
			$error = (in_array($_POST["token"], (array) $TOKENS) ? "" : lang('Invalid CSRF token. Send the form again.'));
		}
		$token = ($_POST && !$error ? $_POST["token"] : token());
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
		} else {
			$TOKENS = array();
			page_header(htmlspecialchars(lang('Database') . ": " . $_GET["db"]));
			echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Alter database') . "</a></p>\n";
			if (mysql_get_server_info() >= 5) {
				$result = mysql_query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '" . mysql_real_escape_string($_GET["db"]) . "'");
				if (mysql_num_rows($result)) {
					echo "<h2>" . lang('Routines') . "</h2>\n";
					echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
					while ($row = mysql_fetch_assoc($result)) {
						echo "<tr valign='top'>";
						echo "<th>" . htmlspecialchars($row["ROUTINE_TYPE"]) . "</th>";
						echo "<td>" . htmlspecialchars($row["ROUTINE_NAME"]) . "</td>"; //! parameters from SHOW CREATE {PROCEDURE|FUNCTION}
						echo "<td><pre>" . htmlspecialchars($row["ROUTINE_DEFINITION"]) . "</pre></td>";
						echo "</tr>\n";
					}
					echo "</table>\n";
				}
				mysql_free_result($result);
			}
		}
	}
	page_footer();
}
