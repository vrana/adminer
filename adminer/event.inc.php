<?php
$EVENT = $_GET["event"];
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");
$statuses = array("ENABLED" => "ENABLE", "DISABLED" => "DISABLE", "SLAVESIDE_DISABLED" => "DISABLE ON SLAVE");
$row = $_POST;

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		query_redirect("DROP EVENT " . idf_escape($EVENT), substr(ME, 0, -1), lang('Event has been dropped.'));
	} elseif (in_array($row["INTERVAL_FIELD"], $intervals) && isset($statuses[$row["STATUS"]])) {
		$schedule = "\nON SCHEDULE " . ($row["INTERVAL_VALUE"]
			? "EVERY " . q($row["INTERVAL_VALUE"]) . " $row[INTERVAL_FIELD]"
			. ($row["STARTS"] ? " STARTS " . q($row["STARTS"]) : "")
			. ($row["ENDS"] ? " ENDS " . q($row["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . q($row["STARTS"])
			) . " ON COMPLETION" . ($row["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;
		
		queries_redirect(substr(ME, 0, -1), ($EVENT != "" ? lang('Event has been altered.') : lang('Event has been created.')), queries(($EVENT != ""
			? "ALTER EVENT " . idf_escape($EVENT) . $schedule
			. ($EVENT != $row["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($row["EVENT_NAME"]) : "")
			: "CREATE EVENT " . idf_escape($row["EVENT_NAME"]) . $schedule
			) . "\n" . $statuses[$row["STATUS"]] . " COMMENT " . q($row["EVENT_COMMENT"])
			. rtrim(" DO\n$row[EVENT_DEFINITION]", ";") . ";"
		));
	}
}

page_header(($EVENT != "" ? lang('Alter event') . ": " . h($EVENT) : lang('Create event')), $error);

if (!$row && $EVENT != "") {
	$rows = get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . q(DB) . " AND EVENT_NAME = " . q($EVENT));
	$row = reset($rows);
}
?>

<form action="" method="post">
<table cellspacing="0">
<tr><th><?php echo lang('Name'); ?><td><input name="EVENT_NAME" value="<?php echo h($row["EVENT_NAME"]); ?>" maxlength="64" autocapitalize="off">
<tr><th title="datetime"><?php echo lang('Start'); ?><td><input name="STARTS" value="<?php echo h("$row[EXECUTE_AT]$row[STARTS]"); ?>">
<tr><th title="datetime"><?php echo lang('End'); ?><td><input name="ENDS" value="<?php echo h($row["ENDS"]); ?>">
<tr><th><?php echo lang('Every'); ?><td><input type="number" name="INTERVAL_VALUE" value="<?php echo h($row["INTERVAL_VALUE"]); ?>" class="size"> <?php echo html_select("INTERVAL_FIELD", $intervals, $row["INTERVAL_FIELD"]); ?>
<tr><th><?php echo lang('Status'); ?><td><?php echo html_select("STATUS", $statuses, $row["STATUS"]); ?>
<tr><th><?php echo lang('Comment'); ?><td><input name="EVENT_COMMENT" value="<?php echo h($row["EVENT_COMMENT"]); ?>" maxlength="64">
<tr><th>&nbsp;<td><?php echo checkbox("ON_COMPLETION", "PRESERVE", $row["ON_COMPLETION"] == "PRESERVE", lang('On completion preserve')); ?>
</table>
<p><?php textarea("EVENT_DEFINITION", $row["EVENT_DEFINITION"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($EVENT != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $EVENT)); ?><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
