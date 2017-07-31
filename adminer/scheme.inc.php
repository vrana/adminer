<?php
$row = $_POST;

if ($_POST && !$error) {
	$link = preg_replace('~ns=[^&]*&~', '', ME) . "ns=";
	if ($_POST["drop"]) {
		query_redirect("DROP SCHEMA " . idf_escape($_GET["ns"]), $link, lang('Schema has been dropped.'));
	} else {
		$name = trim($row["name"]);
		$link .= urlencode($name);
		if ($_GET["ns"] == "") {
			query_redirect("CREATE SCHEMA " . idf_escape($name), $link, lang('Schema has been created.'));
		} elseif ($_GET["ns"] != $name) {
			query_redirect("ALTER SCHEMA " . idf_escape($_GET["ns"]) . " RENAME TO " . idf_escape($name), $link, lang('Schema has been altered.')); //! sp_rename in MS SQL
		} else {
			adminer_redirect($link);
		}
	}
}

page_header($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema'), $error);

if (!$row) {
	$row["name"] = $_GET["ns"];
}
?>

<form action="" method="post">
<p><input name="name" id="name" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<script type='text/javascript'>focus(document.getElementById('name'));</script>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if ($_GET["ns"] != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
