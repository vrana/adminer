<?php
$PROCEDURE = $_GET["procedure"];
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");
$routine_languages = routine_languages();
$row = ($PROCEDURE == "" ? array("fields" => array()) : routine($PROCEDURE, $routine));
$row["name"] = $PROCEDURE;

if ($_POST) {
	if (!$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
		drop_create(
			"DROP $routine " . idf_escape($PROCEDURE),
			create_routine($routine, $_POST),
			create_routine($routine, $row),
			substr(ME, 0, -1),
			lang('Routine has been dropped.'),
			lang('Routine has been altered.'),
			lang('Routine has been created.'),
			$PROCEDURE
		);
	}
	$row = $_POST;
	$row["fields"] = (array) $row["fields"];
	process_fields($row["fields"]);
}

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? lang('Alter function') : lang('Alter procedure')) . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? lang('Create function') : lang('Create procedure'))), $error);

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
?>

<form action="" method="post" id="form">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? lang('Language') . ": " . html_select("language", $routine_languages, $row["language"]) : ""); ?>
<table cellspacing="0" class="nowrap">
<?php
edit_fields($row["fields"], $collations, $routine);
if (isset($_GET["function"])) {
	echo "<tr><td>" . lang('Return type');
	edit_type("returns", $row["returns"], $collations);
}
?>
</table>
<p><?php textarea("definition", $row["definition"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($PROCEDURE != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
