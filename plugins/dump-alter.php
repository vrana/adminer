<?php

/** Exports one database (e.g. development) so that it can be synced with other database (e.g. production)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpAlter {
	
	function dumpFormat() {
		if (DRIVER == 'server') {
			return array('sql_alter' => 'Alter');
		}
	}
	
	function _database() {
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

SELECT @adminer_alter;
";
	}
	
	function dumpDatabase($db) {
		static $first = true;
		if ($_POST["format"] == "sql_alter") {
			if ($first) {
				$first = false;
				echo "SET @adminer_alter = '';\n\n";
				register_shutdown_function(array($this, '_database'));
			} else {
				$this->_database();
			}
			return true;
		}
	}
	
	function dumpTable($table, $style, $is_view = false) {
		if ($_POST["format"] == "sql_alter") {
			$create = create_sql($table, $_POST["auto_increment"]);
			if ($is_view) {
				echo substr_replace($create, " OR REPLACE", 6, 0) . ";\n\n";
			} else {
				echo substr_replace($create, " IF NOT EXISTS", 12, 0) . ";\n\n";
				// create procedure which iterates over original columns and adds new and removes old
				$query = "SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLLATION_NAME, COLUMN_TYPE, EXTRA, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . q($table) . " ORDER BY ORDINAL_POSITION";
				echo "DELIMITER ;;
CREATE PROCEDURE adminer_alter (INOUT alter_command text) BEGIN
	DECLARE _column_name, _collation_name, after varchar(64) DEFAULT '';
	DECLARE _column_type, _column_default text;
	DECLARE _is_nullable char(3);
	DECLARE _extra varchar(30);
	DECLARE _column_comment varchar(255);
	DECLARE done, set_after bool DEFAULT 0;
	DECLARE add_columns text DEFAULT '";
				$fields = array();
				$after = "";
				foreach (get_rows($query) as $row) {
					$default = $row["COLUMN_DEFAULT"];
					$row["default"] = ($default !== null ? q($default) : "NULL");
					$row["after"] = q($after); //! rgt AFTER lft, lft AFTER id doesn't work
					$row["alter"] = escape_string(idf_escape($row["COLUMN_NAME"])
						. " $row[COLUMN_TYPE]"
						. ($row["COLLATION_NAME"] ? " COLLATE $row[COLLATION_NAME]" : "")
						. ($default !== null ? " DEFAULT " . ($default == "CURRENT_TIMESTAMP" ? $default : $row["default"]) : "")
						. ($row["IS_NULLABLE"] == "YES" ? "" : " NOT NULL")
						. ($row["EXTRA"] ? " $row[EXTRA]" : "")
						. ($row["COLUMN_COMMENT"] ? " COMMENT " . q($row["COLUMN_COMMENT"]) : "")
						. ($after ? " AFTER " . idf_escape($after) : " FIRST")
					);
					echo ", ADD $row[alter]";
					$fields[] = $row;
					$after = $row["COLUMN_NAME"];
				}
				echo "';
	DECLARE columns CURSOR FOR $query;
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
	SET @alter_table = '';
	OPEN columns;
	REPEAT
		FETCH columns INTO _column_name, _column_default, _is_nullable, _collation_name, _column_type, _extra, _column_comment;
		IF NOT done THEN
			SET set_after = 1;
			CASE _column_name";
				foreach ($fields as $row) {
					echo "
				WHEN " . q($row["COLUMN_NAME"]) . " THEN
					SET add_columns = REPLACE(add_columns, ', ADD $row[alter]', IF(
						_column_default <=> $row[default] AND _is_nullable = '$row[IS_NULLABLE]' AND _collation_name <=> " . (isset($row["COLLATION_NAME"]) ? "'$row[COLLATION_NAME]'" : "NULL") . " AND _column_type = " . q($row["COLUMN_TYPE"]) . " AND _extra = '$row[EXTRA]' AND _column_comment = " . q($row["COLUMN_COMMENT"]) . " AND after = $row[after]
					, '', ', MODIFY $row[alter]'));"; //! don't replace in comment
				}
				echo "
				ELSE
					SET @alter_table = CONCAT(@alter_table, ', DROP ', '`', REPLACE(_column_name, '`', '``'), '`');
					SET set_after = 0;
			END CASE;
			IF set_after THEN
				SET after = _column_name;
			END IF;
		END IF;
	UNTIL done END REPEAT;
	CLOSE columns;
	IF @alter_table != '' OR add_columns != '' THEN
		SET alter_command = CONCAT(alter_command, 'ALTER TABLE " . table($table) . "', SUBSTR(CONCAT(add_columns, @alter_table), 2), ';\\n');
	END IF;
END;;
DELIMITER ;
CALL adminer_alter(@adminer_alter);
DROP PROCEDURE adminer_alter;

";
				//! indexes
			}
			return true;
		}
	}
	
	function dumpData() {
		if ($_POST["format"] == "sql_alter") {
			return true;
		}
	}
}
