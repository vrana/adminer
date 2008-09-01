<?php
/** phpMinAdmin - Compact MySQL management
* @link http://phpminadmin.sourceforge.net
* @author Jakub Vrana, http://php.vrana.cz
* @copyright 2007 Jakub Vrana
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
*/

error_reporting(E_ALL & ~E_NOTICE);
if (!ini_get("session.auto_start")) {
	session_name("phpMinAdmin_SID");
	session_set_cookie_params(ini_get("session.cookie_lifetime"), preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]));
	session_start();
}
if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST);
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
$SELF = preg_replace('~^[^?]*/([^?]*).*~', '\\1?', $_SERVER["REQUEST_URI"]) . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (strlen($_GET["db"]) ? 'db=' . urlencode($_GET["db"]) . '&' : '');
$TOKENS = &$_SESSION["tokens"][$_GET["server"]][$_SERVER["REQUEST_URI"]];

include "./functions.inc.php";
include "./lang.inc.php";
include "./lang/$LANG.inc.php";
include "./design.inc.php";
include "./abstraction.inc.php";
include "./auth.inc.php";
include "./connect.inc.php";
include "./editing.inc.php";
include "./export.inc.php";

if (isset($_GET["download"])) {
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
	$inout = array("IN", "OUT", "INOUT");
	$functions = array("char_length", "from_unixtime", "hex", "lower", "round", "sec_to_time", "time_to_sec", "unix_timestamp", "upper");
	$grouping = array("avg", "count", "distinct", "group_concat", "max", "min", "sum");
	
	$error = "";
	if (isset($_GET["table"])) {
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
		if ($_POST) {
			if (!in_array($_POST["token"], (array) $TOKENS)) {
				$error = lang('Invalid CSRF token. Send the form again.');
			}
		} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
			$error = lang('Too big POST data. Reduce the data or increase the "post_max_size" configuration directive.');
		}
		$token = ($_POST && !$error ? $_POST["token"] : token());
		if (isset($_GET["default"])) {
			$_GET["edit"] = $_GET["default"];
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
		} else {
			unset($_SESSION["tokens"][$_GET["server"]][$_SERVER["REQUEST_URI"]]);
			page_header(lang('Database') . ": " . htmlspecialchars($_GET["db"]), $error, false);
			echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Alter database') . "</a></p>\n";
			echo '<p><a href="' . htmlspecialchars($SELF) . 'schema=">' . lang('Database schema') . "</a></p>\n";
			if ($mysql->server_info >= 5) {
				echo '<p><a href="' . htmlspecialchars($SELF) . 'createv=">' . lang('Create view') . "</a></p>\n";
				echo "<h3>" . lang('Routines') . "</h3>\n";
				$result = $mysql->query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '" . $mysql->escape_string($_GET["db"]) . "'");
				if ($result->num_rows) {
					echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
					while ($row = $result->fetch_assoc()) {
						echo "<tr>";
						echo "<td>" . htmlspecialchars($row["ROUTINE_TYPE"]) . "</td>";
						echo '<th><a href="' . htmlspecialchars($SELF) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'callf' : 'call') . '=' . urlencode($row["ROUTINE_NAME"]) . '">' . htmlspecialchars($row["ROUTINE_NAME"]) . '</a></th>';
						echo '<td><a href="' . htmlspecialchars($SELF) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'function' : 'procedure') . '=' . urlencode($row["ROUTINE_NAME"]) . '">' . lang('Alter') . "</a></td>";
						echo "</tr>\n";
					}
					echo "</table>\n";
				}
				$result->free();
				echo '<p><a href="' . htmlspecialchars($SELF) . 'procedure=">' . lang('Create procedure') . '</a> <a href="' . htmlspecialchars($SELF) . 'function=">' . lang('Create function') . "</a></p>\n";
			}
			if ($mysql->server_info >= 5.1) {
				echo "<h3>" . lang('Events') . "</h3>\n";
				$result = $mysql->query("SHOW EVENTS");
				if ($result->num_rows) {
					echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
					echo "<thead><tr><th>" . lang('Name') . "</th><td>" . lang('Schedule') . "</td><td>" . lang('Start') . "</td><td>" . lang('End') . "</td></tr></thead>\n";
					while ($row = $result->fetch_assoc()) {
						echo "<tr>";
						echo '<th><a href="' . htmlspecialchars($SELF) . 'event=' . urlencode($row["Name"]) . '">' . htmlspecialchars($row["Name"]) . "</a></th>";
						echo "<td>" . ($row["Execute at"] ? lang('At given time') . "</td><td>" . $row["Execute at"] : lang('Every') . " " . $row["Interval value"] . " " . $row["Interval field"] . "</td><td>$row[Starts]") . "</td>";
						echo "<td>$row[Ends]</td>";
						echo "</tr>\n";
					}
					echo "</table>\n";
				}
				$result->free();
				echo '<p><a href="' . htmlspecialchars($SELF) . 'event=">' . lang('Create event') . "</a></p>\n";
			}
		}
	}
	page_footer();
}
