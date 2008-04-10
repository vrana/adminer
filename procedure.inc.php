<?php
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");

$dropped = false;
if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"]) {
	if (strlen($_GET["procedure"]) && ($_POST["dropped"] || $mysql->query("DROP $routine " . idf_escape($_GET["procedure"])))) {
		if ($_POST["drop"]) {
			redirect(substr($SELF, 0, -1), lang('Routine has been dropped.'));
		}
		$dropped = true;
	}
	if (!$_POST["drop"]) {
		$set = array();
		$fields = array_filter((array) $_POST["fields"], 'strlen');
		ksort($fields);
		foreach ($fields as $field) {
			if (strlen($field["field"])) {
				$set[] = (in_array($field["inout"], $inout) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
			}
		}
		if ($mysql->query(
			"CREATE $routine " . idf_escape($_POST["name"])
			. " (" . implode(", ", $set) . ")"
			. (isset($_GET["function"]) ? " RETURNS" . process_type($_POST["returns"], "CHARACTER SET") : "") . "
			$_POST[definition]"
		)) {
			redirect(substr($SELF, 0, -1), (strlen($_GET["procedure"]) ? lang('Routine has been altered.') : lang('Routine has been created.')));
		}
	}
	$error = $mysql->error;
}
page_header((strlen($_GET["procedure"]) ? (isset($_GET["function"]) ? lang('Alter function') : lang('Alter procedure')) . ": " . htmlspecialchars($_GET["procedure"]) : (isset($_GET["function"]) ? lang('Create function') : lang('Create procedure'))), $error);

$collations = get_vals("SHOW CHARACTER SET");
if ($_POST) {
	$row = $_POST;
	$row["fields"] = (array) $row["fields"];
	ksort($row["fields"]);
	$row["fields"] = array_values($row["fields"]);
	if ($_POST["add"]) {
		array_splice($row["fields"], key($_POST["add"]), 0, array(array()));
	}
} elseif (strlen($_GET["procedure"])) {
	$row = routine($_GET["procedure"], $routine);
	$row["name"] = $_GET["procedure"];
} else {
	$row = array("fields" => array());
}
?>

<form action="" method="post" id="form">
<table border="0" cellspacing="0" cellpadding="2">
<?php edit_fields($row["fields"], $collations, $routine); ?>
<?php if (isset($_GET["function"])) { ?><tr><td><?php echo lang('Return type'); ?></td><?php echo edit_type("returns", $row["returns"], $collations); ?></tr><?php } ?>
</table>
<?php echo type_change(count($row["fields"])); ?>
<?php if (isset($_GET["function"])) { ?>
<script type="text/javascript">
document.getElementById('form')['returns[type]'].onchange();
</script>
<?php } ?>
<p><textarea name="definition" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["definition"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1" /><?php } ?>
<?php echo lang('Name'); ?>: <input name="name" value="<?php echo htmlspecialchars($row["name"]); ?>" maxlength="64" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["procedure"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
