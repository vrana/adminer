<?php
$TABLE = $_GET["trigger"];
$trigger_time = array("BEFORE", "AFTER");
$trigger_event = array("INSERT", "UPDATE", "DELETE");

$dropped = false;
if ($_POST && !$error && in_array($_POST["Timing"], $trigger_time) && in_array($_POST["Event"], $trigger_event)) {
	$dropped = drop_create(
		"DROP TRIGGER " . idf_escape($_GET["name"]),
		"CREATE TRIGGER " . idf_escape($_POST["Trigger"]) . " $_POST[Timing] $_POST[Event] ON " . idf_escape($TABLE) . " FOR EACH ROW\n$_POST[Statement]",
		ME . "table=" . urlencode($TABLE),
		lang('Trigger has been dropped.'),
		lang('Trigger has been altered.'),
		lang('Trigger has been created.'),
		$_GET["name"]
	);
}

page_header((strlen($_GET["name"]) ? lang('Alter trigger') . ": " . h($_GET["name"]) : lang('Create trigger')), $error, array("table" => $TABLE));

$row = array("Trigger" => $TABLE . "_bi");
if ($_POST) {
	$row = $_POST;
} elseif (strlen($_GET["name"])) {
	$result = $connection->query("SHOW TRIGGERS WHERE `Trigger` = " . $connection->quote($_GET["name"]));
	$row = $result->fetch_assoc();
}
?>

<form action="" method="post" id="form">
<table cellspacing="0">
<tr><th><?php echo lang('Time'); ?><td><?php echo html_select("Timing", $trigger_time, $row["Timing"], "if (/^" . h(preg_quote($TABLE, "/")) . "_[ba][iud]$/.test(this.form['Trigger'].value)) this.form['Trigger'].value = '" . h(addcslashes($TABLE, "\r\n'\\")) . "_' + selectValue(this).charAt(0).toLowerCase() + selectValue(this.form['Event']).charAt(0).toLowerCase();"); ?>
<tr><th><?php echo lang('Event'); ?><td><?php echo html_select("Event", $trigger_event, $row["Event"], "this.form['Timing'].onchange();"); ?>
<tr><th><?php echo lang('Name'); ?><td><input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" maxlength="64">
</table>
<p><textarea name="Statement" rows="10" cols="80" style="width: 98%;"><?php echo h($row["Statement"]); ?></textarea>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1"><?php } ?>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if (strlen($_GET["name"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?>><?php } ?>
</form>
