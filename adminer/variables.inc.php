<?php
page_header(lang('Variables'));

echo "<table cellspacing='0'>\n";
$result = $connection->query("SHOW VARIABLES");
while ($row = $result->fetch_assoc()) {
	echo "<tr>";
	echo "<th><code class='jush-sqlset'>" . h($row["Variable_name"]) . "</code>";
	echo "<td>" . nbsp($row["Value"]);
}
echo "</table>\n";
