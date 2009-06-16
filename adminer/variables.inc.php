<?php
page_header(lang('Variables'));

echo "<table cellspacing='0'>\n";
$result = $dbh->query("SHOW VARIABLES");
while ($row = $result->fetch_assoc()) {
	echo "<tr>";
	echo "<th><code class='jush-sql_set'>" . htmlspecialchars($row["Variable_name"]) . "</code></th>";
	echo "<td>" . (strlen(trim($row["Value"])) ? htmlspecialchars($row["Value"]) : "&nbsp;") . "</td>";
	echo "</tr>\n";
}
$result->free();
echo "</table>\n";
