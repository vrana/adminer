<?php
if ($_POST && !$error) {
	$killed = 0;
	foreach ((array) $_POST["kill"] as $val) {
		if (queries("KILL " . ereg_replace("[^0-9]+", "", $val))) {
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
$result = $connection->query("SHOW FULL PROCESSLIST");
for ($i=0; $row = $result->fetch_assoc(); $i++) {
	if (!$i) {
		echo "<thead><tr lang='en'><th>&nbsp;<th>" . implode("<th>", array_keys($row)) . "</thead>\n";
	}
	echo "<tr" . odd() . "><td>" . checkbox("kill[]", $row["Id"], 0) . "<td>" . implode("<td>", array_map('nbsp', $row)) . "\n";
}
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Kill'); ?>">
</form>
