<?php
$dropped = false;
if ($_POST && !$error) {
	if (strlen($_GET["view"])) {
		$dropped = query_redirect("DROP VIEW " . idf_escape($_GET["view"]), substr(ME, 0, -1), lang('View has been dropped.'), false, !$_POST["dropped"]);
	}
	query_redirect("CREATE VIEW " . idf_escape($_POST["name"]) . " AS\n$_POST[select]", ME . "table=" . urlencode($_POST["name"]), (strlen($_GET["view"]) ? lang('View has been altered.') : lang('View has been created.')));
}

page_header((strlen($_GET["view"]) ? lang('Alter view') : lang('Create view')), $error, array("table" => $_GET["view"]), $_GET["view"]);

$row = array();
if ($_POST) {
	$row = $_POST;
} elseif (strlen($_GET["view"])) {
	$row = view($_GET["view"]);
	$row["name"] = $_GET["view"];
}
?>

<form action="" method="post">
<p><textarea name="select" rows="10" cols="80" style="width: 98%;"><?php echo h($row["select"]); ?></textarea>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<?php if ($dropped) { // old view was dropped but new wasn't created ?><input type="hidden" name="dropped" value="1"><?php } ?>
<?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64">
<input type="submit" value="<?php echo lang('Save'); ?>">
</form>
