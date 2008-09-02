<?php
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		if ($mysql->query("DROP EVENT " . idf_escape($_GET["event"]))) {
			redirect(substr($SELF, 0, -1), lang('Event has been dropped.'));
		}
	} elseif (in_array($_POST["INTERVAL_FIELD"], $intervals)) {
		$schedule = " ON SCHEDULE " . ($_POST["INTERVAL_VALUE"] ? "EVERY '" . $mysql->escape_string($_POST["INTERVAL_VALUE"]) . "' $_POST[INTERVAL_FIELD]" . ($_POST["STARTS"] ? " STARTS '" . $mysql->escape_string($_POST["STARTS"]) . "'" : "") . ($_POST["ENDS"] ? " ENDS '" . $mysql->escape_string($_POST["ENDS"]) . "'" : "") : "AT '" . $mysql->escape_string($_POST["STARTS"]) . "'");
		if ($mysql->query((strlen($_GET["event"]) ? "ALTER EVENT " . idf_escape($_GET["event"]) . $schedule . ($_GET["event"] != $_POST["EVENT_NAME"] ? " RENAME TO " . idf_escape($_POST["EVENT_NAME"]) : "") : "CREATE EVENT " . idf_escape($_POST["EVENT_NAME"]) . $schedule) . " DO $_POST[EVENT_DEFINITION]")) {
			redirect(substr($SELF, 0, -1), (strlen($_GET["event"]) ? lang('Event has been altered.') : lang('Event has been created.')));
		}
	}
	$error = $mysql->error;
}
page_header((strlen($_GET["event"]) ? lang('Alter event') . ": " . htmlspecialchars($_GET["event"]) : lang('Create event')), $error);

$row = array();
if ($_POST) {
	$row = $_POST;
} elseif (strlen($_GET["event"])) {
	$result = $mysql->query("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = '" . $mysql->escape_string($_GET["db"]) . "' AND EVENT_NAME = '" . $mysql->escape_string($_GET["event"]) . "'");
	$row = $result->fetch_assoc();
	$result->free();
}
?>

<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<tr><th><?php echo lang('Name'); ?></th><td><input name="EVENT_NAME" value="<?php echo htmlspecialchars($row["EVENT_NAME"]); ?>" maxlength="64" /></td></tr>
<tr><th><?php echo lang('Start'); ?></th><td><input name="STARTS" value="<?php echo htmlspecialchars("$row[EXECUTE_AT]$row[STARTS]"); ?>" /></td></tr>
<tr><th><?php echo lang('End'); ?></th><td><input name="ENDS" value="<?php echo htmlspecialchars($row["ENDS"]); ?>" /></td></tr>
<tr><th><?php echo lang('Every'); ?></th><td><input name="INTERVAL_VALUE" value="<?php echo htmlspecialchars($row["INTERVAL_VALUE"]); ?>" /> <select name="INTERVAL_FIELD"><?php echo optionlist($intervals, $row["INTERVAL_FIELD"]); ?></select></td></tr>
</table>
<p><textarea name="EVENT_DEFINITION" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["EVENT_DEFINITION"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["event"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
