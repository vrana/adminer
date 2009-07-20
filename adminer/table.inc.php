<?php
$result = $dbh->query("SHOW COLUMNS FROM " . idf_escape($_GET["table"]));
if (!$result) {
	$error = htmlspecialchars($dbh->error);
}

page_header(lang('Table') . ": " . htmlspecialchars($_GET["table"]), $error);

if ($result) {
	$table_status = table_status($_GET["table"]);
	$auto_increment_only = true;
	echo "<table cellspacing='0'>\n";
	while ($row = $result->fetch_assoc()) {
		if (!$row["auto_increment"]) {
			$auto_increment_only = false;
		}
		echo "<tr><th>" . htmlspecialchars($row["Field"]) . "<td>" . htmlspecialchars($row["Type"]) . ($row["Null"] == "YES" ? " <i>NULL</i>" : "") . "\n";
	}
	echo "</table>\n";
	$result->free();
	
	echo "<p>";
	echo '<a href="' . htmlspecialchars($SELF) . 'create=' . urlencode($_GET["table"]) . '">' . lang('Alter table') . '</a>';
	echo ($auto_increment_only ? '' : ' <a href="' . htmlspecialchars($SELF) . 'default=' . urlencode($_GET["table"]) . '">' . lang('Default values') . '</a>');
	echo ' <a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($_GET["table"]) . '">' . lang('Select table') . '</a>';
	echo ' <a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET["table"]) . '">' . lang('New item') . '</a>';
	
	echo "<h3>" . lang('Indexes') . "</h3>\n";
	$indexes = indexes($_GET["table"]);
	if ($indexes) {
		echo "<table cellspacing='0'>\n";
		foreach ($indexes as $index) {
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . htmlspecialchars($val) . "</i>" . ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "");
			}
			echo "<tr><th>$index[type]<td>" . implode(", ", $print) . "\n";
		}
		echo "</table>\n";
	}
	echo '<p><a href="' . htmlspecialchars($SELF) . 'indexes=' . urlencode($_GET["table"]) . '">' . lang('Alter indexes') . "</a>\n";
	
	if ($table_status["Engine"] == "InnoDB") {
		echo "<h3>" . lang('Foreign keys') . "</h3>\n";
		$foreign_keys = foreign_keys($_GET["table"]);
		if ($foreign_keys) {
			echo "<table cellspacing='0'>\n";
			foreach ($foreign_keys as $name => $foreign_key) {
				$link = (strlen($foreign_key["db"]) ? "<strong>" . htmlspecialchars($foreign_key["db"]) . "</strong>." : "") . htmlspecialchars($foreign_key["table"]);
				echo "<tr>";
				echo "<th><i>" . implode("</i>, <i>", array_map('htmlspecialchars', $foreign_key["source"])) . "</i>";
				echo '<td><a href="' . htmlspecialchars(strlen($foreign_key["db"]) ? preg_replace('~db=[^&]*~', "db=" . urlencode($foreign_key["db"]), $SELF) : $SELF) . "table=" . urlencode($foreign_key["table"]) . "\">$link</a>";
				echo "(<em>" . implode("</em>, <em>", array_map('htmlspecialchars', $foreign_key["target"])) . "</em>)";
				echo "<td>" . (!strlen($foreign_key["db"]) ? '<a href="' . htmlspecialchars($SELF) . 'foreign=' . urlencode($_GET["table"]) . '&amp;name=' . urlencode($name) . '">' . lang('Alter') . '</a>' : '&nbsp;');
			}
			echo "</table>\n";
		}
		echo '<p><a href="' . htmlspecialchars($SELF) . 'foreign=' . urlencode($_GET["table"]) . '">' . lang('Add foreign key') . "</a>\n";
	}
}

if ($dbh->server_info >= 5) {
	echo "<h3>" . lang('Triggers') . "</h3>\n";
	$result = $dbh->query("SHOW TRIGGERS LIKE " . $dbh->quote(addcslashes($_GET["table"], "%_")));
	if ($result->num_rows) {
		echo "<table cellspacing='0'>\n";
		while ($row = $result->fetch_assoc()) {
			echo "<tr valign='top'><td>$row[Timing]<td>$row[Event]<th>" . htmlspecialchars($row["Trigger"]) . "<td><a href=\"" . htmlspecialchars($SELF) . 'trigger=' . urlencode($_GET["table"]) . '&amp;name=' . urlencode($row["Trigger"]) . '">' . lang('Alter') . "</a>\n";
		}
		echo "</table>\n";
	}
	$result->free();
	echo '<p><a href="' . htmlspecialchars($SELF) . 'trigger=' . urlencode($_GET["table"]) . '">' . lang('Add trigger') . "</a>\n";
}
