<?php
page_header(lang('Variables'));

echo "<table cellspacing='0'>\n";
$result = $dbh->query("SHOW VARIABLES");
while ($row = $result->fetch_assoc()) {
	echo "<tr>";
	echo "<th><code class='jush-sqlset'>" . h($row["Variable_name"]) . "</code>";
	echo "<td>" . (strlen(trim($row["Value"])) ? h($row["Value"]) : "&nbsp;");
}
$result->free();
echo "</table>\n";
