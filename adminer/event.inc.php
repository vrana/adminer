<?php
$EVENT = $_GET["event"];
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");
$statuses = array("ENABLED" => "ENABLE", "DISABLED" => "DISABLE", "SLAVESIDE_DISABLED" => "DISABLE ON SLAVE");

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		query_redirect("DROP EVENT " . idf_escape($EVENT), substr(ME, 0, -1), lang('Event has been dropped.'));
	} elseif (in_array($_POST["INTERVAL_FIELD"], $intervals) && isset($statuses[$_POST["STATUS"]])) {
		$schedule = "\nON SCHEDULE " . ($_POST["INTERVAL_VALUE"]
			? "EVERY " . $connection->quote($_POST["INTERVAL_VALUE"]) . " $_POST[INTERVAL_FIELD]"
			. ($_POST["STARTS"] ? " STARTS " . $connection->quote($_POST["STARTS"]) : "")
			. ($_POST["ENDS"] ? " ENDS " . $connection->quote($_POST["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . $connection->quote($_POST["STARTS"])
			) . " ON COMPLETION" . ($_POST["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;
		query_redirect((strlen($EVENT)
			? "ALTER EVENT " . idf_escape($EVENT) . $schedule
			. ($EVENT != $_POST["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($_POST["EVENT_NAME"]) : "")
			: "CREATE EVENT " . idf_escape($_POST["EVENT_NAME"]) . $schedule
			) . "\n" . $statuses[$_POST["STATUS"]] . " COMMENT " . $connection->quote($_POST["EVENT_COMMENT"])
			. " DO\n$_POST[EVENT_DEFINITION]"
		, substr(ME, 0, -1), (strlen($EVENT) ? lang('Event has been altered.') : lang('Event has been created.')));
	}
}

page_header((strlen($EVENT) ? lang('Alter event') . ": " . h($EVENT) : lang('Create event')), $error);

$row = array();
if ($_POST) {
	$row = $_POST;
} elseif (strlen($EVENT)) {
	$result = $connection->query("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $connection->quote(DB) . " AND EVENT_NAME = " . $connection->quote($EVENT));
	$row = $result->fetch_assoc();
}
?>

<form action="" method="post">
<table cellspacing="0">
<tr><th><?php echo lang('Name'); ?><td><input name="EVENT_NAME" value="<?php echo h($row["EVENT_NAME"]); ?>" maxlength="64">
<tr><th><?php echo lang('Start'); ?><td><input name="STARTS" value="<?php echo h("$row[EXECUTE_AT]$row[STARTS]"); ?>">
<tr><th><?php echo lang('End'); ?><td><input name="ENDS" value="<?php echo h($row["ENDS"]); ?>">
<tr><th><?php echo lang('Every'); ?><td><input name="INTERVAL_VALUE" value="<?php echo h($row["INTERVAL_VALUE"]); ?>" size="6"> <select name="INTERVAL_FIELD"><?php echo optionlist($intervals, $row["INTERVAL_FIELD"]); ?></select>
<tr><th><?php echo lang('Status'); ?><td><select name="STATUS"><?php echo optionlist($statuses, $row["STATUS"]); ?></select>
<tr><th><?php echo lang('Comment'); ?><td><input name="EVENT_COMMENT" value="<?php echo h($row["EVENT_COMMENT"]); ?>" maxlength="64">
<tr><th>&nbsp;<td><label><input type="checkbox" name="ON_COMPLETION" value="PRESERVE"<?php echo ($row["ON_COMPLETION"] == "PRESERVE" ? " checked" : ""); ?>><?php echo lang('On completion preserve'); ?></label>
</table>
<p><textarea name="EVENT_DEFINITION" rows="10" cols="80" style="width: 98%;"><?php echo h($row["EVENT_DEFINITION"]); ?></textarea>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if (strlen($EVENT)) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?>><?php } ?>
</form>
