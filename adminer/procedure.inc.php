<?php
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");

$dropped = false;
if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	if (strlen($_GET["procedure"])) {
		$dropped = query_redirect("DROP $routine " . idf_escape($_GET["procedure"]), substr($SELF, 0, -1), lang('Routine has been dropped.'), $_POST["drop"], !$_POST["dropped"]);
	}
	if (!$_POST["drop"]) {
		$set = array();
		$fields = array_filter((array) $_POST["fields"], 'strlen');
		ksort($fields); // enforce fields order
		foreach ($fields as $field) {
			if (strlen($field["field"])) {
				$set[] = (in_array($field["inout"], $inout) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
			}
		}
		query_redirect("CREATE $routine " . idf_escape($_POST["name"])
			. " (" . implode(", ", $set) . ")"
			. (isset($_GET["function"]) ? " RETURNS" . process_type($_POST["returns"], "CHARACTER SET") : "")
			. "\n$_POST[definition]"
		, substr($SELF, 0, -1), (strlen($_GET["procedure"]) ? lang('Routine has been altered.') : lang('Routine has been created.')));
	}
}
page_header((strlen($_GET["procedure"]) ? (isset($_GET["function"]) ? lang('Alter function') : lang('Alter procedure')) . ": " . htmlspecialchars($_GET["procedure"]) : (isset($_GET["function"]) ? lang('Create function') : lang('Create procedure'))), $error);

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$row = array("fields" => array());
if ($_POST) {
	$row = $_POST;
	$row["fields"] = (array) $row["fields"];
	process_fields($row["fields"]);
} elseif (strlen($_GET["procedure"])) {
	$row = routine($_GET["procedure"], $routine);
	$row["name"] = $_GET["procedure"];
}
?>

<form action="" method="post" id="form">
<table cellspacing="0">
<?php edit_fields($row["fields"], $collations, $routine); ?>
<?php if (isset($_GET["function"])) { ?><tr><td><?php echo lang('Return type'); ?></td><?php echo edit_type("returns", $row["returns"], $collations); ?></tr><?php } ?>
</table>
<p><textarea name="definition" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["definition"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1" /><?php } ?>
<?php echo lang('Name'); ?>: <input name="name" value="<?php echo htmlspecialchars($row["name"]); ?>" maxlength="64" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["procedure"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?> /><?php } ?>
</p>
</form>
