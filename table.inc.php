<?php
page_header(lang('Table') . ": " . htmlspecialchars($_GET["table"]));
echo "<h2>" . lang('Table') . ": " . htmlspecialchars($_GET["table"]) . "</h2>\n";

$result = mysql_query("SHOW FULL COLUMNS FROM " . idf_escape($_GET["table"]));
echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
while ($row = mysql_fetch_assoc($result)) {
	echo "<tr><th>" . htmlspecialchars($row["Field"]) . "</th><td>$row[Type]" . ($row["Null"] == "NO" ? " NOT NULL" : "") . "</td></tr>\n";
}
echo "</table>\n";
mysql_free_result($result);
echo '<p><a href="' . htmlspecialchars($SELF) . 'create=' . urlencode($_GET["table"]) . '">' . lang('Alter table') . "</a></p>\n";

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

$result = mysql_query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . mysql_real_escape_string($_GET["db"]) . "' AND TABLE_NAME = '" . mysql_real_escape_string($_GET["table"]) . "' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY ORDINAL_POSITION");
if (mysql_num_rows($result)) {
	$foreign_keys = array();
	while ($row = mysql_fetch_assoc($result)) {
		$foreign_keys[$row["CONSTRAINT_NAME"]][0] = $row["REFERENCED_TABLE_SCHEMA"];
		$foreign_keys[$row["CONSTRAINT_NAME"]][1] = $row["REFERENCED_TABLE_NAME"];
		$foreign_keys[$row["CONSTRAINT_NAME"]][2][] = htmlspecialchars($row["COLUMN_NAME"]);
		$foreign_keys[$row["CONSTRAINT_NAME"]][3][] = htmlspecialchars($row["REFERENCED_COLUMN_NAME"]);
	}
	echo "<h3>" . lang('Foreign keys') . "</h3>\n";
	echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
	foreach ($foreign_keys as $foreign_key) {
		echo "<tr><td><em>" . implode("</em>, <em>", $foreign_key[2]) . "</em></td><td>" . (strlen($foreign_key[0]) && $foreign_key[0] !== $_GET["db"] ? "<strong>" . htmlspecialchars($foreign_key[0]) . "</strong>." : "") . "<strong>" . htmlspecialchars($foreign_key[1]) . "</strong>(<em>" . implode("</em>, <em>", $foreign_key[3]) . "</em>)</td></tr>\n";
	}
	echo "</table>\n";
}
mysql_free_result($result);

$result = mysql_query("SHOW TRIGGERS LIKE '" . mysql_real_escape_string($_GET["table"]) . "'");
if (mysql_num_rows($result)) {
	echo "<h3>" . lang('Triggers') . "</h3>\n";
	echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
	while ($row = mysql_fetch_assoc($result)) {
		echo "<tr><th>$row[Timing]</th><th>$row[Event]</th><td>" . htmlspecialchars($row["Statement"]) . "</td></tr>\n";
	}
	echo "</table>\n";
}
mysql_free_result($result);
