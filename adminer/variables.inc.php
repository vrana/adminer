<?php
$status = isset($_GET["status"]);
page_header($status ? lang('Status') : lang('Variables'));

echo "<table cellspacing='0'>\n";
foreach (($status ? show_status() : show_variables()) as $key => $val) {
	echo "<tr>";
	echo "<th><code class='jush-" . $driver . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
	echo "<td>" . nbsp($val);
}
echo "</table>\n";
