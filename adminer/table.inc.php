<?php
$TABLE = $_GET["table"];
$result = $dbh->query("SHOW FULL COLUMNS FROM " . idf_escape($TABLE));
if (!$result) {
	$error = h($dbh->error);
}
$table_status = ($result ? table_status($TABLE) : array());
$is_view = !isset($table_status["Rows"]);

page_header(($result && $is_view ? lang('View') : lang('Table')) . ": " . h($TABLE), $error);

if ($result) {
	echo "<table cellspacing='0'>\n";
	echo "<thead><tr><th>" . lang('Column') . "<td>" . lang('Type') . "<td>" . lang('Comment') . "</thead>\n";
	while ($row = $result->fetch_assoc()) {
		echo "<tr><th>" . h($row["Field"]);
		echo "<td>" . h($row["Type"]) . ($row["Null"] == "YES" ? " <i>NULL</i>" : "");
		echo "<td>" . nbsp($row["Comment"]);
		echo "\n";
	}
	echo "</table>\n";
	
	echo "<p>";
	if ($is_view) {
		echo '<a href="' . h(ME) . 'view=' . urlencode($TABLE) . '">' . lang('Alter view') . '</a>';
	} else {
		echo '<a href="' . h(ME) . 'create=' . urlencode($TABLE) . '">' . lang('Alter table') . '</a>';
	}
	echo ' <a href="' . h(ME) . 'select=' . urlencode($TABLE) . '">' . lang('Select table') . '</a>';
	echo ' <a href="' . h(ME) . 'edit=' . urlencode($TABLE) . '">' . lang('New item') . '</a>';
	
	if (!$is_view) {
		echo "<h3>" . lang('Indexes') . "</h3>\n";
		$indexes = indexes($TABLE);
		if ($indexes) {
			echo "<table cellspacing='0'>\n";
			foreach ($indexes as $index) {
				ksort($index["columns"]); // enforce correct columns order
				$print = array();
				foreach ($index["columns"] as $key => $val) {
					$print[] = "<i>" . h($val) . "</i>" . ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "");
				}
				echo "<tr><th>$index[type]<td>" . implode(", ", $print) . "\n";
			}
			echo "</table>\n";
		}
		echo '<p><a href="' . h(ME) . 'indexes=' . urlencode($TABLE) . '">' . lang('Alter indexes') . "</a>\n";
		
		if ($table_status["Engine"] == "InnoDB") {
			echo "<h3>" . lang('Foreign keys') . "</h3>\n";
			$foreign_keys = foreign_keys($TABLE);
			if ($foreign_keys) {
				echo "<table cellspacing='0'>\n";
				foreach ($foreign_keys as $name => $foreign_key) {
					$link = (strlen($foreign_key["db"]) ? "<strong>" . h($foreign_key["db"]) . "</strong>." : "") . h($foreign_key["table"]);
					echo "<tr>";
					echo "<th><i>" . implode("</i>, <i>", array_map('h', $foreign_key["source"])) . "</i>";
					echo "<td><a href='" . h(strlen($foreign_key["db"]) ? preg_replace('~db=[^&]*~', "db=" . urlencode($foreign_key["db"]), ME) : ME) . "table=" . urlencode($foreign_key["table"]) . "'>$link</a>";
					echo "(<em>" . implode("</em>, <em>", array_map('h', $foreign_key["target"])) . "</em>)";
					echo "<td>" . (!strlen($foreign_key["db"]) ? '<a href="' . h(ME . 'foreign=' . urlencode($TABLE) . '&name=' . urlencode($name)) . '">' . lang('Alter') . '</a>' : '&nbsp;');
				}
				echo "</table>\n";
			}
			echo '<p><a href="' . h(ME) . 'foreign=' . urlencode($TABLE) . '">' . lang('Add foreign key') . "</a>\n";
		}
		
		if ($dbh->server_info >= 5) {
			echo "<h3>" . lang('Triggers') . "</h3>\n";
			$result = $dbh->query("SHOW TRIGGERS LIKE " . $dbh->quote(addcslashes($TABLE, "%_")));
			if ($result->num_rows) {
				echo "<table cellspacing='0'>\n";
				while ($row = $result->fetch_assoc()) {
					echo "<tr valign='top'><td>$row[Timing]<td>$row[Event]<th>" . h($row["Trigger"]) . "<td><a href='" . h(ME . 'trigger=' . urlencode($TABLE) . '&name=' . urlencode($row["Trigger"])) . "'>" . lang('Alter') . "</a>\n";
				}
				echo "</table>\n";
			}
			echo '<p><a href="' . h(ME) . 'trigger=' . urlencode($TABLE) . '">' . lang('Add trigger') . "</a>\n";
		}
	}
}
