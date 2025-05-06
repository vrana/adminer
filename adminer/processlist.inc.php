<?php
namespace Adminer;

if (support("kill")) {
	if ($_POST && !$error) {
		$killed = 0;
		foreach ((array) $_POST["kill"] as $val) {
			if (adminer()->killProcess($val)) {
				$killed++;
			}
		}
		queries_redirect(ME . "processlist=", lang('%d process(es) have been killed.', $killed), $killed || !$_POST["kill"]);
	}
}

page_header(lang('Process list'), $error);
?>

<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds">
<?php
echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
// HTML valid because there is always at least one process
$i = -1;
foreach (adminer()->processList() as $i => $row) {
	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>" : "");
		foreach ($row as $key => $val) {
			echo "<th>$key" . doc_link(array(
				'sql' => "show-processlist.html#processlist_" . strtolower($key),
				'pgsql' => "monitoring-stats.html#PG-STAT-ACTIVITY-VIEW",
				'oracle' => "REFRN30223",
			));
		}
		echo "</thead>\n";
	}
	echo "<tr>" . (support("kill") ? "<td>" . checkbox("kill[]", $row[JUSH == "sql" ? "Id" : "pid"], 0) : "");
	foreach ($row as $key => $val) {
		echo "<td>" . (
			(JUSH == "sql" && $key == "Info" && preg_match("~Query|Killed~", $row["Command"]) && $val != "") ||
			(JUSH == "pgsql" && $key == "current_query" && $val != "<IDLE>") ||
			(JUSH == "oracle" && $key == "sql_text" && $val != "")
			? "<code class='jush-" . JUSH . "'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang('Clone') . '</a>'
			: h($val)
		);
	}
	echo "\n";
}
?>
</table>
</div>
<p>
<?php
if (support("kill")) {
	echo ($i + 1) . "/" . lang('%d in total', max_connections());
	echo "<p><input type='submit' value='" . lang('Kill') . "'>\n";
}
echo input_token();
?>
</form>
<?php echo script("tableCheck();"); ?>
