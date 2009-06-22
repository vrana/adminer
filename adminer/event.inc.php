<?php
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");
$statuses = array("ENABLED" => "ENABLE", "DISABLED" => "DISABLE", "SLAVESIDE_DISABLED" => "DISABLE ON SLAVE");

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		query_redirect("DROP EVENT " . idf_escape($_GET["event"]), substr($SELF, 0, -1), lang('Event has been dropped.'));
	} elseif (in_array($_POST["INTERVAL_FIELD"], $intervals) && in_array($_POST["STATUS"], $statuses)) {
		$schedule = "\nON SCHEDULE " . ($_POST["INTERVAL_VALUE"]
			? "EVERY " . $dbh->quote($_POST["INTERVAL_VALUE"]) . " $_POST[INTERVAL_FIELD]"
			. ($_POST["STARTS"] ? " STARTS " . $dbh->quote($_POST["STARTS"]) : "")
			. ($_POST["ENDS"] ? " ENDS " . $dbh->quote($_POST["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . $dbh->quote($_POST["STARTS"])
			) . " ON COMPLETION" . ($_POST["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;
		query_redirect((strlen($_GET["event"])
			? "ALTER EVENT " . idf_escape($_GET["event"]) . $schedule
			. ($_GET["event"] != $_POST["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($_POST["EVENT_NAME"]) : "")
			: "CREATE EVENT " . idf_escape($_POST["EVENT_NAME"]) . $schedule
			) . "\n$_POST[STATUS] COMMENT " . $dbh->quote($_POST["EVENT_COMMENT"])
			. " DO\n$_POST[EVENT_DEFINITION]"
		, substr($SELF, 0, -1), (strlen($_GET["event"]) ? lang('Event has been altered.') : lang('Event has been created.')));
	}
}
page_header((strlen($_GET["event"]) ? lang('Alter event') . ": " . htmlspecialchars($_GET["event"]) : lang('Create event')), $error);

$row = array();
if ($_POST) {
	$row = $_POST;
} elseif (strlen($_GET["event"])) {
	$result = $dbh->query("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $dbh->quote($_GET["db"]) . " AND EVENT_NAME = " . $dbh->quote($_GET["event"]));
	$row = $result->fetch_assoc();
	$row["STATUS"] = $statuses[$row["STATUS"]];
	$result->free();
}
?>

<form action="" method="post">
<table cellspacing="0">
<tr><th><?php echo lang('Name'); ?></th><td><input name="EVENT_NAME" value="<?php echo htmlspecialchars($row["EVENT_NAME"]); ?>" maxlength="64" /></td></tr>
<tr><th><?php echo lang('Start'); ?></th><td><input name="STARTS" value="<?php echo htmlspecialchars("$row[EXECUTE_AT]$row[STARTS]"); ?>" /></td></tr>
<tr><th><?php echo lang('End'); ?></th><td><input name="ENDS" value="<?php echo htmlspecialchars($row["ENDS"]); ?>" /></td></tr>
<tr><th><?php echo lang('Every'); ?></th><td><input name="INTERVAL_VALUE" value="<?php echo htmlspecialchars($row["INTERVAL_VALUE"]); ?>" size="6" /> <select name="INTERVAL_FIELD"><?php echo optionlist($intervals, $row["INTERVAL_FIELD"]); ?></select></td></tr>
<tr><th><?php echo lang('Status'); ?></th><td><select name="STATUS"><?php echo optionlist($statuses, $row["STATUS"]); ?></select></td></tr>
<tr><th><?php echo lang('Comment'); ?></th><td><input name="EVENT_COMMENT" value="<?php echo htmlspecialchars($row["EVENT_COMMENT"]); ?>" maxlength="64" /></td></tr>
<tr><th>&nbsp;</th><td><label><input type="checkbox" name="ON_COMPLETION" value="PRESERVE"<?php echo ($row["ON_COMPLETION"] == "PRESERVE" ? " checked='checked'" : ""); ?> /><?php echo lang('On completion preserve'); ?></label></td></tr>
</table>
<p><textarea name="EVENT_DEFINITION" rows="10" cols="80" style="width: 98%;"><?php echo htmlspecialchars($row["EVENT_DEFINITION"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["event"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?> /><?php } ?>
</p>
</form>
