<?php
page_header(lang('Server'), "", false);

if ($adminer->homepage()) {
	echo "<form action='' method='post'>\n";
	echo "<p>" . lang('Search data in tables') . ": <input name='query' value='" . h($_POST["query"]) . "'> <input type='submit' value='" . lang('Search') . "'>\n";
	if ($_POST["query"] != "") {
		search_tables();
	}
	echo "<table cellspacing='0' class='nowrap checkable' onclick='tableClick(event);'>\n";
	echo '<thead><tr class="wrap"><td><input id="check-all" type="checkbox" onclick="formCheck(this, /^tables\[/);" class="jsonly"><th>' . lang('Table') . '<td>' . lang('Rows') . "</thead>\n";
	
	foreach (table_status() as $table => $row) {
		$name = $adminer->tableName($row);
		if (isset($row["Engine"]) && $name != "") {
			echo '<tr' . odd() . '><td>' . checkbox("tables[]", $table, in_array($table, (array) $_POST["tables"], true), "", "formUncheck('check-all');");
			echo "<th><a href='" . h(ME) . 'select=' . urlencode($table) . "'>$name</a>";
			$val = format_number($row["Rows"]);
			echo "<td align='right'><a href='" . h(ME . "edit=") . urlencode($table) . "'>" . ($row["Engine"] == "InnoDB" && $val ? "~ $val" : $val) . "</a>";
		}
	}
	
	echo "</table>\n";
	echo "<script type='text/javascript'>tableCheck();</script>\n";
	echo "</form>\n";
}
