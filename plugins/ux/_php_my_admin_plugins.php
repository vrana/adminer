<?php
	foreach (glob(__DIR__."/*.php") as $filename)
		if (__FILE__ != realpath($filename))		// except current file
			include_once $filename;

	$php_my_admin_plugins = array(
							// high priority to visually hide modifications
							// UI additional
							"tables_list_name_select"		=> new AdminerTablesListNameSelect(),
							"tables_list_filter"			=> new AdminerTablesListFilter(),
							// UI mod
							"duplicate_result_controls"		=> new AdminerDuplicateResultControls(),

							// secondary plugins
							// UI mod
							"frameset_simulator"			=> new AdminerFramesetSimulator(true),
							"disable_highlight"				=> new AdminerDisableHighlight(),
							"executed_query_output_modifier"=> new AdminerExecutedQueryOutputModifier(),
							"submit_at_right"				=> new AdminerSubmitAtRight(),
							"table_hscroll_followers"		=> new AdminerTableHScrollFollowers(),
							// UI additional
							"sql_command_table_fields"		=> new AdminerSqlCommandTableFields(),
							"export_per_table"				=> new AdminerExportPerTable(),
							"table_structure_advanced"		=> new AdminerTableStructureAdvanced(),
							"table_edit_by_fields"			=> new AdminerTableEditByFields(),
							"table_record_field_details"	=> new AdminerTableRecordFieldDetails(),
							"table_sort_desc_before_title"	=> new AdminerTableSortDescBeforeTitle(),
							// Tools
							"db_diagnostics_queries"		=> new AdminerDbDiagnosticsQueries(),
							"sql_command_transaction"		=> new AdminerSqlCommandTransaction()
							);
	$_php_my_admin_plugins = $php_my_admin_plugins;		// user friendly variable
?>