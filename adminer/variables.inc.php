<?php
$status = isset($_GET["status"]);
page_header($status ? lang('Status') : lang('Variables'));

$result = $connection->query($status ? "SHOW STATUS" : "SHOW VARIABLES");
echo "<table cellspacing='0'>\n";
while ($row = $result->fetch_assoc()) {
	echo "<tr>";
	echo "<th><code class='jush-" . ($status ? "sqlstatus" : "sqlset") . "'>" . h($row["Variable_name"]) . "</code>";
	echo "<td>" . nbsp($row["Value"]);
}
echo "</table>\n";
