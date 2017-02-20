<?php
if (support("kill") && $_POST && !$error) {
	$killed = 0;
	foreach ((array) $_POST["kill"] as $val) {
		if (kill_process($val)) {
			$killed++;
		}
	}
	queries_redirect(ME . "locklist=", lang('%d process(es) have been killed.', $killed), $killed || !$_POST["kill"]);
}

page_header(lang('Locks list'), $error);
?>

<form action="" method="post">
<table cellspacing="0" onclick="tableClick(event);" ondblclick="tableClick(event, true);" class="nowrap checkable">
<?php
$i = -1;
foreach (lock_list() as $i => $row) {

	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>&nbsp;" : "");
		foreach ($row as $key => $val) {
			echo "<th>$key" . doc_link(array(
//				'sql' => "show-processlist.html#processlist_" . strtolower($key),
//				'pgsql' => "monitoring-stats.html#PG-STAT-ACTIVITY-VIEW",
//				'oracle' => "../b14237/dynviews_2088.htm",
			));
		}
		echo "</thead>\n";
	}
	echo "<tr" . odd() . ">" . (support("kill") ? "<td>" . checkbox("kill[]", $row[$jush == "sql" ? "Id" : "pid"], 0) : "");
	foreach ($row as $key => $val) {
		echo "<td>" . (
			($jush == "sql" && $key == "Info" && preg_match("~Query|Killed~", $row["Command"]) && $val != "") ||
			($jush == "pgsql" && $key == "current_query" && $val != "<IDLE>") ||
			($jush == "oracle" && $key == "sql_text" && $val != "")
			? "<code class='jush-$jush'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang('Clone') . '</a>'
			: nbsp($val)
		);
	}
	echo "\n";
}
?>
</table>
<script type='text/javascript'>tableCheck();</script>
<p>
<?php
if (support("kill")) {
	echo ($i + 1) . "/" . lang('%d in total', max_connections());
	echo "<p><input type='submit' value='" . lang('Kill') . "'>\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
