<?php
if ($_POST && !$error) {
	$link = preg_replace('~ns=[^&]*&~', '', ME) . "ns=";
	if ($_POST["drop"]) {
		query_redirect("DROP SCHEMA " . idf_escape($_GET["ns"]), $link, lang('Schema has been dropped.'));
	} else {
		$name = trim($_POST["name"]);
		$link .= urlencode($name);
		if ($_GET["ns"] == "") {
			query_redirect("CREATE SCHEMA " . idf_escape($name), $link, lang('Schema has been created.'));
		} elseif ($_GET["ns"] != $name) {
			query_redirect("ALTER SCHEMA " . idf_escape($_GET["ns"]) . " RENAME TO " . idf_escape($name), $link, lang('Schema has been altered.')); //! sp_rename in MS SQL
		} else {
			redirect($link);
		}
	}
}

page_header($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema'), $error);

$row = $_POST;
if (!$row) {
	$row = array("name" => $_GET["ns"]);
}
?>

<form action="" method="post">
<p><input id="name" name="name" value="<?php echo h($row["name"]); ?>">
<script type='text/javascript'>document.getElementById('name').focus();</script>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if ($_GET["ns"] != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
