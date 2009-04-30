<?php
if ($_POST && !$error) {
	$killed = 0;
	foreach ((array) $_POST["kill"] as $val) {
		if (queries("KILL " . intval($val))) {
			$killed++;
		}
	}
	query_redirect(queries(), $SELF . "processlist=", lang('%d process(es) has been killed.', $killed), $killed || !$_POST["kill"], false, !$killed && $_POST["kill"]);
}
page_header(lang('Process list'), $error);
?>

<form action="" method="post">
<table border="1" cellspacing="0" cellpadding="2">
<?php
$result = $mysql->query("SHOW PROCESSLIST");
for ($i=0; $row = $result->fetch_assoc(); $i++) {
	if (!$i) {
		echo "<thead><tr lang='en'><th>&nbsp;</th><th>" . implode("</th><th>", array_keys($row)) . "</th></tr></thead>\n";
	}
	echo "<tr" . odd() . "><td><input type='checkbox' name='kill[]' value='$row[Id]' /></td><td>" . implode("</td><td>", $row) . "</td></tr>\n";
}
$result->free();
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Kill'); ?>" />
</p>
</form>
