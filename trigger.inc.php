<?php
$trigger_time = array("BEFORE", "AFTER");
$trigger_event = array("INSERT", "UPDATE", "DELETE");

if ($_POST && !$error) {
	if (strlen($_GET["name"]) && $mysql->query("DROP TRIGGER " . idf_escape($_GET["name"])) && $_POST["drop"]) {
		redirect($SELF . "table=" . urlencode($_GET["trigger"]), lang('Trigger has been dropped.'));
	}
	if (!$_POST["drop"]) {
		if (in_array($_POST["Timing"], $trigger_time) && in_array($_POST["Event"], $trigger_event) && $mysql->query(
			"CREATE TRIGGER " . idf_escape($_POST["Trigger"]) . " $_POST[Timing] $_POST[Event] ON " . idf_escape($_GET["trigger"]) . " FOR EACH ROW $_POST[Statement]"
		)) {
			redirect($SELF . "table=" . urlencode($_GET["trigger"]), (strlen($_GET["name"]) ? lang('Trigger has been altered.') : lang('Trigger has been created.')));
		}
	}
	$error = $mysql->error;
}

page_header(strlen($_GET["name"]) ? lang('Alter trigger') . ": " . htmlspecialchars($_GET["name"]) : lang('Create trigger'), array("table" => $_GET["trigger"]));

if ($_POST) {
	$row = $_POST;
	echo "<p class='error'>" . lang('Unable to operate trigger') . ": " . htmlspecialchars($error) . "</p>\n";
} elseif (strlen($_GET["name"])) {
	$result = $mysql->query("SHOW TRIGGERS LIKE '" . $mysql->escape_string(addcslashes($_GET["trigger"], "%_")) . "'");
	while ($row = $result->fetch_assoc()) {
		if ($row["Trigger"] === $_GET["name"]) {
			break;
		}
	}
	$result->free();
} else {
	$row = array();
}
?>

<form action="" method="post" id="form">
<table border="0" cellspacing="0" cellpadding="2">
<tr><th><?php echo lang('Name'); ?></th><td><input name="Trigger" value="<?php echo htmlspecialchars($row["Trigger"]); ?>" maxlength="64" /></td></tr>
<tr><th><?php echo lang('Time'); ?></th><td><select name="Timing"><?php echo optionlist($trigger_time, $row["Timing"]); ?></select></td></tr>
<tr><th><?php echo lang('Event'); ?></th><td><select name="Event"><?php echo optionlist($trigger_event, $row["Event"]); ?></select></td></tr>
</table>
<p><textarea name="Statement" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["Statement"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["name"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
