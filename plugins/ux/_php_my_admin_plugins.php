<?php
	foreach (glob(__DIR__."/*.php") as $filename)
		if (__FILE__ != realpath($filename))		// except current file
			include_once $filename;

	$php_my_admin_plugins = array(
							"disable_highlight"				=> new AdminerDisableHighlight(),
							"executed_query_output_modifier"=> new AdminerExecutedQueryOutputModifier(),
							"submit_at_right"				=> new AdminerSubmitAtRight(),
							"duplicate_result_controls"		=> new AdminerDuplicateResultControls(),
							"table_hscroll_followers"		=> new AdminerTableHScrollFollowers(),

							"frameset_simulator"			=> new AdminerFramesetSimulator(true),
							"tables_list_name_select"		=> new AdminerTablesListNameSelect(),
							"tables_list_filter"			=> new AdminerTablesListFilter(),
							"sql_command_table_fields"		=> new AdminerSqlCommandTableFields(),
							"table_structure_advanced"		=> new AdminerTableStructureAdvanced(),
							"table_record_field_details"	=> new AdminerTableRecordFieldDetails(),
							);
	$_php_my_admin_plugins = $php_my_admin_plugins;		// user friendly variable
?>