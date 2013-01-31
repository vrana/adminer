<?php
$TABLE = $_GET["view"];
$row = ($TABLE == "" ? array() : view($TABLE));
$row["name"] = $TABLE;

if ($_POST) {
	if (!$error) {
		$name = trim($_POST["name"]);
		drop_create(
			"DROP VIEW " . table($TABLE),
			"CREATE VIEW " . table($name) . " AS\n$_POST[select]",
			"CREATE VIEW " . table($TABLE) . " AS\n$row[select]",
			($_POST["drop"] ? substr(ME, 0, -1) : ME . "table=" . urlencode($name)),
			lang('View has been dropped.'),
			lang('View has been altered.'),
			lang('View has been created.'),
			$TABLE
		);
	}
	$row = $_POST;
}

page_header(($TABLE != "" ? lang('Alter view') : lang('Create view')), $error, array("table" => $TABLE), $TABLE);
?>

<form action="" method="post">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64" autocapitalize="off">
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($_GET["view"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
