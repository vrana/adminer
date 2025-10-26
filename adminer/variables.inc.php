<?php
namespace Adminer;

$status = isset($_GET["status"]);
page_header($status ? lang('Status') : lang('Variables'));

$variables = ($status ? adminer()->showStatus() : adminer()->showVariables());
if (!$variables) {
	echo "<p class='message'>" . lang('No rows.') . "\n";
} else {
	echo "<table>\n";
	foreach ($variables as $row) {
		echo "<tr>";
		$key = array_shift($row);
		echo "<th><code class='jush-" . JUSH . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
		foreach ($row as $val) {
			echo "<td>" . nl_br(h($val));
		}
	}
	echo "</table>\n";
}
