<?php
$trigger_time = array("BEFORE", "AFTER");
$trigger_event = array("INSERT", "UPDATE", "DELETE");

$dropped = false;
if ($_POST && !$error) {
	if (strlen($_GET["name"])) {
		$dropped = query_redirect("DROP TRIGGER " . idf_escape($_GET["name"]), $SELF . "table=" . urlencode($_GET["trigger"]), lang('Trigger has been dropped.'), $_POST["drop"], !$_POST["dropped"]);
	}
	if (!$_POST["drop"]) {
		if (in_array($_POST["Timing"], $trigger_time) && in_array($_POST["Event"], $trigger_event)) {
			query_redirect("CREATE TRIGGER " . idf_escape($_POST["Trigger"]) . " $_POST[Timing] $_POST[Event] ON " . idf_escape($_GET["trigger"]) . " FOR EACH ROW\n$_POST[Statement]", $SELF . "table=" . urlencode($_GET["trigger"]), (strlen($_GET["name"]) ? lang('Trigger has been altered.') : lang('Trigger has been created.')));
		}
	}
}

page_header((strlen($_GET["name"]) ? lang('Alter trigger') . ": " . htmlspecialchars($_GET["name"]) : lang('Create trigger')), $error, array("table" => $_GET["trigger"]));

$row = array("Trigger" => "$_GET[trigger]_bi");
if ($_POST) {
	$row = $_POST;
} elseif (strlen($_GET["name"])) {
	$result = $dbh->query("SHOW TRIGGERS WHERE `Trigger` = " . $dbh->quote($_GET["name"]));
	$row = $result->fetch_assoc();
	$result->free();
}
?>

<form action="" method="post" id="form">
<table cellspacing="0">
<tr><th><?php echo lang('Time'); ?><td><select name="Timing" onchange="if (/^<?php echo htmlspecialchars(preg_quote($_GET["trigger"], "/")); ?>_[ba][iud]$/.test(this.form['Trigger'].value)) this.form['Trigger'].value = '<?php echo htmlspecialchars(addcslashes($_GET["trigger"], "\r\n'\\")); ?>_' + this.value.charAt(0).toLowerCase() + this.form['Event'].value.charAt(0).toLowerCase();"><?php echo optionlist($trigger_time, $row["Timing"]); ?></select>
<tr><th><?php echo lang('Event'); ?><td><select name="Event" onchange="this.form['Timing'].onchange();"><?php echo optionlist($trigger_event, $row["Event"]); ?></select>
<tr><th><?php echo lang('Name'); ?><td><input name="Trigger" value="<?php echo htmlspecialchars($row["Trigger"]); ?>" maxlength="64">
</table>
<p><textarea name="Statement" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["Statement"]); ?></textarea>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<?php if ($dropped) { ?><input type="hidden" name="dropped" value="1"><?php } ?>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if (strlen($_GET["name"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?>><?php } ?>
</form>
