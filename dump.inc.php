<?php
include "./export.inc.php";

function dump_table($table, $style) {
	global $mysql, $max_packet, $types;
	if ($style) {
		if ($_POST["format"] == "csv") {
			dump_csv(array_keys(fields($table)));
		} else {
			$result = $mysql->query("SHOW CREATE TABLE " . idf_escape($table));
			if ($result) {
				if ($style == "DROP, CREATE") {
					echo "DROP TABLE " . idf_escape($table) . ";\n";
				}
				$create = $mysql->result($result, 1);
				echo ($style == "CREATE, ALTER" ? preg_replace('~^CREATE TABLE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n\n";
				if ($max_packet < 1073741824) { // protocol limit
					$row_size = 21 + strlen(idf_escape($table));
					foreach (fields($table) as $field) {
						$type = $types[$field["type"]];
						$row_size += 5 + ($field["length"] ? $field["length"] : $type) * (preg_match('~char|text|enum~', $field["type"]) ? 3 : 1); // UTF-8 in MySQL uses up to 3 bytes
					}
					if ($row_size > $max_packet) {
						$max_packet = 1024 * ceil($row_size / 1024);
						echo "SET max_allowed_packet = $max_packet, GLOBAL max_allowed_packet = $max_packet;\n";
					}
				}
				$result->free();
			}
			if ($mysql->server_info >= 5) {
				$result = $mysql->query("SHOW TRIGGERS LIKE '" . $mysql->escape_string(addcslashes($table, "%_")) . "'");
				if ($result->num_rows) {
					echo "DELIMITER ;;\n\n";
					while ($row = $result->fetch_assoc()) {
						echo "CREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . idf_escape($row["Table"]) . " FOR EACH ROW $row[Statement];;\n\n";
					}
					echo "DELIMITER ;\n\n";
				}
				$result->free();
			}
		}
	}
}

function dump_routines($db) {
	global $mysql;
	if ($mysql->server_info >= 5) {
		$out = "";
		foreach (array("FUNCTION", "PROCEDURE") as $routine) {
			$result = $mysql->query("SHOW $routine STATUS WHERE Db = '" . $mysql->escape_string($db) . "'");
			while ($row = $result->fetch_assoc()) {
				if (!$out) {
					echo "DELIMITER ;;\n\n";
					$out = "DELIMITER ;\n\n";
				}
				echo $mysql->result($mysql->query("SHOW CREATE $routine " . idf_escape($row["Db"]) . "." . idf_escape($row["Name"])), 2) . ";;\n\n";
			}
			$result->free();
		}
		echo $out;
	}
}

function dump($db, $style) {
	global $mysql;
	if ($_POST["format"] != "csv" && in_array($style, array("DROP, CREATE", "CREATE", "CREATE, ALTER")) && ($result = $mysql->query("SHOW CREATE DATABASE " . idf_escape($db)))) {
		if ($style == "DROP, CREATE") {
			echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
		}
		$create = $mysql->result($result, 1);
		echo ($style == "CREATE, ALTER" ? preg_replace('~^CREATE DATABASE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n";
		$result->free();
	}
	if ($style) {
		echo ($_POST["format"] != "csv" ? "USE " . idf_escape($db) . ";\n" : "");
		if (!strlen($_GET["db"])) {
			$views = array();
			$result = $mysql->query("SHOW TABLE STATUS");
			while ($row = $result->fetch_assoc()) {
				if (isset($row["Engine"])) {
					if ($_POST["format"] == "csv") {
						ob_start();
					}
					dump_table($row["Name"], $_POST["tables"][0]);
					dump_data($row["Name"], $_POST["data"][0]);
					if ($_POST["format"] == "csv") {
						echo tar_file("$db/$row[Name].csv", ob_get_clean());
					}
				} else {
					$views[] = $row["Name"];
				}
			}
			$result->free();
			if ($_POST["format"] != "csv") {
				foreach ($views as $view) {
					dump_table($view, $_POST["tables"][0]);
				}
				dump_routines($db);
			}
		}
	}
}

function tar_file($filename, $contents) {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct(strlen($contents)), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return{$i});
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	return $return . str_repeat("\0", 512 - strlen($return)) . $contents . str_repeat("\0", 511 - (strlen($contents) + 511) % 512);
}

if ($_POST) {
	$filename = (strlen($_GET["db"]) ? preg_replace('~[^a-z0-9_]~i', '-', (strlen($_GET["dump"]) ? $_GET["dump"] : $_GET["db"])) : "dump");
	$ext = ($_POST["format"] == "sql" ? "sql" : (!strlen($_GET["db"]) || count(array_filter($_POST["tables"]) + array_filter($_POST["data"])) > 1 ? "tar" : "csv"));
	header("Content-Type: " . ($ext == "tar" ? "application/x-tar" : ($ext == "sql" || $_POST["output"] != "file" ? "text/plain" : "text/csv")) . "; charset=utf-8");
	header("Content-Disposition: " . ($_POST["output"] == "file" ? "attachment" : "inline") . "; filename=$filename.$ext");
	if ($_POST["format"] != "csv") {
		$max_packet = 16777216;
		echo "SET NAMES utf8;\n";
		echo "SET foreign_key_checks = 0;\n";
		echo "SET time_zone = '" . $mysql->escape_string($mysql->result($mysql->query("SELECT @@time_zone"))) . "';\n";
		echo "SET max_allowed_packet = $max_packet, GLOBAL max_allowed_packet = $max_packet;\n";
		echo "\n";
	}
	
	foreach ($_POST["databases"] as $db => $style) {
		$db = bracket_escape($db, "back");
		if ($mysql->select_db($db)) {
			dump($db, $style);
		}
	}
	if (strlen($_GET["db"])) {
		foreach ($_POST["tables"] as $key => $style) {
			$table = bracket_escape($key, "back");
			if ($ext == "tar") {
				ob_start();
			}
			dump_table($table, $style);
			dump_data($table, $_POST["data"][$key]);
			if ($ext == "tar") {
				echo tar_file("$table.csv", ob_get_clean());
			}
		}
		dump_routines($_GET["db"]);
	}
	exit;
}

page_header(lang('Export'), "", (strlen($_GET["export"]) ? array("table" => $_GET["export"]) : array()), $_GET["db"]);
?>

<script type="text/javascript">
function check(td, name, value) {
	var inputs = td.parentNode.parentNode.parentNode.getElementsByTagName('input');
	for (var i=0; i < inputs.length; i++) {
		if (name.test(inputs[i].name)) {
			inputs[i].checked = (inputs[i].value == value);
		}
	}
}
</script>

<form action="" method="post">
<p><?php echo lang('Output') . ": <select name='output'><option value='text'>" . lang('open') . "</option><option value='file'>" . lang('save') . "</option></select> " . $dump_options; ?></p>

<?php
echo "<table border='1' cellspacing='0' cellpadding='2'>\n<thead><tr><th>" . lang('Database') . "</th>";
foreach (array('', 'USE', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
	echo "<th onclick=\"check(this, /^databases/, '$val');\" style='cursor: pointer;'>" . ($val ? $val : lang('skip')) . "</th>";
}
echo "</tr></thead>\n";
if (!strlen($_GET["db"]) && !isset($_SESSION["databases"][$_GET["server"]])) {
	$_SESSION["databases"][$_GET["server"]] = get_vals("SHOW DATABASES");
}
foreach ((strlen($_GET["db"]) ? array($_GET["db"]) : $_SESSION["databases"][$_GET["server"]]) as $db) {
	if ($db != "information_schema" || $mysql->server_info < 5) {
		echo "<tr><td>" . htmlspecialchars($db) . "</td>";
		foreach (array('', 'USE', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
			echo '<td><input type="radio" name="databases[' . htmlspecialchars(bracket_escape($db)) . ']"' . ($val == (strlen($_GET["db"]) ? '' : 'CREATE') ? " checked='checked'" : "") . " value='$val' /></td>";
		}
		echo "</tr>\n";
	}
}
echo "</table>\n";

echo "<table border='1' cellspacing='0' cellpadding='2'>\n<thead><tr><th rowspan='2'>" . lang('Tables') . "</th><th colspan='4'>" . lang('Structure') . "</th><th colspan='4'>" . lang('Data') . "</th></tr><tr>";
foreach (array('', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
	echo "<th onclick=\"check(this, /^tables/, '$val');\" style='cursor: pointer;'>" . ($val ? $val : lang('skip')) . "</th>";
}
foreach (array('', 'TRUNCATE, INSERT', 'INSERT', 'UPDATE') as $val) {
	echo "<th onclick=\"check(this, /^data/, '$val');\" style='cursor: pointer;'>" . ($val ? $val : lang('skip')) . "</th>";
}
echo "</tr></thead>\n";
$views = "";
$result = $mysql->query(strlen($_GET["db"]) ? "SHOW TABLE STATUS" : "SELECT 'Engine'");
while ($row = $result->fetch_assoc()) {
	$print = "<tr><td>" . htmlspecialchars($row["Name"]) . "</td>";
	foreach (array('', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
		$print .= '<td><input type="radio" name="tables[' . htmlspecialchars(bracket_escape($row["Name"])) . ']"' . ($val == (strlen($_GET["dump"]) && $row["Name"] != $_GET["dump"] ? '' : 'DROP, CREATE') ? " checked='checked'" : "") . " value='$val' /></td>";
	}
	if (!$row["Engine"]) {
		$views .= "$print</tr>\n";
	} else {
		foreach (array('', 'TRUNCATE, INSERT', 'INSERT', 'UPDATE') as $val) {
			$print .= '<td><input type="radio" name="data[' . htmlspecialchars(bracket_escape($row["Name"])) . ']"' . ($val == ((strlen($_GET["dump"]) && $row["Name"] != $_GET["dump"]) || !$row["Engine"] ? '' : 'INSERT') ? " checked='checked'" : "") . " value='$val' /></td>";
		}
		echo "$print</tr>\n";
	}
}
echo "$views</table>\n";
?>
<input type="submit" value="<?php echo lang('Export'); ?>" />
</form>
