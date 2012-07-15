<?php
$TABLE = $_GET["table"];
$fields = fields($TABLE);
if (!$fields) {
	$error = error();
}
$table_status = ($fields ? table_status($TABLE) : array());

page_header(($fields && is_view($table_status) ? lang('View') : lang('Table')) . ": " . h($TABLE), $error);
$adminer->selectLinks($table_status);
$comment = $table_status["Comment"];
if ($comment != "") {
	echo "<p>" . lang('Comment') . ": " . h($comment) . "\n";
}

if ($fields) {
	echo "<table cellspacing='0'>\n";
	echo "<thead><tr><th>" . lang('Column') . "<td>" . lang('Type') . (support("comment") ? "<td>" . lang('Comment') : "") . "</thead>\n";
	foreach ($fields as $field) {
		echo "<tr" . odd() . "><th>" . h($field["field"]);
		echo "<td title='" . h($field["collation"]) . "'>" . h($field["full_type"]) . ($field["null"] ? " <i>NULL</i>" : "") . ($field["auto_increment"] ? " <i>" . lang('Auto Increment') . "</i>" : "");
		echo (isset($field["default"]) ? " [<b>" . h($field["default"]) . "</b>]" : "");
		echo (support("comment") ? "<td>" . nbsp($field["comment"]) : "");
		echo "\n";
	}
	echo "</table>\n";
	
	if (!is_view($table_status)) {
		echo "<h3>" . lang('Indexes') . "</h3>\n";
		$indexes = indexes($TABLE);
		if ($indexes) {
			echo "<table cellspacing='0'>\n";
			foreach ($indexes as $name => $index) {
				ksort($index["columns"]); // enforce correct columns order
				$print = array();
				foreach ($index["columns"] as $key => $val) {
					$print[] = "<i>" . h($val) . "</i>" . ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "");
				}
				echo "<tr title='" . h($name) . "'><th>$index[type]<td>" . implode(", ", $print) . "\n";
			}
			echo "</table>\n";
		}
		echo '<p><a href="' . h(ME) . 'indexes=' . urlencode($TABLE) . '">' . lang('Alter indexes') . "</a>\n";
		
		if (fk_support($table_status)) {
			echo "<h3>" . lang('Foreign keys') . "</h3>\n";
			$foreign_keys = foreign_keys($TABLE);
			if ($foreign_keys) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Source') . "<td>" . lang('Target') . "<td>" . lang('ON DELETE') . "<td>" . lang('ON UPDATE') . ($jush != "sqlite" ? "<td>&nbsp;" : "") . "</thead>\n";
				foreach ($foreign_keys as $name => $foreign_key) {
					echo "<tr title='" . h($name) . "'>";
					echo "<th><i>" . implode("</i>, <i>", array_map('h', $foreign_key["source"])) . "</i>";
					echo "<td><a href='" . h($foreign_key["db"] != "" ? preg_replace('~db=[^&]*~', "db=" . urlencode($foreign_key["db"]), ME) : ($foreign_key["ns"] != "" ? preg_replace('~ns=[^&]*~', "ns=" . urlencode($foreign_key["ns"]), ME) : ME)) . "table=" . urlencode($foreign_key["table"]) . "'>"
						. ($foreign_key["db"] != "" ? "<b>" . h($foreign_key["db"]) . "</b>." : "") . ($foreign_key["ns"] != "" ? "<b>" . h($foreign_key["ns"]) . "</b>." : "") . h($foreign_key["table"])
						. "</a>"
					;
					echo "(<i>" . implode("</i>, <i>", array_map('h', $foreign_key["target"])) . "</i>)";
					echo "<td>" . nbsp($foreign_key["on_delete"]) . "\n";
					echo "<td>" . nbsp($foreign_key["on_update"]) . "\n";
					echo ($jush == "sqlite" ? "" : '<td><a href="' . h(ME . 'foreign=' . urlencode($TABLE) . '&name=' . urlencode($name)) . '">' . lang('Alter') . '</a>');
				}
				echo "</table>\n";
			}
			if ($jush != "sqlite") {
				echo '<p><a href="' . h(ME) . 'foreign=' . urlencode($TABLE) . '">' . lang('Add foreign key') . "</a>\n";
			}
		}
		
		if (support("trigger")) {
			echo "<h3>" . lang('Triggers') . "</h3>\n";
			$triggers = triggers($TABLE);
			if ($triggers) {
				echo "<table cellspacing='0'>\n";
				foreach ($triggers as $key => $val) {
					echo "<tr valign='top'><td>$val[0]<td>$val[1]<th>" . h($key) . "<td><a href='" . h(ME . 'trigger=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . lang('Alter') . "</a>\n";
				}
				echo "</table>\n";
			}
			echo '<p><a href="' . h(ME) . 'trigger=' . urlencode($TABLE) . '">' . lang('Add trigger') . "</a>\n";
		}
	}
}
