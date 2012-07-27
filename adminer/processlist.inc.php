<?php
if (support("kill") && $_POST && !$error) {
	$killed = 0;
	foreach ((array) $_POST["kill"] as $val) {
		if (queries("KILL " . (+$val))) {
			$killed++;
		}
	}
	queries_redirect(ME . "processlist=", lang('%d process(es) have been killed.', $killed), $killed || !$_POST["kill"]);
}

page_header(lang('Process list'), $error);
?>

<form action="" method="post">
<table cellspacing="0" onclick="tableClick(event);" class="nowrap checkable">
<?php
// HTML valid because there is always at least one process
$i = -1;
foreach (process_list() as $i => $row) {
	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>&nbsp;" : "") . "<th>" . implode("<th>", array_keys($row)) . "</thead>\n";
	}
	echo "<tr" . odd() . ">" . (support("kill") ? "<td>" . checkbox("kill[]", $row["Id"], 0) : "");
	foreach ($row as $key => $val) {
		echo "<td>" . (
			($jush == "sql" && $key == "Info" && ereg("Query|Killed", $row["Command"]) && $val != "") ||
			($jush == "pgsql" && $key == "current_query" && $val != "<IDLE>") ||
			($jush == "oracle" && $key == "sql_text" && $val != "")
			? "<code class='jush-$jush'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang('Edit') . '</a>'
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
	echo ($i + 1) . "/" . lang('%d in total', $connection->result("SELECT @@max_connections"));
	echo "<p><input type='submit' value='" . lang('Kill') . "'>\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
