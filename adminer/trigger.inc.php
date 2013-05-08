<?php
$TABLE = $_GET["trigger"];
$name = $_GET["name"];
$trigger_options = trigger_options();
$trigger_event = array("INSERT", "UPDATE", "DELETE");
$row = (array) trigger($name) + array("Trigger" => $TABLE . "_bi");

if ($_POST) {
	if (!$error && in_array($_POST["Timing"], $trigger_options["Timing"]) && in_array($_POST["Event"], $trigger_event) && in_array($_POST["Type"], $trigger_options["Type"])) {
		// don't use drop_create() because there may not be more triggers for the same action
		$on = " ON " . table($TABLE);
		$drop = "DROP TRIGGER " . idf_escape($name) . ($jush == "pgsql" ? $on : "");
		$location = ME . "table=" . urlencode($TABLE);
		if ($_POST["drop"]) {
			query_redirect($drop, $location, lang('Trigger has been dropped.'));
		} else {
			if ($name != "") {
				queries($drop);
			}
			queries_redirect(
				$location,
				($name != "" ? lang('Trigger has been altered.') : lang('Trigger has been created.')),
				queries(create_trigger($on, $_POST))
			);
			if ($name != "") {
				queries(create_trigger($on, $row + array("Type" => reset($trigger_options["Type"]))));
			}
		}
	}
	$row = $_POST;
}

page_header(($name != "" ? lang('Alter trigger') . ": " . h($name) : lang('Create trigger')), $error, array("table" => $TABLE));
?>

<form action="" method="post" id="form">
<table cellspacing="0">
<tr><th><?php echo lang('Time'); ?><td><?php echo html_select("Timing", $trigger_options["Timing"], $row["Timing"], "if (/^" . preg_quote($TABLE, "/") . "_[ba][iud]$/.test(this.form['Trigger'].value)) this.form['Trigger'].value = '" . js_escape($TABLE) . "_' + selectValue(this).charAt(0).toLowerCase() + selectValue(this.form['Event']).charAt(0).toLowerCase();"); ?>
<tr><th><?php echo lang('Event'); ?><td><?php echo html_select("Event", $trigger_event, $row["Event"], "this.form['Timing'].onchange();"); ?>
<tr><th><?php echo lang('Type'); ?><td><?php echo html_select("Type", $trigger_options["Type"], $row["Type"]); ?>
</table>
<p><?php echo lang('Name'); ?>: <input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" maxlength="64" autocapitalize="off">
<p><?php textarea("Statement", $row["Statement"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($name != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
