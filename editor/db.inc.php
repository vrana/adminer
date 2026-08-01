<?php
namespace Adminer;

page_header(lang('Server'), "", false);

if (adminer()->homepage()) {
	echo "<form action='' method='post'>\n";
	echo "<p>" . lang('Search data in tables') . ": <input type='search' name='query' value='" . h($_POST["query"]) . "'> <input type='submit' value='" . lang('Search') . "'>\n";
	if ($_POST["query"] != "") {
		search_tables();
	}
	echo "<div class='scrollable'>\n";
	echo "<table class='nowrap checkable odds'" . on('click', 'tableClick') . on('dblclick', 'tableClick') . ">\n";
	echo '<thead><tr class="wrap">';
	echo '<td><input id="check-all" type="checkbox" class="jsonly"' . on('click', 'formCheck', '^tables\[') . '>';
	echo '<th>' . lang('Table');
	echo '<td>' . lang('Rows');
	echo "<tbody>\n";

	foreach (table_status() as $table => $row) {
		$name = adminer()->tableName($row);
		if ($name != "") {
			echo '<tr><td class="hover">' . checkbox("tables[]", $table, in_array($table, (array) $_POST["tables"], true));
			echo "<th><a href='" . h(ME) . 'select=' . urlencode($table) . "'>$name</a>";
			echo "<td align='right'><a href='" . h(ME . "edit=") . urlencode($table) . "'>" . format_rows($row) . "</a>";
		}
	}

	echo "</table>\n";
	echo "</div>\n";
	echo "</form>\n";
	echo script("tableCheck();");
	adminer()->pluginsLinks();
}
