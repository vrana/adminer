<?php

/** Link system tables (in mysql and information_schema databases) by foreign keys
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerForeignSystem {

	function foreignKeys($table) {
		if (Adminer\DRIVER == "server" && Adminer\DB == "mysql") {
			$return = array(
				"columns_priv" => array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User"))),
				"db" => array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User"))),
				"help_category" => array(array("table" => "help_category", "source" => array("parent_category_id"), "target" => array("help_category_id"))),
				"help_relation" => array(array("table" => "help_topic", "source" => array("help_topic_id"), "target" => array("help_topic_id")), array("table" => "help_keyword", "source" => array("help_keyword_id"), "target" => array("help_keyword_id"))),
				"help_topic" => array(array("table" => "help_category", "source" => array("help_category_id"), "target" => array("help_category_id"))),
				"procs_priv" => array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User")), array("table" => "proc", "source" => array("Db", "Routine_name"), "target" => array("db", "name"))),
				"tables_priv" => array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User"))),
				"time_zone_name" => array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id"))),
				"time_zone_transition" => array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id")), array("table" => "time_zone_transition_type", "source" => array("Time_zone_id", "Transition_type_id"), "target" => array("Time_zone_id", "Transition_type_id"))),
				"time_zone_transition_type" => array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id"))),
			);
			return $return[$table];
		} elseif (Adminer\DB == "information_schema") {
			$schemata = array("table" => "SCHEMATA", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA"), "target" => array("CATALOG_NAME", "SCHEMA_NAME"));
			$tables = array("table" => "TABLES", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME"), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME"));
			$columns = array("table" => "COLUMNS", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME", "COLUMN_NAME"), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME", "COLUMN_NAME"));
			$character_sets = array("table" => "CHARACTER_SETS", "source" => array("CHARACTER_SET_NAME"), "target" => array("CHARACTER_SET_NAME"));
			$collations = array("table" => "COLLATIONS", "source" => array("COLLATION_NAME"), "target" => array("COLLATION_NAME"));
			$routine_charsets = array(array("source" => array("CHARACTER_SET_CLIENT")) + $character_sets, array("source" => array("COLLATION_CONNECTION")) + $collations, array("source" => array("DATABASE_COLLATION")) + $collations);
			$return = array(
				"CHARACTER_SETS" => array(array("source" => array("DEFAULT_COLLATE_NAME")) + $collations),
				"COLLATIONS" => array($character_sets),
				"COLLATION_CHARACTER_SET_APPLICABILITY" => array($collations, $character_sets),
				"COLUMNS" => array($schemata, $tables, $character_sets, $collations),
				"COLUMN_PRIVILEGES" => array($schemata, $tables, $columns),
				"TABLES" => array($schemata, array("source" => array("TABLE_COLLATION")) + $collations),
				"SCHEMATA" => array(array("source" => array("DEFAULT_CHARACTER_SET_NAME")) + $character_sets, array("source" => array("DEFAULT_COLLATION_NAME")) + $collations),
				"EVENTS" => array_merge(array(array("source" => array("EVENT_CATALOG", "EVENT_SCHEMA")) + $schemata), $routine_charsets),
				"FILES" => array($schemata, $tables),
				"KEY_COLUMN_USAGE" => array(
					array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA")) + $schemata,
					$schemata,
					$tables,
					$columns,
					array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA")) + $schemata,
					array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA", "REFERENCED_TABLE_NAME")) + $tables,
					array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA", "REFERENCED_TABLE_NAME", "REFERENCED_COLUMN_NAME")) + $columns,
				),
				"PARTITIONS" => array($schemata, $tables),
				"REFERENTIAL_CONSTRAINTS" => array(
					array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA")) + $schemata,
					array("source" => array("UNIQUE_CONSTRAINT_CATALOG", "UNIQUE_CONSTRAINT_SCHEMA")) + $schemata,
					array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA", "TABLE_NAME")) + $tables,
					array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA", "REFERENCED_TABLE_NAME")) + $tables,
				),
				"ROUTINES" => array_merge(array(array("source" => array("ROUTINE_CATALOG", "ROUTINE_SCHEMA")) + $schemata), $routine_charsets),
				"SCHEMA_PRIVILEGES" => array($schemata),
				"STATISTICS" => array($schemata, $tables, $columns, array("source" => array("TABLE_CATALOG", "INDEX_SCHEMA")) + $schemata),
				"TABLE_CONSTRAINTS" => array(
					array("source" => array("CONSTRAINT_CATALOG", "CONSTRAINT_SCHEMA")) + $schemata,
					array("source" => array("CONSTRAINT_CATALOG", "TABLE_SCHEMA")) + $schemata,
					array("source" => array("CONSTRAINT_CATALOG", "TABLE_SCHEMA", "TABLE_NAME")) + $tables,
				),
				"TABLE_PRIVILEGES" => array($schemata, $tables),
				"TRIGGERS" => array_merge(array(
					array("source" => array("TRIGGER_CATALOG", "TRIGGER_SCHEMA")) + $schemata,
					array("source" => array("EVENT_OBJECT_CATALOG", "EVENT_OBJECT_SCHEMA")) + $schemata,
					array("source" => array("EVENT_OBJECT_CATALOG", "EVENT_OBJECT_SCHEMA", "EVENT_OBJECT_TABLE")) + $tables,
				), $routine_charsets),
				"VIEWS" => array($schemata),
			);
			return $return[$table];
		}
	}
}
