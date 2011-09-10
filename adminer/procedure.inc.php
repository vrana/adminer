<?php
$PROCEDURE = $_GET["procedure"];
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");
$routine_languages = routine_languages();

$dropped = false;
if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	$set = array();
	$fields = (array) $_POST["fields"];
	ksort($fields); // enforce fields order
	foreach ($fields as $field) {
		if ($field["field"] != "") {
			$set[] = (ereg("^($inout)\$", $field["inout"]) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
	}
	$dropped = drop_create(
		"DROP $routine " . idf_escape($PROCEDURE),
		"CREATE $routine " . idf_escape(trim($_POST["name"])) . " (" . implode(", ", $set) . ")" . (isset($_GET["function"]) ? " RETURNS" . process_type($_POST["returns"], "CHARACTER SET") : "") . (in_array($_POST["language"], $routine_languages) ? " LANGUAGE $_POST[language]" : "") . rtrim("\n$_POST[definition]", ";") . ";",
		substr(ME, 0, -1),
		lang('Routine has been dropped.'),
		lang('Routine has been altered.'),
		lang('Routine has been created.'),
		$PROCEDURE
	);
}

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? lang('Alter function') : lang('Alter procedure')) . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? lang('Create function') : lang('Create procedure'))), $error);

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$row = array("fields" => array());
if ($_POST) {
	$row = $_POST;
	$row["fields"] = (array) $row["fields"];
	process_fields($row["fields"]);
} elseif ($PROCEDURE != "") {
	$row = routine($PROCEDURE, $routine);
	$row["name"] = $PROCEDURE;
}
?>

<form action="" method="post" id="form">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64">
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
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1"><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
