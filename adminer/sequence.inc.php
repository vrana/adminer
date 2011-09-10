<?php
$SEQUENCE = $_GET["sequence"];

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	$name = trim($_POST["name"]);
	if ($_POST["drop"]) {
		query_redirect("DROP SEQUENCE " . idf_escape($SEQUENCE), $link, lang('Sequence has been dropped.'));
	} elseif ($SEQUENCE == "") {
		query_redirect("CREATE SEQUENCE " . idf_escape($name), $link, lang('Sequence has been created.'));
	} elseif ($SEQUENCE != $name) {
		query_redirect("ALTER SEQUENCE " . idf_escape($SEQUENCE) . " RENAME TO " . idf_escape($name), $link, lang('Sequence has been altered.'));
	} else {
		redirect($link);
	}
}

page_header($SEQUENCE != "" ? lang('Alter sequence') . ": " . h($SEQUENCE) : lang('Create sequence'), $error);

$row = $_POST;
if (!$row) {
	$row = array("name" => $SEQUENCE);
}
?>

<form action="" method="post">
<p><input name="name" value="<?php echo h($row["name"]); ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if ($SEQUENCE != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
