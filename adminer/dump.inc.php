<?php
function tar_file($filename, $contents) {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct(strlen($contents)), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return{$i});
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	return $return . str_repeat("\0", 512 - strlen($return)) . $contents . str_repeat("\0", 511 - (strlen($contents) + 511) % 512);
}

function dump_triggers($table, $style) {
	global $dbh;
	if ($_POST["format"] != "csv" && $style && $dbh->server_info >= 5) {
		$result = $dbh->query("SHOW TRIGGERS LIKE " . $dbh->quote(addcslashes($table, "%_")));
		if ($result->num_rows) {
			echo "\nDELIMITER ;;\n";
			while ($row = $result->fetch_assoc()) {
				echo "\n" . ($style == 'CREATE+ALTER' ? "DROP TRIGGER IF EXISTS " . idf_escape($row["Trigger"]) . ";;\n" : "")
				. "CREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . idf_escape($row["Table"]) . " FOR EACH ROW\n$row[Statement];;\n";
			}
			echo "\nDELIMITER ;\n";
		}
		$result->free();
	}
}

if ($_POST) {
	$ext = dump_headers((strlen($_GET["dump"]) ? $_GET["dump"] : $_GET["db"]), (!strlen($_GET["db"]) || count((array) $_POST["tables"] + (array) $_POST["data"]) > 1));
	if ($_POST["format"] != "csv") {
		echo "SET NAMES utf8;\n";
		echo "SET foreign_key_checks = 0;\n";
		echo "SET time_zone = " . $dbh->quote($dbh->result($dbh->query("SELECT @@time_zone"))) . ";\n";
		echo "\n";
	}
	
	$style = $_POST["db_style"];
	foreach ((strlen($_GET["db"]) ? array($_GET["db"]) : (array) $_POST["databases"]) as $db) {
		if ($dbh->select_db($db)) {
			if ($_POST["format"] != "csv" && ereg('CREATE', $style) && ($result = $dbh->query("SHOW CREATE DATABASE " . idf_escape($db)))) {
				if ($style == "DROP+CREATE") {
					echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
				}
				$create = $dbh->result($result, 1);
				echo ($style == "CREATE+ALTER" ? preg_replace('~^CREATE DATABASE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n";
				$result->free();
			}
			if ($style && $_POST["format"] != "csv") {
				echo "USE " . idf_escape($db) . ";\n\n";
				$out = "";
				if ($dbh->server_info >= 5) {
					foreach (array("FUNCTION", "PROCEDURE") as $routine) {
						$result = $dbh->query("SHOW $routine STATUS WHERE Db = " . $dbh->quote($db));
						while ($row = $result->fetch_assoc()) {
							$out .= ($style != 'DROP+CREATE' ? "DROP $routine IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "")
							. $dbh->result($dbh->query("SHOW CREATE $routine " . idf_escape($row["Name"])), 2) . ";;\n\n";
						}
						$result->free();
					}
				}
				if ($dbh->server_info >= 5.1) {
					$result = $dbh->query("SHOW EVENTS");
					while ($row = $result->fetch_assoc()) {
						$out .= ($style != 'DROP+CREATE' ? "DROP EVENT IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "")
						. $dbh->result($dbh->query("SHOW CREATE EVENT " . idf_escape($row["Name"])), 3) . ";;\n\n";
					}
					$result->free();
				}
				echo ($out ? "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n" : "");
			}
			
			if ($_POST["table_style"] || $_POST["data_style"]) {
				$views = array();
				$result = $dbh->query("SHOW TABLE STATUS");
				while ($row = $result->fetch_assoc()) {
					$table = (!strlen($_GET["db"]) || in_array($row["Name"], (array) $_POST["tables"]));
					$data = (!strlen($_GET["db"]) || in_array($row["Name"], (array) $_POST["data"]));
					if ($table || $data) {
						if (isset($row["Engine"])) {
							if ($ext == "tar") {
								ob_start();
							}
							dump_table($row["Name"], ($table ? $_POST["table_style"] : ""));
							if ($data) {
								dump_data($row["Name"], $_POST["data_style"]);
							}
							if ($table) {
								dump_triggers($row["Name"], $_POST["table_style"]);
							}
							if ($ext == "tar") {
								echo tar_file((strlen($_GET["db"]) ? "" : "$db/") . "$row[Name].csv", ob_get_clean());
							} elseif ($_POST["format"] != "csv") {
								echo "\n";
							}
						} elseif ($_POST["format"] != "csv") {
							$views[] = $row["Name"];
						}
					}
				}
				$result->free();
				foreach ($views as $view) {
					dump_table($view, $_POST["table_style"], true);
				}
			}
			
			if ($style == "CREATE+ALTER" && $_POST["format"] != "csv") {
				// drop old tables
				$query = "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()";
?>
DELIMITER ;;
CREATE PROCEDURE adminer_drop () BEGIN
	DECLARE _table_name, _engine, _table_collation varchar(64);
	DECLARE _table_comment varchar(64);
	DECLARE done bool DEFAULT 0;
	DECLARE tables CURSOR FOR <?php echo $query; ?>;
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
	OPEN tables;
	REPEAT
		FETCH tables INTO _table_name, _engine, _table_collation, _table_comment;
		IF NOT done THEN
			CASE _table_name<?php
$result = $dbh->query($query);
while ($row = $result->fetch_assoc()) {
	$comment = $dbh->quote($row["ENGINE"] == "InnoDB" ? preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["TABLE_COMMENT"]) : $row["TABLE_COMMENT"]);
	echo "
				WHEN " . $dbh->quote($row["TABLE_NAME"]) . " THEN
					" . (isset($row["ENGINE"]) ? "IF _engine != '$row[ENGINE]' OR _table_collation != '$row[TABLE_COLLATION]' OR _table_comment != $comment THEN
						ALTER TABLE " . idf_escape($row["TABLE_NAME"]) . " ENGINE=$row[ENGINE] COLLATE=$row[TABLE_COLLATION] COMMENT=$comment;
					END IF" : "BEGIN END") . ";";
}
$result->free();
?>

				ELSE
					SET @alter_table = CONCAT('DROP TABLE `', REPLACE(_table_name, '`', '``'), '`');
					PREPARE alter_command FROM @alter_table;
					EXECUTE alter_command; -- returns "can't return a result set in the given context" with MySQL extension
					DROP PREPARE alter_command;
			END CASE;
		END IF;
	UNTIL done END REPEAT;
	CLOSE tables;
END;;
DELIMITER ;
CALL adminer_drop;
DROP PROCEDURE adminer_drop;
<?php
			}
		}
	}
	exit;
}

page_header(lang('Export'), "", (strlen($_GET["export"]) ? array("table" => $_GET["export"]) : array()), $_GET["db"]);
?>

<form action="" method="post">
<table cellspacing="0">
<?php
$db_style = array('USE', 'DROP+CREATE', 'CREATE');
$table_style = array('DROP+CREATE', 'CREATE');
$data_style = array('TRUNCATE+INSERT', 'INSERT', 'INSERT+UPDATE');
if ($dbh->server_info >= 5) {
	$db_style[] = 'CREATE+ALTER';
	$table_style[] = 'CREATE+ALTER';
}
echo "<tr><th>" . lang('Output') . "</th><td>$dump_output</td></tr>\n";
echo "<tr><th>" . lang('Format') . "</th><td>$dump_format</td></tr>\n";
echo "<tr><th>" . lang('Database') . "</th><td><select name='db_style'><option></option>" . optionlist($db_style, (strlen($_GET["db"]) ? '' : 'CREATE')) . "</select></td></tr>\n";
echo "<tr><th>" . lang('Tables') . "</th><td><select name='table_style'><option></option>" . optionlist($table_style, 'DROP+CREATE') . "</select></td></tr>\n";
echo "<tr><th>" . lang('Data') . "</th><td><select name='data_style'><option></option>" . optionlist($data_style, 'INSERT') . "</select></td></tr>\n";
?>
</table>
<p><input type="submit" value="<?php echo lang('Export'); ?>" /></p>

<table cellspacing="0">
<?php
if (strlen($_GET["db"])) {
	$checked = (strlen($_GET["dump"]) ? "" : " checked='checked'");
	echo "<thead><tr>";
	echo "<th align='left'><label><input type='checkbox' id='check-tables'$checked onclick='form_check(this, /^tables\\[/);' />" . lang('Tables') . "</label></th>";
	echo "<th align='right'><label>" . lang('Data') . "<input type='checkbox' id='check-data'$checked onclick='form_check(this, /^data\\[/);' /></label></th>";
	echo "</tr></thead>\n";
	$views = "";
	$result = $dbh->query("SHOW TABLE STATUS");
	while ($row = $result->fetch_assoc()) {
		$checked = (strlen($_GET["dump"]) && $row["Name"] != $_GET["dump"] ? '' : " checked='checked'");
		$print = '<tr><td><label><input type="checkbox" name="tables[]" value="' . htmlspecialchars($row["Name"]) . "\"$checked onclick=\"form_uncheck('check-tables');\" />" . htmlspecialchars($row["Name"]) . "</label></td>";
		if (!$row["Engine"]) {
			$views .= "$print</tr>\n";
		} else {
			echo "$print<td align='right'><label>" . ($row["Engine"] == "InnoDB" && $row["Rows"] ? lang('~ %s', $row["Rows"]) : $row["Rows"]) . '<input type="checkbox" name="data[]" value="' . htmlspecialchars($row["Name"]) . "\"$checked onclick=\"form_uncheck('check-data');\" /></label></td></tr>\n";
		}
	}
	echo $views;
} else {
	echo "<thead><tr><th align='left'><label><input type='checkbox' id='check-databases' checked='checked' onclick='form_check(this, /^databases\\[/);' />" . lang('Database') . "</label></th></tr></thead>\n";
	foreach (get_databases() as $db) {
		if ($db != "information_schema" || $dbh->server_info < 5) {
			echo '<tr><td><label><input type="checkbox" name="databases[]" value="' . htmlspecialchars($db) . '" checked="checked" onclick="form_uncheck(\'check-databases\');" />' . htmlspecialchars($db) . "</label></td></tr>\n";
		}
	}
}
?>
</table>
</form>
