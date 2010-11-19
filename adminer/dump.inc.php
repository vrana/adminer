<?php
$TABLE = $_GET["dump"];

if ($_POST) {
	$cookie = "";
	foreach (array("output", "format", "db_style", "table_style", "data_style") as $key) {
		$cookie .= "&$key=" . urlencode($_POST[$key]);
	}
	cookie("adminer_export", substr($cookie, 1));
	$ext = $adminer->dumpHeaders(($TABLE != "" ? $TABLE : DB), (DB == "" || count((array) $_POST["tables"] + (array) $_POST["data"]) > 1));
	$is_sql = ($_POST["format"] == "sql");
	if ($is_sql) {
		echo "-- Adminer $VERSION " . $drivers[DRIVER] . " dump

" . ($jush != "sql" ? "" : "SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = " . q($connection->result("SELECT @@time_zone")) . ";
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

");
	}
	
	$style = $_POST["db_style"];
	$databases = array(DB);
	if (DB == "") {
		$databases = $_POST["databases"];
		if (is_string($databases)) {
			$databases = explode("\n", rtrim(str_replace("\r", "", $databases), "\n"));
		}
	}
	foreach ((array) $databases as $db) {
		if ($connection->select_db($db)) {
			if ($is_sql && ereg('CREATE', $style) && ($create = $connection->result("SHOW CREATE DATABASE " . idf_escape($db), 1))) {
				if ($style == "DROP+CREATE") {
					echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
				}
				echo ($style == "CREATE+ALTER" ? preg_replace('~^CREATE DATABASE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n";
			}
			if ($is_sql) {
				if ($style) {
					echo use_sql($db) . ";\n\n";
				}
				if (in_array("CREATE+ALTER", array($style, $_POST["table_style"]))) {
					echo "SET @adminer_alter = '';\n\n";
				}
				$out = "";
				if ($_POST["routines"]) {
					foreach (array("FUNCTION", "PROCEDURE") as $routine) {
						foreach (get_rows("SHOW $routine STATUS WHERE Db = " . q($db), null, "-- ") as $row) {
							$out .= ($style != 'DROP+CREATE' ? "DROP $routine IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "")
							. $connection->result("SHOW CREATE $routine " . idf_escape($row["Name"]), 2) . ";;\n\n";
						}
					}
				}
				if ($_POST["events"]) {
					foreach (get_rows("SHOW EVENTS", null, "-- ") as $row) {
						$out .= ($style != 'DROP+CREATE' ? "DROP EVENT IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "")
						. $connection->result("SHOW CREATE EVENT " . idf_escape($row["Name"]), 3) . ";;\n\n";
					}
				}
				if ($out) {
					echo "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n";
				}
			}
			
			if ($_POST["table_style"] || $_POST["data_style"]) {
				$views = array();
				foreach (table_status() as $row) {
					$table = (DB == "" || in_array($row["Name"], (array) $_POST["tables"]));
					$data = (DB == "" || in_array($row["Name"], (array) $_POST["data"]));
					if ($table || $data) {
						if (!is_view($row)) {
							if ($ext == "tar") {
								ob_start();
							}
							$adminer->dumpTable($row["Name"], ($table ? $_POST["table_style"] : ""));
							if ($data) {
								$adminer->dumpData($row["Name"], $_POST["data_style"], "SELECT * FROM " . table($row["Name"]));
							}
							if ($is_sql && $_POST["triggers"]) {
								$triggers = trigger_sql($row["Name"], $_POST["table_style"]);
								if ($triggers) {
									echo "\nDELIMITER ;;\n$triggers\nDELIMITER ;\n";
								}
							}
							if ($ext == "tar") {
								echo tar_file((DB != "" ? "" : "$db/") . "$row[Name].csv", ob_get_clean());
							} elseif ($is_sql) {
								echo "\n";
							}
						} elseif ($is_sql) {
							$views[] = $row["Name"];
						}
					}
				}
				foreach ($views as $view) {
					$adminer->dumpTable($view, $_POST["table_style"], true);
				}
				if ($ext == "tar") {
					echo pack("x512");
				}
			}
			
			if ($style == "CREATE+ALTER" && $is_sql) {
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
				foreach (get_rows($query) as $row) {
					$comment = q($row["ENGINE"] == "InnoDB" ? preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["TABLE_COMMENT"]) : $row["TABLE_COMMENT"]);
					echo "
				WHEN " . q($row["TABLE_NAME"]) . " THEN
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
			if (in_array("CREATE+ALTER", array($style, $_POST["table_style"])) && $is_sql) {
				echo "SELECT @adminer_alter;\n";
			}
		}
	}
	if ($is_sql) {
		echo "-- " . $connection->result("SELECT NOW()") . "\n";
	}
	exit;
}

page_header(lang('Export'), "", ($_GET["export"] != "" ? array("table" => $_GET["export"]) : array()), DB);
?>

<form action="" method="post">
<table cellspacing="0">
<?php
$db_style = array('', 'USE', 'DROP+CREATE', 'CREATE');
$table_style = array('', 'DROP+CREATE', 'CREATE');
$data_style = array('', 'TRUNCATE+INSERT', 'INSERT');
if ($jush == "sql") {
	$db_style[] = 'CREATE+ALTER';
	$table_style[] = 'CREATE+ALTER';
	$data_style[] = 'INSERT+UPDATE';
}
parse_str($_COOKIE["adminer_export"], $row);
if (!$row) {
	$row = array("output" => "text", "format" => "sql", "db_style" => (DB != "" ? "" : "CREATE"), "table_style" => "DROP+CREATE", "data_style" => "INSERT");
}
$checked = ($_GET["dump"] == "");
echo "<tr><th>" . lang('Output') . "<td>" . html_select("output", $adminer->dumpOutput(), $row["output"], 0) . "\n"; // 0 - radio
echo "<tr><th>" . lang('Format') . "<td>" . html_select("format", $adminer->dumpFormat(), $row["format"], 0) . "\n"; // 0 - radio
echo ($jush == "sqlite" ? "" : "<tr><th>" . lang('Database') . "<td>" . html_select('db_style', $db_style, $row["db_style"])
	. (support("routine") ? checkbox("routines", 1, $checked, lang('Routines')) : "")
	. (support("event") ? checkbox("events", 1, $checked, lang('Events')) : "")
);
echo "<tr><th>" . lang('Tables') . "<td>" . html_select('table_style', $table_style, $row["table_style"])
	. checkbox("auto_increment", 1, $row["table_style"], lang('Auto Increment'))
	. (support("trigger") ? checkbox("triggers", 1, $row["table_style"], lang('Triggers')) : "")
;
echo "<tr><th>" . lang('Data') . "<td>" . html_select('data_style', $data_style, $row["data_style"]);
?>
</table>
<p><input type="submit" value="<?php echo lang('Export'); ?>">

<table cellspacing="0">
<?php
$prefixes = array();
if (DB != "") {
	$checked = ($TABLE != "" ? "" : " checked");
	echo "<thead><tr>";
	echo "<th style='text-align: left;'><label><input type='checkbox' id='check-tables'$checked onclick='formCheck(this, /^tables\\[/);'>" . lang('Tables') . "</label>";
	echo "<th style='text-align: right;'><label>" . lang('Data') . "<input type='checkbox' id='check-data'$checked onclick='formCheck(this, /^data\\[/);'></label>";
	echo "</thead>\n";
	$views = "";
	//! defer number of rows to JavaScript
	foreach (table_status() as $row) {
		$name = $row["Name"];
		$prefix = ereg_replace("_.*", "", $name);
		$checked = ($TABLE == "" || $TABLE == (substr($TABLE, -1) == "%" ? "$prefix%" : $name)); //! % may be part of table name
		$print = "<tr><td>" . checkbox("tables[]", $name, $checked, $name, "formUncheck('check-tables');");
		if (is_view($row)) {
			$views .= "$print\n";
		} else {
			echo "$print<td align='right'><label>" . ($row["Engine"] == "InnoDB" && $row["Rows"] ? "~ " : "") . $row["Rows"] . checkbox("data[]", $name, $checked, "", "formUncheck('check-data');") . "</label>\n";
		}
		$prefixes[$prefix]++;
	}
	echo $views;
} else {
	echo "<thead><tr><th style='text-align: left;'><label><input type='checkbox' id='check-databases'" . ($TABLE == "" ? " checked" : "") . " onclick='formCheck(this, /^databases\\[/);'>" . lang('Database') . "</label></thead>\n";
	$databases = get_databases();
	if ($databases) {
		foreach ($databases as $db) {
			if (!information_schema($db)) {
				$prefix = ereg_replace("_.*", "", $db);
				echo "<tr><td>" . checkbox("databases[]", $db, $TABLE == "" || $TABLE == "$prefix%", $db, "formUncheck('check-databases');") . "</label>\n";
				$prefixes[$prefix]++;
			}
		}
	} else {
		echo "<tr><td><textarea name='databases' rows='10' cols='20' onkeypress='return textareaKeypress(this, event);'></textarea>";
	}
}
?>
</table>
</form>
<?php
$first = true;
foreach ($prefixes as $key => $val) {
	if ($key != "" && $val > 1) {
		echo ($first ? "<p>" : " ") . "<a href='" . h(ME) . "dump=" . urlencode("$key%") . "'>" . h($key) . "</a>";
		$first = false;
	}
}
