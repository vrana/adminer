<?php
	foreach (glob(__DIR__."/*.php") as $filename) {
		include_once $filename;
	}

	$php_my_admin_plugins = array(
							"disable_highlight_in_textarea" => new AdminerDisableHighlightInTextarea(),
							"disable_highlight_in_code"		=> new AdminerDisableHighlightInCode(),
							"code_text_wrap"				=> new AdminerCodeTextWrap(),

							"frameset_simulator"			=> new AdminerFramesetSimulator(),
							"tables_list_name_select"		=> new AdminerTablesListNameSelect(),
							"tables_list_filter"			=> new AdminerTablesListFilter(),
							"sql_table_fields"				=> new AdminerSqlTableFields(),
							"table_structure_quick_edit"	=> new AdminerTableStructureQuickEdit(),
							"submit_at_right"				=> new AdminerSubmitAtRight(),
							"display_executed_sql"			=> new AdminerDisplayExecutedSQL(),
							"duplicate_result_controls"		=> new AdminerDuplicateResultControls(),
							"table_hscroll_followers"		=> new AdminerTableHScrollFollowers(),
							);
	$_php_my_admin_plugins = $php_my_admin_plugins;		// user friendly variable
?>