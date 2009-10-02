<?php
$TABLE = $_GET["dump"];

if ($_POST) {
	$ext = dump_headers((strlen($TABLE) ? $TABLE : DB), (!strlen(DB) || count((array) $_POST["tables"] + (array) $_POST["data"]) > 1));
	if ($_POST["format"] == "sql") {
		echo "-- Adminer $VERSION dump
SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = " . $connection->quote($connection->result($connection->query("SELECT @@time_zone"))) . ";
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

";
	}
	
	$style = $_POST["db_style"];
	foreach ((strlen(DB) ? array(DB) : (array) $_POST["databases"]) as $db) {
		if ($connection->select_db($db)) {
			if ($_POST["format"] == "sql" && ereg('CREATE', $style) && ($result = $connection->query("SHOW CREATE DATABASE " . idf_escape($db)))) {
				if ($style == "DROP+CREATE") {
					echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
				}
				$create = $connection->result($result, 1);
				echo ($style == "CREATE+ALTER" ? preg_replace('~^CREATE DATABASE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n";
			}
			if ($_POST["format"] == "sql") {
				if ($style) {
					echo "USE " . idf_escape($db) . ";\n" . ($style == "CREATE+ALTER" ? "SET @adminer_alter = '';\n" : "") . "\n";
				}
				$out = "";
				if ($_POST["routines"]) {
					foreach (array("FUNCTION", "PROCEDURE") as $routine) {
						$result = $connection->query("SHOW $routine STATUS WHERE Db = " . $connection->quote($db));
						while ($row = $result->fetch_assoc()) {
							$out .= ($style != 'DROP+CREATE' ? "DROP $routine IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "")
							. $connection->result($connection->query("SHOW CREATE $routine " . idf_escape($row["Name"])), 2) . ";;\n\n";
						}
					}
				}
				if ($_POST["events"]) {
					$result = $connection->query("SHOW EVENTS");
					while ($row = $result->fetch_assoc()) {
						$out .= ($style != 'DROP+CREATE' ? "DROP EVENT IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "")
						. $connection->result($connection->query("SHOW CREATE EVENT " . idf_escape($row["Name"])), 3) . ";;\n\n";
					}
				}
				if ($out) {
					echo "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n";
				}
			}
			
			if ($_POST["table_style"] || $_POST["data_style"]) {
				$views = array();
				foreach (table_status() as $row) {
					$table = (!strlen(DB) || in_array($row["Name"], (array) $_POST["tables"]));
					$data = (!strlen(DB) || in_array($row["Name"], (array) $_POST["data"]));
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
								echo tar_file((strlen(DB) ? "" : "$db/") . "$row[Name].csv", ob_get_clean());
							} elseif ($_POST["format"] == "sql") {
								echo "\n";
							}
						} elseif ($_POST["format"] == "sql") {
							$views[] = $row["Name"];
						}
					}
				}
				foreach ($views as $view) {
					dump_table($view, $_POST["table_style"], true);
				}
				if ($ext == "tar") {
					echo pack("x512");
				}
			}
			
			if ($style == "CREATE+ALTER" && $_POST["format"] == "sql") {
				// drop old tables
				$query = "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()";
				echo "DELIMITER ;;
CREATE PROCEDURE adminer_alter (INOUT alter_command text) BEGIN
	DECLARE _table_name, _engine, _table_collation varchar(64);
	DECLARE _table_comment varchar(64);
	DECLARE done bool DEFAULT 0;
	DECLARE tables CURSOR FOR $query;
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
	OPEN tables;
	REPEAT
		FETCH tables INTO _table_name, _engine, _table_collation, _table_comment;
		IF NOT done THEN
			CASE _table_name";
				$result = $connection->query($query);
				while ($row = $result->fetch_assoc()) {
					$comment = $connection->quote($row["ENGINE"] == "InnoDB" ? preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["TABLE_COMMENT"]) : $row["TABLE_COMMENT"]);
					echo "
				WHEN " . $connection->quote($row["TABLE_NAME"]) . " THEN
					" . (isset($row["ENGINE"]) ? "IF _engine != '$row[ENGINE]' OR _table_collation != '$row[TABLE_COLLATION]' OR _table_comment != $comment THEN
						ALTER TABLE " . idf_escape($row["TABLE_NAME"]) . " ENGINE=$row[ENGINE] COLLATE=$row[TABLE_COLLATION] COMMENT=$comment;
					END IF" : "BEGIN END") . ";";
				}
				echo "
				ELSE
					SET alter_command = CONCAT(alter_command, 'DROP TABLE `', REPLACE(_table_name, '`', '``'), '`;\\n');
			END CASE;
		END IF;
	UNTIL done END REPEAT;
	CLOSE tables;
END;;
DELIMITER ;
CALL adminer_alter(@adminer_alter);
DROP PROCEDURE adminer_alter;
";
			}
			if (in_array("CREATE+ALTER", array($style, $_POST["table_style"])) && $_POST["format"] == "sql") {
				echo "SELECT @adminer_alter;\n";
			}
		}
	}
	exit;
}

page_header(lang('Export'), "", (strlen($_GET["export"]) ? array("table" => $_GET["export"]) : array()), DB);
?>

<form action="" method="post">
<table cellspacing="0">
<?php
$db_style = array('', 'USE', 'DROP+CREATE', 'CREATE');
$table_style = array('', 'DROP+CREATE', 'CREATE');
$data_style = array('', 'TRUNCATE+INSERT', 'INSERT', 'INSERT+UPDATE');
if ($connection->server_info >= 5) {
	$db_style[] = 'CREATE+ALTER';
	$table_style[] = 'CREATE+ALTER';
}
echo "<tr><th>" . lang('Output') . "<td><input type='hidden' name='token' value='$token'>" . $adminer->dumpOutput(0) . "\n"; // token is not needed but checked in bootstrap for all POST data
echo "<tr><th>" . lang('Format') . "<td>" . $adminer->dumpFormat(0) . "\n";
echo "<tr><th>" . lang('Database') . "<td><select name='db_style'>" . optionlist($db_style, (strlen(DB) ? '' : 'CREATE')) . "</select>\n";
if ($connection->server_info >= 5) {
	$checked = strlen($_GET["dump"]);
	checkbox("routines", 1, $checked, lang('Routines'));
	if ($connection->server_info >= 5.1) {
		checkbox("events", 1, $checked, lang('Events'));
	}
}
echo "<tr><th>" . lang('Tables') . "<td><select name='table_style'>" . optionlist($table_style, 'DROP+CREATE') . "</select>\n";
echo "<tr><th>" . lang('Data') . "<td><select name='data_style'>" . optionlist($data_style, 'INSERT') . "</select>\n";
?>
</table>
<p><input type="submit" value="<?php echo lang('Export'); ?>"></p>

<table cellspacing="0">
<?php
if (strlen(DB)) {
	$checked = (strlen($TABLE) ? "" : " checked");
	echo "<thead><tr>";
	echo "<th style='text-align: left;'><label><input type='checkbox' id='check-tables'$checked onclick='form_check(this, /^tables\\[/);'>" . lang('Tables') . "</label>";
	echo "<th style='text-align: right;'><label>" . lang('Data') . "<input type='checkbox' id='check-data'$checked onclick='form_check(this, /^data\\[/);'></label>";
	echo "</thead>\n";
	$views = "";
	foreach (table_status() as $row) {
		$checked = !strlen($TABLE) || $row["Name"] == $TABLE;
		$print = "<tr><td>" . checkbox("tables[]", $row["Name"], $checked, $row["Name"], "form_uncheck('check-tables');");
		if (!$row["Engine"]) {
			$views .= "$print\n";
		} else {
			echo "$print<td align='right'><label>" . ($row["Engine"] == "InnoDB" && $row["Rows"] ? lang('~ %s', $row["Rows"]) : $row["Rows"]) . checkbox("data[]", $row["Name"], $checked, "", "form_uncheck('check-data');") . "</label>\n";
		}
	}
	echo $views;
} else {
	echo "<thead><tr><th style='text-align: left;'><label><input type='checkbox' id='check-databases' checked onclick='form_check(this, /^databases\\[/);'>" . lang('Database') . "</label></thead>\n";
	foreach (get_databases() as $db) {
		if (!information_schema($db)) {
			echo "<tr><td>" . checkbox("databases[]", $db, 1, $db, "form_uncheck('check-databases');") . "</label>\n";
		}
	}
}
?>
</table>
</form>
