<?php
$dropped = false;
if ($_POST && !$error) {
	if (strlen($_GET["createv"])) {
		$dropped = query_redirect("DROP VIEW " . idf_escape($_GET["createv"]), substr($SELF, 0, -1), lang('View has been dropped.'), $_POST["drop"], !$_POST["dropped"]);
	}
	if (!$_POST["drop"]) {
		query_redirect("CREATE VIEW " . idf_escape($_POST["name"]) . " AS\n$_POST[select]", $SELF . "view=" . urlencode($_POST["name"]), (strlen($_GET["createv"]) ? lang('View has been altered.') : lang('View has been created.')));
	}
}

page_header((strlen($_GET["createv"]) ? lang('Alter view') : lang('Create view')), $error, array("view" => $_GET["createv"]), $_GET["createv"]);

$row = array();
if ($_POST) {
	$row = $_POST;
} elseif (strlen($_GET["createv"])) {
	$row = view($_GET["createv"]);
	$row["name"] = $_GET["createv"];
}
?>

<form action="" method="post">
<p><textarea name="select" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["select"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1" /><?php } ?>
<?php echo lang('Name'); ?>: <input name="name" value="<?php echo htmlspecialchars($row["name"]); ?>" maxlength="64" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["createv"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?> /><?php } ?>
</p>
</form>
