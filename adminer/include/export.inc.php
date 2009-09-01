<?php
function dump_table($table, $style, $is_view = false) {
	global $dbh;
	if ($_POST["format"] == "csv") {
		dump("\xef\xbb\xbf"); // UTF-8 byte order mark
		if ($style) {
			dump_csv(array_keys(fields($table)));
		}
	} elseif ($style) {
		$result = $dbh->query("SHOW CREATE TABLE " . idf_escape($table));
		if ($result) {
			if ($style == "DROP+CREATE") {
				dump("DROP " . ($is_view ? "VIEW" : "TABLE") . " IF EXISTS " . idf_escape($table) . ";\n");
			}
			$create = $dbh->result($result, 1);
			dump(($style != "CREATE+ALTER" ? $create : ($is_view ? substr_replace($create, " OR REPLACE", 6, 0) : substr_replace($create, " IF NOT EXISTS", 12, 0))) . ";\n\n");
		}
		if ($style == "CREATE+ALTER" && !$is_view) {
			// create procedure which iterates over original columns and adds new and removes old
			$query = "SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLLATION_NAME, COLUMN_TYPE, EXTRA, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $dbh->quote($table) . " ORDER BY ORDINAL_POSITION";
			dump("DELIMITER ;;
CREATE PROCEDURE adminer_alter (INOUT alter_command text) BEGIN
	DECLARE _column_name, _collation_name, _column_type, after varchar(64) DEFAULT '';
	DECLARE _column_default longtext;
	DECLARE _is_nullable char(3);
	DECLARE _extra varchar(20);
	DECLARE _column_comment varchar(255);
	DECLARE done, set_after bool DEFAULT 0;
	DECLARE add_columns text DEFAULT '");
			$fields = array();
			$result = $dbh->query($query);
			$after = "";
			while ($row = $result->fetch_assoc()) {
				$row["default"] = (isset($row["COLUMN_DEFAULT"]) ? $dbh->quote($row["COLUMN_DEFAULT"]) : "NULL");
				$row["after"] = $dbh->quote($after); //! rgt AFTER lft, lft AFTER id doesn't work
				$row["alter"] = escape_string(idf_escape($row["COLUMN_NAME"])
					. " $row[COLUMN_TYPE]"
					. ($row["COLLATION_NAME"] ? " COLLATE $row[COLLATION_NAME]" : "")
					. (isset($row["COLUMN_DEFAULT"]) ? " DEFAULT $row[default]" : "")
					. ($row["IS_NULLABLE"] == "YES" ? "" : " NOT NULL")
					. ($row["EXTRA"] ? " $row[EXTRA]" : "")
					. ($row["COLUMN_COMMENT"] ? " COMMENT " . $dbh->quote($row["COLUMN_COMMENT"]) : "")
					. ($after ? " AFTER " . idf_escape($after) : " FIRST")
				);
				dump(", ADD $row[alter]");
				$fields[] = $row;
				$after = $row["COLUMN_NAME"];
			}
			dump("';
	DECLARE columns CURSOR FOR $query;
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
	SET @alter_table = '';
	OPEN columns;
	REPEAT
		FETCH columns INTO _column_name, _column_default, _is_nullable, _collation_name, _column_type, _extra, _column_comment;
		IF NOT done THEN
			SET set_after = 1;
			CASE _column_name");
			foreach ($fields as $row) {
				dump("
				WHEN " . $dbh->quote($row["COLUMN_NAME"]) . " THEN
					SET add_columns = REPLACE(add_columns, ', ADD $row[alter]', '');
					IF NOT (_column_default <=> $row[default]) OR _is_nullable != '$row[IS_NULLABLE]' OR _collation_name != '$row[COLLATION_NAME]' OR _column_type != '$row[COLUMN_TYPE]' OR _extra != '$row[EXTRA]' OR _column_comment != " . $dbh->quote($row["COLUMN_COMMENT"]) . " OR after != $row[after] THEN
						SET @alter_table = CONCAT(@alter_table, ', MODIFY $row[alter]');
					END IF;"); //! don't replace in comment
			}
			dump("
				ELSE
					SET @alter_table = CONCAT(@alter_table, ', DROP ', _column_name);
					SET set_after = 0;
			END CASE;
			IF set_after THEN
				SET after = _column_name;
			END IF;
		END IF;
	UNTIL done END REPEAT;
	CLOSE columns;
	IF @alter_table != '' OR add_columns != '' THEN
		SET alter_command = CONCAT(alter_command, 'ALTER TABLE " . idf_escape($table) . "', SUBSTR(CONCAT(add_columns, @alter_table), 2), ';\\n');
	END IF;
END;;
DELIMITER ;
CALL adminer_alter(@adminer_alter);
DROP PROCEDURE adminer_alter;

");
			//! indexes
		}
	}
}

function dump_data($table, $style, $select = "") {
	global $dbh, $max_packet;
	if ($style) {
		if ($_POST["format"] != "csv" && $style == "TRUNCATE+INSERT") {
			dump("TRUNCATE " . idf_escape($table) . ";\n");
		}
		$result = $dbh->query(($select ? $select : "SELECT * FROM " . idf_escape($table)), 1); // 1 - MYSQLI_USE_RESULT //! enum and set as numbers, microtime
		if ($result) {
			$fields = fields($table);
			$insert = "";
			$buffer = "";
			while ($row = $result->fetch_assoc()) {
				if ($_POST["format"] == "csv") {
					dump_csv($row);
				} else {
					if (!$insert) {
						$insert = "INSERT INTO " . idf_escape($table) . " (" . implode(", ", array_map('idf_escape', array_keys($row))) . ") VALUES";
					}
					$row2 = array();
					foreach ($row as $key => $val) {
						$row2[$key] = (isset($val) ? (ereg('int|float|double|decimal', $fields[$key]["type"]) ? $val : $dbh->quote($val)) : "NULL"); //! columns looking like functions
					}
					$s = implode(",\t", $row2);
					if ($style == "INSERT+UPDATE") {
						$set = array();
						foreach ($row2 as $key => $val) {
							$set[] = idf_escape($key) . " = $val";
						}
						dump("$insert ($s) ON DUPLICATE KEY UPDATE " . implode(", ", $set) . ";\n");
					} else {
						$s = "\n($s)";
						if (!$buffer) {
							$buffer = $insert . $s;
						} elseif (strlen($buffer) + 1 + strlen($s) < $max_packet) { // 1 - separator length
							$buffer .= ",$s";
						} else {
							$buffer .= ";\n";
							dump($buffer);
							$buffer = $insert . $s;
						}
					}
				}
			}
			if ($_POST["format"] != "csv" && $style != "INSERT+UPDATE" && $buffer) {
				$buffer .= ";\n";
				dump($buffer);
			}
		}
	}
}

function dump_headers($identifier, $multi_table = false) {
	$compress = $_POST["compress"];
	$filename = (strlen($identifier) ? friendly_url($identifier) : "dump");
	$ext = ($_POST["format"] == "sql" ? "sql" : ($multi_table ? "tar" : "csv")); // multiple CSV packed to TAR
	header("Content-Type: " .
		($compress == "bz2" ? "application/x-bzip" :
		($compress == "gz" ? "application/x-gzip" :
		($ext == "tar" ? "application/x-tar" :
		($ext == "sql" || $_POST["output"] != "file" ? "text/plain" : "text/csv") . "; charset=utf-8"
	))));
	if ($_POST["output"] == "file" || $compress) {
		header("Content-Disposition: attachment; filename=$filename.$ext" . (ereg('[0-9a-z]', $compress) ? ".$compress" : ""));
	}
	session_write_close();
	return $ext;
}

$compress = array();
if (function_exists('gzencode')) {
	$compress['gz'] = 'gzip';
}
if (function_exists('bzcompress')) {
	$compress['bz2'] = 'bzip2';
}
// ZipArchive requires temporary file, ZIP can be created by gzcompress - see PEAR File_Archive
$dump_output = "<select name='output'>" . optionlist(array('text' => lang('open'), 'file' => lang('save'))) . "</select>";
$dump_format = "<select name='format'>" . optionlist(array('sql' => 'SQL', 'csv' => 'CSV')) . "</select>";
$dump_compress = ($compress ? "<select name='compress'><option>" . optionlist($compress) . "</select>" : "");
$max_packet = 1048576; // default, minimum is 1024
