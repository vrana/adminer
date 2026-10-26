<?php
namespace Adminer;

page_header(lang('Server'), "", false);

if (adminer()->homepage()) {
	$query = ($_POST ? $_POST["query"] : $_GET["query"]); // GET is used by the Ctrl+K box
	$filter = $_GET["filter"]; // sent by the Ctrl+K box without JavaScript
	echo "<form method='post'>\n";
	echo "<p>" . lang('Search data in tables') . ": <input type='search' name='query' value='" . h($query != "" ? $query : $filter) . "'> <input type='submit' value='" . lang('Search') . "'>\n";
	if ($query != "") {
		search_tables($query);
	}
	echo "<div class='scrollable'>\n";
	echo "<table class='nowrap checkable odds'" . on('click', 'tableClick') . on('dblclick', 'tableClick') . ">\n";
	echo '<thead><tr>';
	echo '<td class="hover"><input id="check-all" type="checkbox" class="jsonly"' . on('click', 'formCheck', '^tables\[') . '>';
	echo '<th>' . lang('Table');
	echo '<th>' . lang('Rows');
	echo "<tbody>\n";

	foreach (table_status() as $table => $row) {
		$name = adminer()->tableName($row);
		if ($name != "" && ($filter == "" || stripos("$table", $filter) !== false || stripos($name, h($filter)) !== false)) {
			echo '<tr><td class="hover">' . checkbox("tables[]", $table, in_array($table, (array) $_POST["tables"], true));
			echo "<th><a href='" . h(ME) . 'select=' . url_escape($table) . "'>$name</a>";
			echo "<td align='right'><a href='" . h(ME . "edit=") . url_escape($table) . "'>" . format_status($row, "Rows") . "</a>";
		}
	}

	echo "</table>\n";
	echo "</div>\n";
	echo "</form>\n";
	echo script("tableCheck();");
	adminer()->pluginsLinks();
}
