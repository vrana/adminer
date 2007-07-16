<?php
// Copyright 2007 Jakub Vrana http://phpminadmin.sourceforge.net, licensed under the Apache License, Version 2.0 (the 'License'); you may not use this file except in compliance with the License.

session_start();
error_reporting(E_ALL & ~E_NOTICE);
$SELF = preg_replace('~^[^?]*/([^?]*).*~', '\\1?', $_SERVER["REQUEST_URI"]) . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (strlen($_GET["db"]) ? 'db=' . urlencode($_GET["db"]) . '&' : '');
$TOKENS = &$_SESSION["tokens"][$_GET["server"]][$_SERVER["REQUEST_URI"]];
include "./functions.inc.php";
include "./lang.inc.php";
include "./design.inc.php";
include "./abstraction.inc.php";
include "./auth.inc.php";
include "./connect.inc.php";

if (isset($_GET["dump"])) {
	include "./dump.inc.php";
} elseif (isset($_GET["download"])) {
	include "./download.inc.php";
} else { // outputs footer
	$on_actions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION");
	$types = array(
		"tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20,
		"float" => 12, "double" => 21, "decimal" => 66,
		"date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4,
		"char" => 255, "varchar" => 65535,
		"binary" => 255, "varbinary" => 65535,
		"tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295,
		"tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295,
		"enum" => 65535, "set" => 64,
	);
	$unsigned = array("", "unsigned", "zerofill", "unsigned zerofill");
	$enum_length = '\'(?:\'\'|[^\'\\\\]+|\\\\.)*\'|"(?:""|[^"\\\\]+|\\\\.)*"';
	
	if (isset($_GET["table"])) {
		include "./table.inc.php";
	} elseif (isset($_GET["select"])) {
		include "./select.inc.php";
	} elseif (isset($_GET["view"])) {
		include "./view.inc.php";
	} else { // uses CSRF token
		if ($_POST) {
			$error = (in_array($_POST["token"], (array) $TOKENS) ? "" : lang('Invalid CSRF token. Send the form again.'));
		}
		$token = ($_POST && !$error ? $_POST["token"] : token());
		if (isset($_GET["default"])) {
			$_GET["edit"] = $_GET["default"];
		}
		if (isset($_GET["callf"])) {
			$_GET["call"] = $_GET["callf"];
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
		} elseif (isset($_GET["processlist"])) {
			include "./processlist.inc.php";
		} else {
			$TOKENS = array();
			page_header(htmlspecialchars(lang('Database') . ": " . $_GET["db"]));
			echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Alter database') . "</a></p>\n";
			if ($mysql->server_info >= 5) {
				$result = $mysql->query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '" . $mysql->escape_string($_GET["db"]) . "'");
				if ($result->num_rows) {
					echo "<h2>" . lang('Routines') . "</h2>\n";
					echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
					while ($row = $result->fetch_assoc()) {
						echo "<tr valign='top'>";
						echo "<th>" . htmlspecialchars($row["ROUTINE_TYPE"]) . "</th>";
						echo '<td><a href="' . htmlspecialchars($SELF) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'callf' : 'call') . '=' . urlencode($row["ROUTINE_NAME"]) . '">' . htmlspecialchars($row["ROUTINE_NAME"]) . '</a></td>';
						echo "<td><pre class='jush-sql'>" . htmlspecialchars($row["ROUTINE_DEFINITION"]) . "</pre></td>";
						echo "</tr>\n";
					}
					echo "</table>\n";
				}
				$result->free();
				echo '<p><a href="' . htmlspecialchars($SELF) . 'createv=">' . lang('Create view') . "</a></p>\n";
			}
		}
	}
	page_footer();
}
