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
				"help_relation" => array(
					array("table" => "help_topic", "source" => array("help_topic_id"), "target" => array("help_topic_id")),
					array("table" => "help_keyword", "source" => array("help_keyword_id"), "target" => array("help_keyword_id")),
				),
				"help_topic" => array(array("table" => "help_category", "source" => array("help_category_id"), "target" => array("help_category_id"))),
				"procs_priv" => array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User")), array("table" => "proc", "source" => array("Db", "Routine_name"), "target" => array("db", "name"))),
				"tables_priv" => array(array("table" => "user", "source" => array("Host", "User"), "target" => array("Host", "User"))),
				"time_zone_name" => array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id"))),
				"time_zone_transition" => array(
					array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id")),
					array("table" => "time_zone_transition_type", "source" => array("Time_zone_id", "Transition_type_id"), "target" => array("Time_zone_id", "Transition_type_id")),
				),
				"time_zone_transition_type" => array(array("table" => "time_zone", "source" => array("Time_zone_id"), "target" => array("Time_zone_id"))),
			);
			return $return[$table];
		} elseif (Adminer\DB == "information_schema") {
			$schemata = $this->schemata("TABLE");
			$tables = $this->tables("TABLE");
			$columns = array("table" => "COLUMNS", "source" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME", "COLUMN_NAME"), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME", "COLUMN_NAME"));
			$character_sets = $this->character_sets("CHARACTER_SET_NAME");
			$collations = $this->collations("COLLATION_NAME");
			$routine_charsets = array($this->character_sets("CHARACTER_SET_CLIENT"), $this->collations("COLLATION_CONNECTION"), $this->collations("DATABASE_COLLATION"));
			$return = array(
				"CHARACTER_SETS" => array($this->collations("DEFAULT_COLLATE_NAME")),
				"CHECK_CONSTRAINTS" => array($this->schemata("CONSTRAINT")),
				"COLLATIONS" => array($character_sets),
				"COLLATION_CHARACTER_SET_APPLICABILITY" => array($collations, $character_sets),
				"COLUMNS" => array($schemata, $tables, $character_sets, $collations),
				"COLUMN_PRIVILEGES" => array($schemata, $tables, $columns),
				"COLUMNS_EXTENSIONS" => array($schemata, $tables, $columns),
				"TABLES" => array($schemata, $this->collations("TABLE_COLLATION")),
				"SCHEMATA" => array($this->character_sets("DEFAULT_CHARACTER_SET_NAME"), $this->collations("DEFAULT_COLLATION_NAME")),
				"EVENTS" => array_merge(array($this->schemata("EVENT")), $routine_charsets),
				"FILES" => array($schemata, $tables),
				"KEY_COLUMN_USAGE" => array(
					$this->schemata("CONSTRAINT"),
					$schemata,
					$tables,
					$columns,
					$this->schemata("TABLE", "REFERENCED_TABLE"),
					$this->tables("TABLE", "REFERENCED_TABLE"),
					array("source" => array("TABLE_CATALOG", "REFERENCED_TABLE_SCHEMA", "REFERENCED_TABLE_NAME", "REFERENCED_COLUMN_NAME")) + $columns,
				),
				"PARTITIONS" => array($schemata, $tables),
				"REFERENTIAL_CONSTRAINTS" => array(
					$this->schemata("CONSTRAINT"),
					$this->schemata("UNIQUE_CONSTRAINT"),
					$this->tables("CONSTRAINT", "CONSTRAINT", "TABLE_NAME"),
					$this->tables("CONSTRAINT", "CONSTRAINT", "REFERENCED_TABLE_NAME"),
				),
				"ROUTINES" => array_merge(array($this->schemata("ROUTINE")), $routine_charsets),
				"SCHEMA_PRIVILEGES" => array($schemata),
				"STATISTICS" => array($schemata, $tables, $columns, $this->schemata("TABLE", "INDEX")),
				"TABLE_CONSTRAINTS" => array(
					$this->schemata("CONSTRAINT"),
					$this->schemata("CONSTRAINT", "TABLE"),
					$this->tables("CONSTRAINT", "TABLE"),
				),
				"TABLE_CONSTRAINTS_EXTENSIONS" => array($this->schemata("CONSTRAINT"), $this->tables("CONSTRAINT", "CONSTRAINT", "TABLE_NAME")),
				"TABLE_PRIVILEGES" => array($schemata, $tables),
				"TRIGGERS" => array_merge(array(
					$this->schemata("TRIGGER"),
					$this->schemata("EVENT_OBJECT"),
					$this->tables("EVENT_OBJECT", "EVENT_OBJECT", "EVENT_OBJECT_TABLE"),
				), $routine_charsets),
				"VIEWS" => array($schemata, $this->character_sets("CHARACTER_SET_CLIENT"), $this->collations("COLLATION_CONNECTION")),
			);
			return $return[$table];
		}
	}

	private function schemata($catalog, $schema = null) {
		return array("table" => "SCHEMATA", "source" => array($catalog . "_CATALOG", ($schema ?: $catalog) . "_SCHEMA"), "target" => array("CATALOG_NAME", "SCHEMA_NAME"));
	}

	private function tables($catalog, $schema = null, $table_name = null) {
		$schema = ($schema ?: $catalog);
		return array("table" => "TABLES", "source" => array($catalog . "_CATALOG", $schema . "_SCHEMA", ($table_name ?: $schema . "_NAME")), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME"));
	}

	private function character_sets($source) {
		return array("table" => "CHARACTER_SETS", "source" => array($source), "target" => array("CHARACTER_SET_NAME"));
	}

	private function collations($source) {
		return array("table" => "COLLATIONS", "source" => array($source), "target" => array("COLLATION_NAME"));
	}
}
