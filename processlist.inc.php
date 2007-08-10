<?php
if ($_POST && !$error) {
	$killed = 0;
	foreach ((array) $_POST["kill"] as $val) {
		if ($mysql->query("KILL " . intval($val))) {
			$killed++;
		}
	}
	if ($killed || !$_POST["kill"]) {
		redirect($SELF . "processlist=", lang('%d process(es) has been killed.', $killed));
	}
	$error = $mysql->error;
}

page_header(lang('Process list'));

if ($_POST) {
	echo "<p class='error'>" . lang('Unable to kill process') . ": " . htmlspecialchars($error) . "</p>\n";
}
?>

<form action="" method="post">
<table border="1" cellspacing="0" cellpadding="2">
<?php
$result = $mysql->query("SHOW PROCESSLIST");
for ($i=0; $row = $result->fetch_assoc(); $i++) {
	if (!$i) {
		echo "<thead lang='en'><tr><th>&nbsp;</th><th>" . implode("</th><th>", array_keys($row)) . "</th></tr></thead>\n";
	}
	echo "<tr><td><input type='checkbox' name='kill[]' value='$row[Id]' /></td><td>" . implode("</td><td>", $row) . "</td></tr>\n";
}
$result->free();
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Kill'); ?>" />
</p>
</form>
