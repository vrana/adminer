<?php

/** Link system tables (in mysql and information_schema databases) by foreign keys
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerForeignSystem {
	
	function foreignKeys($table) {
		if (DRIVER == "server" && DB == "mysql") {
			switch ($table) {
				case "columns_priv": return array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User")));
				case "db": return array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User")));
				case "help_category": return array(array("table" => "help_category", "source" => array("parent_category_id"), "target" => array("help_category_id")));
				case "help_relation": return array(array("table" => "help_topic", "source" => array("help_topic_id"), "target" => array("help_topic_id")), array("table" => "help_keyword", "source" => array("help_keyword_id"), "target" => array("help_keyword_id")));
				case "help_topic": return array(array("table" => "help_category", "source" => array("help_category_id"), "target" => array("help_category_id")));
				case "procs_priv": return array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User")), array("table" => "proc", "source" => array("Db", "Routine_name"), "target" => array("db", "name")));
				case "tables_priv": return array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User")));
				case "time_zone_name": return array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id")));
				case "time_zone_transition": return array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id")), array("table" => "time_zone_transition_type", "source" => array("Time_zone_id", "Transition_type_id"), "target" => array("Time_zone_id", "Transition_type_id")));
				case "time_zone_transition_type": return array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id")));
			}
		} elseif (DB == "information_schema") {
			$schemata = array("table" => "SCHEMATA", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA"), "target" => array("CATALOG_NAME", "SCHEMA_NAME"));
			$tables = array("table" => "TABLES", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME"), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME"));
			$columns = array("table" => "COLUMNS", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME", "COLUMN_NAME"), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME", "COLUMN_NAME"));
			$character_sets = array("table" => "CHARACTER_SETS", "source" => array("CHARACTER_SET_NAME"), "target" => array("CHARACTER_SET_NAME"));
			$collations = array("table" => "COLLATIONS", "source" => array("COLLATION_NAME"), "target" => array("COLLATION_NAME"));
			$routine_charsets = array(array("source" => array("CHARACTER_SET_CLIENT")) + $character_sets, array("source" => array("COLLATION_CONNECTION")) + $collations, array("source" => array("DATABASE_COLLATION")) + $collations);
			switch ($table) {
				case "CHARACTER_SETS": return array(array("source" => array("DEFAULT_COLLATE_NAME")) + $collations);
				case "COLLATIONS": return array($character_sets);
				case "COLLATION_CHARACTER_SET_APPLICABILITY": return array($collations, $character_sets);
				case "COLUMNS": return array($schemata, $tables, $character_sets, $collations);
				case "COLUMN_PRIVILEGES": return array($schemata, $tables, $columns);
				case "TABLES": return array($schemata, array("source" => array("TABLE_COLLATION")) + $collations);
				case "SCHEMATA": return array(array("source" => array("DEFAULT_CHARACTER_SET_NAME")) + $character_sets, array("source" => array("DEFAULT_COLLATION_NAME")) + $collations);
				case "EVENTS": return array_merge(array(array("source" => array("EVENT_CATALOG", "EVENT_SCHEMA")) + $schemata), $routine_charsets);
				case "FILES": return array($schemata, $tables);
				case "KEY_COLUMN_USAGE": return array(array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA")) + $schemata, $schemata, $tables, $columns, array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA")) + $schemata, array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA", "REFERENCED_TABLE_NAME")) + $tables, array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA", "REFERENCED_TABLE_NAME", "REFERENCED_COLUMN_NAME")) + $columns);
				case "PARTITIONS": return array($schemata, $tables);
				case "REFERENTIAL_CONSTRAINTS": return array(array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA")) + $schemata, array("source" => array("UNIQUE_CONSTRAINT_CATALOG", "UNIQUE_CONSTRAINT_SCHEMA")) + $schemata, array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA", "TABLE_NAME")) + $tables, array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA", "REFERENCED_TABLE_NAME")) + $tables);
				case "ROUTINES": return array_merge(array(array("source" => array("ROUTINE_CATALOG", "ROUTINE_SCHEMA")) + $schemata), $routine_charsets);
				case "SCHEMA_PRIVILEGES": return array($schemata);
				case "STATISTICS": return array($schemata, $tables, $columns, array("source" => array("TABLE_CATALOG", "INDEX_SCHEMA")) + $schemata);
				case "TABLE_CONSTRAINTS": return array(array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA")) + $schemata, array("source" => array("CONSTRAINT_CATALOG", "TABLE_SCHEMA")) + $schemata, array("source" => array("CONSTRAINT_CATALOG", "TABLE_SCHEMA", "TABLE_NAME")) + $tables);
				case "TABLE_PRIVILEGES": return array($schemata, $tables);
				case "TRIGGERS": return array_merge(array(array("source" => array("TRIGGER_CATALOG", "TRIGGER_SCHEMA")) + $schemata, array("source" => array("EVENT_OBJECT_CATALOG", "EVENT_OBJECT_SCHEMA")) + $schemata, array("source" => array("EVENT_OBJECT_CATALOG", "EVENT_OBJECT_SCHEMA", "EVENT_OBJECT_TABLE")) + $tables), $routine_charsets);
				case "VIEWS": return array($schemata);
			}
		}
	}
	
}
