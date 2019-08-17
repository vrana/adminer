<?php
$status = isset($_GET["status"]);
page_header($status ? lang('Status') : lang('Variables'));

$variables = ($status ? show_status() : show_variables());
if (!$variables) {
	echo "<p class='message'>" . lang('No rows.') . "\n";
} else {
	# some variable values like optimizer_switch on Mariadb can contain nasty long strings without any whitespace 
	# see https://developer.mozilla.org/en-US/docs/Web/CSS/word-break
	echo '<style>table.info td {
	word-break:break-word; /* deprecated, all browsers */
	overflow-wrap:anywhere; /* CSS3 WD, only firefox in 2019 */
	}</style>';
	echo "<table class='info'>\n";
	foreach ($variables as $key => $val) {
		echo "<tr>";
		echo "<th><code class='jush-" . $jush . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
		echo "<td>" . h($val);
	}
	echo "</table>\n";
}
