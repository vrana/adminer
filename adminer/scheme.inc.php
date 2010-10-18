<?php
if ($_POST && !$error) {
	$link = preg_replace('~ns=[^&]*&~', '', ME) . "ns=";
	if ($_POST["drop"]) {
		query_redirect("DROP SCHEMA " . idf_escape($_GET["ns"]), $link, lang('Schema has been dropped.'));
	} else {
		$link .= urlencode($_POST["name"]);
		if ($_GET["ns"] == "") {
			query_redirect("CREATE SCHEMA " . idf_escape($_POST["name"]), $link, lang('Schema has been created.'));
		} elseif ($_GET["ns"] != $_POST["name"]) {
			query_redirect("ALTER SCHEMA " . idf_escape($_GET["ns"]) . " RENAME TO " . idf_escape($_POST["name"]), $link, lang('Schema has been altered.')); //! sp_rename in MS SQL
		} else {
			redirect($link);
		}
	}
}

page_header($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema'), $error);

$row = array("name" => $_GET["ns"]);
if ($_POST) {
	$row = $_POST;
}
?>

<form action="" method="post">
<p><input name="name" value="<?php echo h($row["name"]); ?>">
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if ($_GET["ns"] != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n";
}
?>
</form>
