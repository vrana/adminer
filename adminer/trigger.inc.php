<?php
$TABLE = $_GET["trigger"];
$trigger_options = trigger_options();
$trigger_event = array("INSERT", "UPDATE", "DELETE");

$dropped = false;
if ($_POST && !$error && in_array($_POST["Timing"], $trigger_options["Timing"]) && in_array($_POST["Event"], $trigger_event) && in_array($_POST["Type"], $trigger_options["Type"])) {
	$timing_event = " $_POST[Timing] $_POST[Event]";
	$on = " ON " . table($TABLE);
	$dropped = drop_create(
		"DROP TRIGGER " . idf_escape($_GET["name"]) . ($jush == "pgsql" ? $on : ""),
		"CREATE TRIGGER " . idf_escape($_POST["Trigger"]) . ($jush == "mssql" ? $on . $timing_event : $timing_event . $on) . rtrim(" $_POST[Type]\n$_POST[Statement]", ";") . ";",
		ME . "table=" . urlencode($TABLE),
		lang('Trigger has been dropped.'),
		lang('Trigger has been altered.'),
		lang('Trigger has been created.'),
		$_GET["name"]
	);
}

page_header(($_GET["name"] != "" ? lang('Alter trigger') . ": " . h($_GET["name"]) : lang('Create trigger')), $error, array("table" => $TABLE));

$row = $_POST;
if (!$row) {
	$row = trigger($_GET["name"]) + array("Trigger" => $TABLE . "_bi");
}
?>

<form action="" method="post" id="form">
<table cellspacing="0">
<tr><th><?php echo lang('Time'); ?><td><?php echo html_select("Timing", $trigger_options["Timing"], $row["Timing"], "if (/^" . preg_quote($TABLE, "/") . "_[ba][iud]$/.test(this.form['Trigger'].value)) this.form['Trigger'].value = '" . js_escape($TABLE) . "_' + selectValue(this).charAt(0).toLowerCase() + selectValue(this.form['Event']).charAt(0).toLowerCase();"); ?>
<tr><th><?php echo lang('Event'); ?><td><?php echo html_select("Event", $trigger_event, $row["Event"], "this.form['Timing'].onchange();"); ?>
<tr><th><?php echo lang('Type'); ?><td><?php echo html_select("Type", $trigger_options["Type"], $row["Type"]); ?>
</table>
<p><?php echo lang('Name'); ?>: <input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" maxlength="64">
<p><?php textarea("Statement", $row["Statement"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($_GET["name"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1"><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
