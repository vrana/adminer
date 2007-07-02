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

$indexes = indexes($_GET["table"]);
if ($indexes) {
	echo "<h3>" . lang('Indexes') . "</h3>\n";
	echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
	foreach ($indexes as $type => $index) {
		foreach ($index as $columns) {
			sort($columns);
			echo "<tr><td>$type</td><td><i>" . implode("</i>, <i>", $columns) . "</i></td></tr>\n";
		}
	}
	echo "</table>\n";
}

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
