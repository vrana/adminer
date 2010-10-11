<?php
$TABLE = $_GET["view"];
$dropped = false;
if ($_POST && !$error) {
	$dropped = drop_create(
		"DROP VIEW " . table($TABLE),
		"CREATE VIEW " . table($_POST["name"]) . " AS\n$_POST[select]",
		($_POST["drop"] ? substr(ME, 0, -1) : ME . "table=" . urlencode($_POST["name"])),
		lang('View has been dropped.'),
		lang('View has been altered.'),
		lang('View has been created.'),
		$TABLE
	);
}

page_header(($TABLE != "" ? lang('Alter view') : lang('Create view')), $error, array("table" => $TABLE), $TABLE);

$row = array();
if ($_POST) {
	$row = $_POST;
} elseif ($TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
}
?>

<form action="" method="post">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64">
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<?php if ($dropped) { // old view was dropped but new wasn't created ?><input type="hidden" name="dropped" value="1"><?php } ?>
<input type="submit" value="<?php echo lang('Save'); ?>">
</form>
