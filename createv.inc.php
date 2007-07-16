<?php
if ($_POST && !$error) {
	if (strlen($_GET["createv"]) && $mysql->query("DROP VIEW " . idf_escape($_GET["createv"])) && $_POST["drop"]) {
		redirect(substr($SELF, 0, -1), lang('View has been dropped.'));
	}
	if (!$_POST["drop"] && $mysql->query("CREATE VIEW " . idf_escape($_POST["name"]) . " AS " . $_POST["select"])) {
		redirect($SELF . "view=" . urlencode($_POST["name"]), (strlen($_GET["createv"]) ? lang('View has been altered.') : lang('View has been created.')));
	}
	$error = $mysql->error;
}

page_header(strlen($_GET["createv"]) ? lang('Alter view') . ": " . htmlspecialchars($_GET["createv"]) : lang('Create view'));

if ($_POST) {
	$row = $_POST;
	echo "<p class='error'>" . lang('Unable to operate view') . ": " . htmlspecialchars($error) . "</p>\n";
} elseif (strlen($_GET["createv"])) {
	$row = view($_GET["createv"]);
	$row["name"] = $_GET["createv"];
} else {
	$row = array();
}
?>

<form action="" method="post">
<p><textarea name="select" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["select"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php echo lang('Name'); ?>: <input name="name" value="<?php echo htmlspecialchars($row["name"]); ?>" maxlength="64" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["createv"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" /><?php } ?>
</p>
</form>
