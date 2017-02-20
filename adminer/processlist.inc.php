<?php
if (support("kill") && $_POST && !$error) {
	$killed = 0;
	foreach ((array) $_POST["kill"] as $val) {
		if (kill_process($val)) {
			$killed++;
		}
	}
	queries_redirect(ME . "processlist=", lang('%d process(es) have been killed.', $killed), $killed || !$_POST["kill"]);
}

if (isset($_GET["autorefresh"]) && !$error) {
	header("Refresh: " . intval($_GET["autorefresh"]));
}
page_header(lang('Process list'), $error);
?>

<form action="" method="post">
<table cellspacing="0" onclick="tableClick(event);" ondblclick="tableClick(event, true);" class="<?php if (!isset($_GET["showfull"])){?>nowrap <?php } ?>checkable">
<?php
// HTML valid because there is always at least one process
$i = -1;
foreach (process_list() as $i => $row) {

	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>&nbsp;" : "");
		foreach ($row as $key => $val) {
			echo "<th>$key" . doc_link(array(
				'sql' => "show-processlist.html#processlist_" . strtolower($key),
				'pgsql' => "monitoring-stats.html#PG-STAT-ACTIVITY-VIEW",
				'oracle' => "../b14237/dynviews_2088.htm",
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
			? "<code class='jush-$jush'>" . (isset($_GET["showfull"]) ? h($val) . "</code>" : shorten_utf8($val, 100, "</code>")) . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang('Clone') . '</a>'
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
if (isset($_GET["showfull"])) {
	echo "<p><a href='" . h(ME . "processlist=") . "'>" . lang('Hide full') . "</a>";
} else {
	echo "<p><a href='" . h(ME . "processlist=&showfull=") . "'>" . lang('Show full') . "</a>";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php if (isset($_GET["autorefresh"]) && !$error) { ?>
<a href="<?php echo remove_from_uri("autorefresh"); ?>"><?php echo lang('Stop auto refresh'); ?></a>
<?php } else { ?>
<form action="">
<?php hidden_fields_get(); ?>
<input type="hidden" value="" name="processlist">
<?php if (isset($_GET["showfull"])) { ?>
<input type="hidden" name="showfull" value="">
<?php } ?>
<input type="text" name="autorefresh" value="5">
<input type="submit" type="submit" value="<?php echo lang('Auto refresh'); ?>">
</form>
<?php } ?>
