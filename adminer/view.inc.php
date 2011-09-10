<?php
$TABLE = $_GET["view"];
$dropped = false;
if ($_POST && !$error) {
	$name = trim($_POST["name"]);
	$dropped = drop_create(
		"DROP VIEW " . table($TABLE),
		"CREATE VIEW " . table($name) . " AS\n$_POST[select]",
		($_POST["drop"] ? substr(ME, 0, -1) : ME . "table=" . urlencode($name)),
		lang('View has been dropped.'),
		lang('View has been altered.'),
		lang('View has been created.'),
		$TABLE
	);
}

page_header(($TABLE != "" ? lang('Alter view') : lang('Create view')), $error, array("table" => $TABLE), $TABLE);

$row = $_POST;
if (!$row && $TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
}
?>

<form action="" method="post">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64">
<p><?php textarea("select", $row["select"]); ?>
<p>
<?php if ($dropped) { // old view was dropped but new wasn't created ?><input type="hidden" name="dropped" value="1"><?php } ?>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($_GET["view"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
