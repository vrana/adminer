<?php
if ($_POST && !$error) {
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
<table cellspacing="0" onclick="tableClick(event);" class="nowrap">
<?php
$i = -1;
foreach (get_rows("SHOW FULL PROCESSLIST") as $i => $row) {
	if (!$i) {
		echo "<thead><tr lang='en'><th>&nbsp;<th>" . implode("<th>", array_keys($row)) . "</thead>\n";
	}
	echo "<tr" . odd() . "><td>" . checkbox("kill[]", $row["Id"], 0);
	foreach ($row as $key => $val) {
		echo "<td>" . ($key == "Info" && $val != "" ? "<code class='jush-$jush'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang('Edit') . '</a>' : nbsp($val));
	}
	echo "\n";
}
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Kill'); ?>">
<?php echo ($i + 1) . "/" . $connection->result("SELECT @@max_connections"); ?>
</form>
