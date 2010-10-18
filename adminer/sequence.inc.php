<?php
$SEQUENCE = $_GET["sequence"];

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	if ($_POST["drop"]) {
		query_redirect("DROP SEQUENCE " . idf_escape($SEQUENCE), $link, lang('Sequence has been dropped.'));
	} elseif ($SEQUENCE == "") {
		query_redirect("CREATE SEQUENCE " . idf_escape($_POST["name"]), $link, lang('Sequence has been created.'));
	} elseif ($SEQUENCE != $_POST["name"]) {
		query_redirect("ALTER SEQUENCE " . idf_escape($SEQUENCE) . " RENAME TO " . idf_escape($_POST["name"]), $link, lang('Sequence has been altered.'));
	} else {
		redirect($link);
	}
}

page_header($SEQUENCE != "" ? lang('Alter sequence') . ": " . h($SEQUENCE) : lang('Create sequence'), $error);

$row = array("name" => $SEQUENCE);
if ($_POST) {
	$row = $_POST;
}
?>

<form action="" method="post">
<p><input name="name" value="<?php echo h($row["name"]); ?>">
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if ($SEQUENCE != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n";
}
?>
</form>
