<?php
$TABLE = $_GET["view"];
$row = $_POST;

if ($_POST && !$error) {
	$name = trim($row["name"]);
	$temp_name = $name . "_adminer_" . uniqid();
	$as = " AS\n$row[select]";
	drop_create(
		"DROP VIEW " . table($TABLE),
		"CREATE VIEW " . table($name) . $as,
		"CREATE VIEW " . table($temp_name) . $as,
		"DROP VIEW " . table($temp_name),
		($_POST["drop"] ? substr(ME, 0, -1) : ME . "table=" . urlencode($name)),
		lang('View has been dropped.'),
		lang('View has been altered.'),
		lang('View has been created.'),
		$TABLE
	);
}

page_header(($TABLE != "" ? lang('Alter view') : lang('Create view')), $error, array("table" => $TABLE), $TABLE);

if (!$_POST && $TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
}
?>

<form action="" method="post">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64" autocapitalize="off">
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($_GET["view"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
