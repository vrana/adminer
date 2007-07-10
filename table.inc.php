<?php
page_header(lang('Table') . ": " . htmlspecialchars($_GET["table"]));

$result = $mysql->query("SHOW COLUMNS FROM " . idf_escape($_GET["table"]));
if (!$result) {
	echo "<p class='error'>" . lang('Unable to show the table definition') . ": " . $mysql->error . ".</p>\n";
} else {
	$auto_increment_only = true;
	echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
	while ($row = $result->fetch_assoc()) {
		if (!$row["auto_increment"]) {
			$auto_increment_only = false;
		}
		echo "<tr><th>" . htmlspecialchars($row["Field"]) . "</th><td>$row[Type]" . ($row["Null"] == "YES" ? " <i>NULL</i>" : "") . "</td></tr>\n";
	}
	echo "</table>\n";
	$result->free();
	
	echo "<p>";
	echo '<a href="' . htmlspecialchars($SELF) . 'create=' . urlencode($_GET["table"]) . '">' . lang('Alter table') . '</a>';
	echo ($auto_increment_only ? '' : ' <a href="' . htmlspecialchars($SELF) . 'default=' . urlencode($_GET["table"]) . '">' . lang('Default values') . '</a>');
	echo "</p>\n";
	
	echo "<h3>" . lang('Indexes') . "</h3>\n";
	$indexes = indexes($_GET["table"]);
	if ($indexes) {
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		foreach ($indexes as $index) {
			ksort($index["columns"]);
			echo "<tr><td>$index[type]</td><td><i>" . implode("</i>, <i>", $index["columns"]) . "</i></td></tr>\n";
		}
		echo "</table>\n";
	}
	echo '<p><a href="' . htmlspecialchars($SELF) . 'indexes=' . urlencode($_GET["table"]) . '">' . lang('Alter indexes') . "</a></p>\n";
	
	$foreign_keys = foreign_keys($_GET["table"]);
	if ($foreign_keys) {
		echo "<h3>" . lang('Foreign keys') . "</h3>\n";
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		foreach ($foreign_keys as $foreign_key) {
			echo "<tr><td><i>" . implode("</i>, <i>", $foreign_key[2]) . "</i></td><td>" . (strlen($foreign_key[0]) && $foreign_key[0] !== $_GET["db"] ? "<strong>" . htmlspecialchars($foreign_key[0]) . "</strong>." : "") . htmlspecialchars($foreign_key[1]) . "(<em>" . implode("</em>, <em>", $foreign_key[3]) . "</em>)</td></tr>\n";
		}
		echo "</table>\n";
	}
}

if ($mysql->server_info >= 5) {
	$result = $mysql->query("SHOW TRIGGERS LIKE '" . $mysql->real_escape_string($_GET["table"]) . "'");
	if ($result->num_rows) {
		echo "<h3>" . lang('Triggers') . "</h3>\n";
		echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
		while ($row = $result->fetch_assoc()) {
			echo "<tr valign='top'><th>$row[Timing]</th><th>$row[Event]</th><td><pre class='jush-sql'>" . htmlspecialchars($row["Statement"]) . "</pre></td></tr>\n";
		}
		echo "</table>\n";
	}
	$result->free();
}
