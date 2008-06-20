<?php
function dump_table($table, $style) {
	global $mysql, $max_packet;
	$result = $mysql->query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		echo $mysql->result($result, 1) . ";\n\n";
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

function dump_data($table) {
	global $mysql, $max_packet;
	$result = $mysql->query("SELECT * FROM " . idf_escape($table)); //! enum and set as numbers, binary as _binary, microtime
	if ($result) {
		if ($result->num_rows) {
			$insert = "INSERT INTO " . idf_escape($table) . " VALUES ";
			$length = 0;
			while ($row = $result->fetch_row()) {
				foreach ($row as $key => $val) {
					$row[$key] = (isset($val) ? "'" . $mysql->escape_string($val) . "'" : "NULL");
				}
				$s = "(" . implode(", ", $row) . ")";
				if (!$length) {
					echo $insert, $s;
					$length = strlen($insert) + strlen($s);
				} else {
					$length += 2 + strlen($s);
					if ($length < $max_packet) {
						echo ", ", $s;
					} else {
						echo ";\n", $insert, $s;
						$length = strlen($insert) + strlen($s);
					}
				}
			}
			echo ";\n";
		}
		$result->free();
	}
	echo "\n";
}

function dump($db, $style) {
	global $mysql;
	static $routines;
	if (!isset($routines)) {
		$routines = array();
		if ($mysql->server_info >= 5) {
			foreach (array("FUNCTION", "PROCEDURE") as $routine) {
				$result = $mysql->query("SHOW $routine STATUS");
				while ($row = $result->fetch_assoc()) {
					if (!strlen($_GET["db"]) || $row["Db"] === $_GET["db"]) {
						$routines[$row["Db"]][] = $mysql->result($mysql->query("SHOW CREATE $routine " . idf_escape($row["Db"]) . "." . idf_escape($row["Name"])), 2) . ";;\n\n";
					}
				}
				$result->free();
			}
		}
	}
	if (in_array($style, array("DROP, CREATE", "CREATE", "CREATE, ALTER")) && ($result = $mysql->query("SHOW CREATE DATABASE " . idf_escape($db)))) {
		if ($style == "DROP, CREATE") {
			echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
		}
		$create = $mysql->result($result, 1);
		echo ($style == "CREATE, ALTER" ? preg_replace('~^CREATE DATABASE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n";
		$result->free();
	}
	if ($style) {
		echo "USE " . idf_escape($db) . ";\n";
	}
	foreach ($_POST["tables"] as $table => $val) {
		$table = bracket_escape($table, "back");
		if ($val) {
			dump_table($table, $val);
		}
		if ($_POST["data"][$table]) {
			dump_data($table, $_POST["data"][$table]);
		}
	}
	/*
	$views = array();
	$result = $mysql->query("SHOW TABLE STATUS");
	while ($row = $result->fetch_assoc()) {
		if (isset($row["Engine"])) {
			dump_table($row["Name"]);
			dump_data($row["Name"]);
		} else {
			$views[] = $row["Name"];
		}
	}
	$result->free();
	foreach ($views as $view) {
		dump_table($view);
	}
	*/
	if ($routines[$db]) {
		echo "DELIMITER ;;\n\n" . implode("", $routines[$db]) . "DELIMITER ;\n\n";
	}
	echo "\n\n";
}

if ($_POST) {
	header("Content-Type: text/plain; charset=utf-8");
	$filename = (strlen($_GET["db"]) ? preg_replace('~[^a-z0-9_]~i', '-', (strlen($_GET["dump"]) ? $_GET["dump"] : $_GET["db"])) : "dump");
	header("Content-Disposition: inline; filename=$filename.sql");
	
	$max_packet = 16777216;
	echo "SET NAMES utf8;\n";
	echo "SET foreign_key_checks = 0;\n";
	echo "SET time_zone = '" . $mysql->escape_string($mysql->result($mysql->query("SELECT @@time_zone"))) . "';\n";
	echo "SET max_allowed_packet = $max_packet, GLOBAL max_allowed_packet = $max_packet;\n";
	echo "\n";
	
	foreach ($_POST["databases"] as $db => $style) {
		$db = bracket_escape($db, "back");
		if ($mysql->select_db($db)) {
			dump($db, $style);
		}
	}
	/*
	} elseif (strlen($_GET["dump"])) {
		dump_table($_GET["dump"]);
	} else {
		dump($_GET["db"]);
	}
	*/
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
<p>
<?php echo lang('Output'); ?>: <select name="output"><option value="text"><?php echo lang('text'); ?></option><option value="file"><?php echo lang('file'); ?></option></select>
<?php echo lang('Format'); ?>: <select name="format"><option value="sql"><?php echo lang('SQL'); ?></option><option value="csv"><?php echo lang('CSV'); ?></option></select>
</p>

<?php
echo "<table border='1' cellspacing='0' cellpadding='2'>\n<thead><tr><th>" . lang('Database') . "</th>";
foreach (array('', 'USE', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
	echo "<th onclick=\"check(this, /^databases/, '$val');\" style='cursor: pointer;'>" . ($val ? $val : lang('skip')) . "</th>";
}
echo "</tr></thead>\n";
if (!isset($_GET["db"]) && !isset($_SESSION["databases"][$_GET["server"]])) {
	$_SESSION["databases"][$_GET["server"]] = get_vals("SHOW DATABASES");
}
foreach ((isset($_GET["db"]) ? array($_GET["db"]) : $_SESSION["databases"][$_GET["server"]]) as $db) {
	if ($db != "information_schema" || $mysql->server_info < 5) {
		echo "<tr><td>" . htmlspecialchars($db) . "</td>";
		foreach (array('', 'USE', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
			echo '<td><input type="radio" name="databases[' . htmlspecialchars(bracket_escape($db)) . ']"' . ($val == (isset($_GET["db"]) ? '' : 'CREATE') ? " checked='checked'" : "") . " value='$val' /></td>";
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
foreach ((isset($_GET["db"]) ? get_vals("SHOW TABLES") : $_SESSION["databases"][$_GET["server"]]) as $table) {
	echo "<tr><td>" . htmlspecialchars($table) . "</td>";
	foreach (array('', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER') as $val) {
		echo '<td><input type="radio" name="tables[' . htmlspecialchars(bracket_escape($table)) . ']"' . ($val == (strlen($_GET["dump"]) && $table != $_GET["dump"] ? '' : 'DROP, CREATE') ? " checked='checked'" : "") . " value='$val' /></td>";
	}
	foreach (array('', 'TRUNCATE, INSERT', 'INSERT', 'UPDATE') as $val) {
		echo '<td><input type="radio" name="data[' . htmlspecialchars(bracket_escape($table)) . ']"' . ($val == (strlen($_GET["dump"]) && $table != $_GET["dump"] ? '' : 'INSERT') ? " checked='checked'" : "") . " value='$val' /></td>";
	}
	echo "</tr>\n";
}
echo "</table>\n";
?>
<input type="submit" value="<?php echo lang('Export'); ?>" />
</form>
