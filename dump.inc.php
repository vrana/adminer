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

if ($_POST) {
	$ext = dump_headers((strlen($_GET["dump"]) ? $_GET["dump"] : $_GET["db"]), (!strlen($_GET["db"]) || count(array_filter((array) $_POST["tables"]) + array_filter((array) $_POST["data"])) > 1));
	if ($_POST["format"] != "csv") {
		$max_packet = 1048576; // default, minimum is 1024
		echo "SET NAMES utf8;\n";
		echo "SET foreign_key_checks = 0;\n";
		echo "SET time_zone = '" . $dbh->escape_string($dbh->result($dbh->query("SELECT @@time_zone"))) . "';\n";
		echo "\n";
	}
	
	foreach ($_POST["databases"] as $db => $style) {
		$db = bracket_escape($db, "back");
		if ($dbh->select_db($db)) {
			if ($_POST["format"] != "csv" && ereg('CREATE', $style) && ($result = $dbh->query("SHOW CREATE DATABASE " . idf_escape($db)))) {
				if ($style == "DROP, CREATE") {
					echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
				}
				$create = $dbh->result($result, 1);
				echo ($style == "CREATE, ALTER" ? preg_replace('~^CREATE DATABASE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n";
				$result->free();
			}
			if ($style && $_POST["format"] != "csv") {
				echo "USE " . idf_escape($db) . ";\n\n";
				$out = "";
				if ($dbh->server_info >= 5) {
					foreach (array("FUNCTION", "PROCEDURE") as $routine) {
						$result = $dbh->query("SHOW $routine STATUS WHERE Db = '" . $dbh->escape_string($db) . "'");
						while ($row = $result->fetch_assoc()) {
							$out .= $dbh->result($dbh->query("SHOW CREATE $routine " . idf_escape($row["Name"])), 2) . ";;\n\n";
						}
						$result->free();
					}
				}
				if ($dbh->server_info >= 5.1) {
					$result = $dbh->query("SHOW EVENTS");
					while ($row = $result->fetch_assoc()) {
						$out .= $dbh->result($dbh->query("SHOW CREATE EVENT " . idf_escape($row["Name"])), 3) . ";;\n\n";
					}
					$result->free();
				}
				echo ($out ? "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n" : "");
			}
			
			if (($style || strlen($_GET["db"])) && (array_filter((array) $_POST["tables"]) || array_filter((array) $_POST["data"]))) {
				$views = array();
				$result = $dbh->query("SHOW TABLE STATUS");
				while ($row = $result->fetch_assoc()) {
					$key = (strlen($_GET["db"]) ? bracket_escape($row["Name"]) : 0);
					if ($_POST["tables"][$key] || $_POST["data"][$key]) {
						if (isset($row["Engine"])) {
							if ($ext == "tar") {
								ob_start();
							}
							dump_table($row["Name"], $_POST["tables"][$key]);
							dump_data($row["Name"], $_POST["data"][$key]);
							if ($ext == "tar") {
								echo tar_file((strlen($_GET["db"]) ? "" : "$db/") . "$row[Name].csv", ob_get_clean());
							} elseif ($_POST["format"] != "csv") {
								echo "\n";
							}
						} elseif ($_POST["format"] != "csv") {
							$views[$row["Name"]] = $_POST["tables"][$key];
						}
					}
				}
				$result->free();
				foreach ($views as $view => $style1) {
					dump_table($view, $style1, true);
				}
			}
			
			if ($dbh->server_info >= 5 && $style == "CREATE, ALTER" && $_POST["format"] != "csv") {
				$query = "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()";
?>
DELIMITER ;;
CREATE PROCEDURE phpminadmin_drop () BEGIN
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
	$comment = $dbh->escape_string($row["ENGINE"] == "InnoDB" ? preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["TABLE_COMMENT"]) : $row["TABLE_COMMENT"]);
	echo "
				WHEN '" . $dbh->escape_string($row["TABLE_NAME"]) . "' THEN
					" . (isset($row["ENGINE"]) ? "IF _engine != '$row[ENGINE]' OR _table_collation != '$row[TABLE_COLLATION]' OR _table_comment != '$comment' THEN
						ALTER TABLE " . idf_escape($row["TABLE_NAME"]) . " ENGINE=$row[ENGINE] COLLATE=$row[TABLE_COLLATION] COMMENT='$comment';
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
CALL phpminadmin_drop;
DROP PROCEDURE phpminadmin_drop;
<?php
			}
		}
	}
	exit;
}

page_header(lang('Export'), "", (strlen($_GET["export"]) ? array("table" => $_GET["export"]) : array()), $_GET["db"]);
?>

<form action="" method="post">
<table border="0">
<?php
echo "<tr><th>" . lang('Output') . "</th><td>$dump_output</td></tr>\n";
echo "<tr><th>" . lang('Format') . "</th><td>$dump_format</td></tr>\n";
echo "<tr><th>" . lang('Database') . "</th><td><select name=''><option></option>" . optionlist(array('USE', 'DROP, CREATE', 'CREATE', 'CREATE, ALTER'), (strlen($_GET["db"]) ? '' : 'CREATE')) . "</select></td></tr>\n";
echo "<tr><th>" . lang('Tables') . "</th><td><select name=''><option></option>" . optionlist(array('DROP, CREATE', 'CREATE', 'CREATE, ALTER'), 'DROP, CREATE') . "</select></td></tr>\n";
echo "<tr><th>" . lang('Data') . "</th><td><select name=''><option></option>" . optionlist(array('TRUNCATE, INSERT', 'INSERT', 'UPDATE'), 'INSERT') . "</select></td></tr>\n"; // INSERT INTO ... ON DUPLICATE KEY UPDATE
?>
</table>
<p><input type="submit" value="<?php echo lang('Export'); ?>" /></p>

<?php
if (!strlen($_GET["db"])) {
	echo "<table cellspacing='0'>\n<thead><tr><th><input type='checkbox' id='check-databases' onclick='dump_check(this, /^databases\\[/);' checked='checked' />" . lang('Database') . "</th></tr></thead>\n";
	foreach (get_databases() as $db) {
		if ($db != "information_schema" || $dbh->server_info < 5) {
			echo '<tr><td><label><input type="checkbox" name="databases[' . htmlspecialchars(bracket_escape($db)) . ']" checked="checked" value="1" onclick="dump_uncheck(\'check-databases\');" />' . htmlspecialchars($db) . "</label></td></tr>\n"; //! uncheck all
		}
	}
	echo "</table>\n";
}

if (strlen($_GET["db"])) {
	$checked = (strlen($_GET["dump"]) ? "" : " checked='checked'");
	echo "<table cellspacing='0'>\n<thead><tr>";
	echo "<th style='text-align: left;'><label><input type='checkbox' id='check-tables' onclick='dump_check(this, /^tables\\[/);'$checked />" . lang('Tables') . "</label></th>";
	echo "<th><label><input type='checkbox' id='check-data' onclick='dump_check(this, /^data\\[/);'$checked />" . lang('Data') . "</label></th>";
	echo "</tr></thead>\n";
	$views = "";
	$result = $dbh->query("SHOW TABLE STATUS");
	while ($row = $result->fetch_assoc()) {
		$checked = (strlen($_GET["dump"]) && $row["Name"] != $_GET["dump"] ? '' : " checked='checked'");
		$print = '<tr><td><label><input type="checkbox" name="tables[' . htmlspecialchars(bracket_escape($row["Name"])) . "]\"$checked value='1' onclick=\"dump_uncheck('check-tables');\" />" . htmlspecialchars($row["Name"]) . "</label></td>"; //! uncheck all
		if (!$row["Engine"]) {
			$views .= "$print</tr>\n";
		} else {
			echo $print . '<td><input type="checkbox" name="data[' . htmlspecialchars(bracket_escape($row["Name"])) . "]\"$checked value='1' onclick=\"dump_uncheck('check-data');\" /></td></tr>\n";
		}
	}
	echo "$views</table>\n";
}
?>
<p><input type="submit" value="<?php echo lang('Export'); ?>" /></p>
</form>
