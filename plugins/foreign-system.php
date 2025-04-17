<?php

/** Link system tables (in "mysql", "information_schema" and "pg_catalog" schemas) by foreign keys
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerForeignSystem extends Adminer\Plugin {

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

		} elseif (Adminer\DB == "information_schema" || $_GET["ns"] == "information_schema") {
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
				"TABLES" => array($schemata, $this->collations("TABLE_COLLATION"), array("table" => "ENGINES", "source" => array("ENGINE"), "target" => array("ENGINE"))),
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
				"PARAMETERS" => array($this->schemata("SPECIFIC"), array("table" => "ROUTINES", "source" => array("SPECIFIC_CATALOG", "SPECIFIC_SCHEMA", "SPECIFIC_NAME"), "target" => array("ROUTINE_CATALOG", "ROUTINE_SCHEMA", "SPECIFIC_NAME"))),
				"PARTITIONS" => array($schemata, $tables),
				"REFERENTIAL_CONSTRAINTS" => array(
					$this->schemata("CONSTRAINT"),
					$this->schemata("UNIQUE_CONSTRAINT"),
					$this->tables("CONSTRAINT", "CONSTRAINT", "TABLE_NAME"),
					$this->tables("CONSTRAINT", "CONSTRAINT", "REFERENCED_TABLE_NAME"),
				),
				"ROUTINES" => array_merge(array($this->schemata("ROUTINE")), $routine_charsets),
				"SCHEMA_PRIVILEGES" => array($schemata),
				"SCHEMATA_EXTENSIONS" => array(array("table" => "SCHEMATA", "source" => array("CATALOG_NAME", "SCHEMA_NAME"), "target" => array("CATALOG_NAME", "SCHEMA_NAME"))),
				"STATISTICS" => array($schemata, $tables, $columns, $this->schemata("TABLE", "INDEX")),
				"TABLE_CONSTRAINTS" => array(
					$this->schemata("CONSTRAINT"),
					$this->schemata("CONSTRAINT", "TABLE"),
					$this->tables("CONSTRAINT", "TABLE"),
				),
				"TABLE_CONSTRAINTS_EXTENSIONS" => array($this->schemata("CONSTRAINT"), $this->tables("CONSTRAINT", "CONSTRAINT", "TABLE_NAME")),
				"TABLE_PRIVILEGES" => array($schemata, $tables),
				"TABLES_EXTENSIONS" => array($schemata, $tables),
				"TRIGGERS" => array_merge(array(
					$this->schemata("TRIGGER"),
					$this->schemata("EVENT_OBJECT"),
					$this->tables("EVENT_OBJECT", "EVENT_OBJECT", "EVENT_OBJECT_TABLE"),
				), $routine_charsets),
				"VIEWS" => array($schemata, $this->character_sets("CHARACTER_SET_CLIENT"), $this->collations("COLLATION_CONNECTION")),
				"VIEW_TABLE_USAGE" => array($schemata, $this->schemata("VIEW"), $tables, array("table" => "VIEWS", "source" => array("VIEW_CATALOG", "VIEW_SCHEMA", "VIEW_NAME"), "target" => array("TABLE_CATALOG", "TABLE_SCHEMA", "TABLE_NAME"))),
			);
			if ($_GET["ns"] == "information_schema") {
				$return = $this->lowerCase($return);
			}
			return $return[strtoupper($table)];

		} elseif (Adminer\DRIVER == "pgsql" && $_GET["ns"] == "pg_catalog") {
			$mapping = array(
				'pg_aggregate' => array('aggfnoid.proc', 'aggtransfn.proc', 'aggfinalfn.proc', 'aggcombinefn.proc', 'aggserialfn.proc', 'aggdeserialfn.proc', 'aggmtransfn.proc', 'aggminvtransfn.proc', 'aggmfinalfn.proc', 'aggsortop.operator', 'aggtranstype.type', 'aggmtranstype.type'),
				'pg_am' => array('amhandler.proc'),
				'pg_amop' => array('amopfamily.opfamily', 'amoplefttype.type', 'amoprighttype.type', 'amopopr.operator', 'amopmethod.am', 'amopsortfamily.opfamily'),
				'pg_amproc' => array('amprocfamily.opfamily', 'amproclefttype.type', 'amprocrighttype.type', 'amproc.proc'),
				'pg_attrdef' => array('adrelid.class', 'adnum.attribute.attnum'),
				'pg_attribute' => array('attrelid.class', 'atttypid.type', 'attcollation.collation'),
				'pg_auth_members' => array('roleid.authid', 'member.authid', 'grantor.authid'),
				'pg_cast' => array('castsource.type', 'casttarget.type', 'castfunc.proc'),
				'pg_class' => array('relnamespace.namespace', 'reltype.type', 'reloftype.type', 'relowner.authid', 'relam.am', 'reltablespace.tablespace', 'reltoastrelid.class', 'relrewrite.class'),
				'pg_collation' => array('collnamespace.namespace', 'collowner.authid'),
				'pg_constraint' => array('connamespace.namespace', 'conrelid.class', 'contypid.type', 'conindid.class', 'conparentid.constraint', 'confrelid.class', 'conkey.attribute.attnum', 'confkey.attribute.attnum', 'conpfeqop.operator', 'conppeqop.operator', 'conffeqop.operator', 'confdelsetcols.attribute.attnum', 'conexclop.operator'),
				'pg_conversion' => array('connamespace.namespace', 'conowner.authid', 'conproc.proc'),
				'pg_database' => array('datdba.authid', 'dattablespace.tablespace'),
				'pg_db_role_setting' => array('setdatabase.database', 'setrole.authid'),
				'pg_default_acl' => array('defaclrole.authid', 'defaclnamespace.namespace'),
				'pg_depend' => array('classid.class', 'refclassid.class'),
				'pg_description' => array('classoid.class'),
				'pg_enum' => array('enumtypid.type'),
				'pg_event_trigger' => array('evtowner.authid', 'evtfoid.proc'),
				'pg_extension' => array('extowner.authid', 'extnamespace.namespace', 'extconfig.class'),
				'pg_foreign_data_wrapper' => array('fdwowner.authid', 'fdwhandler.proc', 'fdwvalidator.proc'),
				'pg_foreign_server' => array('srvowner.authid', 'srvfdw.foreign_data_wrapper'),
				'pg_foreign_table' => array('ftrelid.class', 'ftserver.foreign_server'),
				'pg_index' => array('indexrelid.class', 'indrelid.class', 'indkey.attribute.attnum', 'indcollation.collation', 'indclass.opclass'),
				'pg_inherits' => array('inhrelid.class', 'inhparent.class'),
				'pg_init_privs' => array('classoid.class'),
				'pg_language' => array('lanowner.authid', 'lanplcallfoid.proc', 'laninline.proc', 'lanvalidator.proc'),
				'pg_largeobject' => array('loid.largeobject_metadata'),
				'pg_largeobject_metadata' => array('lomowner.authid'),
				'pg_namespace' => array('nspowner.authid'),
				'pg_opclass' => array('opcmethod.am', 'opcnamespace.namespace', 'opcowner.authid', 'opcfamily.opfamily', 'opcintype.type', 'opckeytype.type'),
				'pg_operator' => array('oprnamespace.namespace', 'oprowner.authid', 'oprleft.type', 'oprright.type', 'oprresult.type', 'oprcom.operator', 'oprnegate.operator', 'oprcode.proc', 'oprrest.proc', 'oprjoin.proc'),
				'pg_opfamily' => array('opfmethod.am', 'opfnamespace.namespace', 'opfowner.authid'),
				'pg_partitioned_table' => array('partrelid.class', 'partdefid.class', 'partattrs.attribute.attnum', 'partclass.opclass', 'partcollation.collation'),
				'pg_policy' => array('polrelid.class', 'polroles.authid'),
				'pg_proc' => array('pronamespace.namespace', 'proowner.authid', 'prolang.language', 'provariadic.type', 'prosupport.proc', 'prorettype.type', 'proargtypes.type', 'proallargtypes.type', 'protrftypes.type'),
				'pg_publication' => array('pubowner.authid'),
				'pg_publication_namespace' => array('pnpubid.publication', 'pnnspid.namespace'),
				'pg_publication_rel' => array('prpubid.publication', 'prrelid.class', 'prattrs.attribute.attnum'),
				'pg_range' => array('rngtypid.type', 'rngsubtype.type', 'rngmultitypid.type', 'rngcollation.collation', 'rngsubopc.opclass', 'rngcanonical.proc', 'rngsubdiff.proc'),
				'pg_rewrite' => array('ev_class.class'),
				'pg_seclabel' => array('classoid.class'),
				'pg_sequence' => array('seqrelid.class', 'seqtypid.type'),
				'pg_shdepend' => array('dbid.database', 'classid.class', 'refclassid.class'),
				'pg_shdescription' => array('classoid.class'),
				'pg_shseclabel' => array('classoid.class'),
				'pg_statistic' => array('starelid.class', 'staattnum.attribute.attnum', 'staop.operator', 'stacoll.collation'),
				'pg_statistic_ext' => array('stxrelid.class', 'stxnamespace.namespace', 'stxowner.authid', 'stxkeys.attribute.attnum'),
				'pg_statistic_ext_data' => array('stxoid.statistic_ext'),
				'pg_subscription' => array('subdbid.database', 'subowner.authid'),
				'pg_subscription_rel' => array('srsubid.subscription', 'srrelid.class'),
				'pg_tablespace' => array('spcowner.authid'),
				'pg_transform' => array('trftype.type', 'trflang.language', 'trffromsql.proc', 'trftosql.proc'),
				'pg_trigger' => array('tgrelid.class', 'tgparentid.trigger', 'tgfoid.proc', 'tgconstrrelid.class', 'tgconstrindid.class', 'tgconstraint.constraint', 'tgattr.attribute.attnum'),
				'pg_ts_config' => array('cfgnamespace.namespace', 'cfgowner.authid', 'cfgparser.ts_parser'),
				'pg_ts_config_map' => array('mapcfg.ts_config', 'mapdict.ts_dict'),
				'pg_ts_dict' => array('dictnamespace.namespace', 'dictowner.authid', 'dicttemplate.ts_template'),
				'pg_ts_parser' => array('prsnamespace.namespace', 'prsstart.proc', 'prstoken.proc', 'prsend.proc', 'prsheadline.proc', 'prslextype.proc'),
				'pg_ts_template' => array('tmplnamespace.namespace', 'tmplinit.proc', 'tmpllexize.proc'),
				'pg_type' => array('typnamespace.namespace', 'typowner.authid', 'typrelid.class', 'typsubscript.proc', 'typelem.type', 'typarray.type', 'typinput.proc', 'typoutput.proc', 'typreceive.proc', 'typsend.proc', 'typmodin.proc', 'typmodout.proc', 'typanalyze.proc', 'typbasetype.type', 'typcollation.collation'),
				'pg_user_mapping' => array('umuser.authid', 'umserver.foreign_server'),
			);
			$return = array();
			foreach ((array) $mapping[$table] as $val) {
				list($source, $target, $column) = explode(".", "$val.oid");
				$return[] = array("table" => "pg_$target", "source" => array($source), "target" => array($column));
			}
			return $return;
		}
	}

	private function lowerCase($value) {
		return (is_array($value) ? array_map(array($this, 'lowerCase'), $value) : strtolower($value));
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

	protected $translations = array(
		'cs' => array('' => 'Propojuje systémové tabulky (v databázích "mysql" a "information_schema") pomocí cizích klíčů'),
		'de' => array('' => 'Verknüpfen Sie Systemtabellen (in "mysql"- und "information_schema"-Datenbanken) durch Fremdschlüssel'),
		'pl' => array('' => 'Połącz tabele systemowe (w bazach danych "mysql" i "information_schema") za pomocą kluczy obcych'),
		'ro' => array('' => 'Conectați tabelele de sistem (în bazele de date "mysql" și "information_schema") prin chei străine'),
		'ja' => array('' => 'システムテーブル ("mysql" と "information_schema") を外部キーを用いて接続'),
	);
}
