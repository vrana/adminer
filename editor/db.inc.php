<?php
page_header(lang('Server'), "", false);

if ($adminer->homepage()) {
	echo "<form action='' method='post'>\n";
	echo "<p>" . lang('Search data in tables') . ": <input type='search' name='query' value='" . h($_POST["query"]) . "'> <input type='submit' value='" . lang('Search') . "'>\n";
	if ($_POST["query"] != "") {
		search_tables();
	}
	echo "<table cellspacing='0' class='nowrap checkable'>\n";
	echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
	echo '<thead><tr class="wrap">';
	echo '<td><input id="check-all" type="checkbox" class="jsonly">' . script("qs('#check-all').onclick = partial(formCheck, /^tables\[/);", "");
	echo '<th>' . lang('Table');
	echo '<td>' . lang('Rows');
	echo "</thead>\n";
	
	foreach (table_status() as $table => $row) {
		$name = $adminer->tableName($row);
		if (isset($row["Engine"]) && $name != "") {
			echo '<tr' . odd() . '><td>' . checkbox("tables[]", $table, in_array($table, (array) $_POST["tables"], true));
			echo "<th><a href='" . h(ME) . 'select=' . urlencode($table) . "'>$name</a>";
			$val = format_number($row["Rows"]);
			echo "<td align='right'><a href='" . h(ME . "edit=") . urlencode($table) . "'>" . ($row["Engine"] == "InnoDB" && $val ? "~ $val" : $val) . "</a>";
		}
	}
	
	echo "</table>\n";
	echo "</form>\n";
	echo script("tableCheck();");
}
