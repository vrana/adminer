<?php
$TABLE = $_GET["foreign"];
if ($_POST && !$error && !$_POST["add"] && !$_POST["change"] && !$_POST["change-js"]) {
	if ($_POST["drop"]) {
		query_redirect("ALTER TABLE " . table($TABLE) . "\nDROP " . ($jush == "sql" ? "FOREIGN KEY " : "CONSTRAINT ") . idf_escape($_GET["name"]), ME . "table=" . urlencode($TABLE), lang('Foreign key has been dropped.'));
	} else {
		$source = array_filter($_POST["source"], 'strlen');
		ksort($source); // enforce input order
		$target = array();
		foreach ($source as $key => $val) {
			$target[$key] = $_POST["target"][$key];
		}
		query_redirect("ALTER TABLE " . table($TABLE)
			. ($_GET["name"] != "" ? "\nDROP " . ($jush == "sql" ? "FOREIGN KEY " : "CONSTRAINT ") . idf_escape($_GET["name"]) . "," : "")
			. "\nADD FOREIGN KEY (" . implode(", ", array_map('idf_escape', $source)) . ") REFERENCES " . table($_POST["table"]) . " (" . implode(", ", array_map('idf_escape', $target)) . ")" //! reuse $_GET["name"] - check in older MySQL versions
			. (ereg("^($on_actions)\$", $_POST["on_delete"]) ? " ON DELETE $_POST[on_delete]" : "")
			. (ereg("^($on_actions)\$", $_POST["on_update"]) ? " ON UPDATE $_POST[on_update]" : "")
		, ME . "table=" . urlencode($TABLE), ($_GET["name"] != "" ? lang('Foreign key has been altered.') : lang('Foreign key has been created.')));
		$error = lang('Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.') . "<br>$error"; //! no partitioning
	}
}

page_header(lang('Foreign key'), $error, array("table" => $TABLE), $TABLE);

$row = array("table" => $TABLE, "source" => array(""));
if ($_POST) {
	$row = $_POST;
	ksort($row["source"]);
	if ($_POST["add"]) {
		$row["source"][] = "";
	} elseif ($_POST["change"] || $_POST["change-js"]) {
		$row["target"] = array();
	}
} elseif ($_GET["name"] != "") {
	$foreign_keys = foreign_keys($TABLE);
	$row = $foreign_keys[$_GET["name"]];
	$row["source"][] = "";
}

$source = array_keys(fields($TABLE)); //! no text and blob
$target = ($TABLE === $row["table"] ? $source : array_keys(fields($row["table"])));
$referencable = array();
foreach (table_status() as $name => $table_status) {
	if (fk_support($table_status)) {
		$referencable[] = $name;
	}
}
?>

<form action="" method="post">
<p>
<?php if ($row["db"] == "" && $row["ns"] == "") { ?>
<?php echo lang('Target table'); ?>:
<?php echo html_select("table", $referencable, $row["table"], "this.form['change-js'].value = '1'; this.form.submit();"); ?>
<input type="hidden" name="change-js" value="">
<noscript><p><input type="submit" name="change" value="<?php echo lang('Change'); ?>"></noscript>
<table cellspacing="0">
<thead><tr><th><?php echo lang('Source'); ?><th><?php echo lang('Target'); ?></thead>
<?php
$j = 0;
foreach ($row["source"] as $key => $val) {
	echo "<tr>";
	echo "<td>" . html_select("source[" . (+$key) . "]", array(-1 => "") + $source, $val, ($j == count($row["source"]) - 1 ? "foreignAddRow(this);" : 1));
	echo "<td>" . html_select("target[" . (+$key) . "]", $target, $row["target"][$key]);
	$j++;
}
?>
</table>
<p>
<?php echo lang('ON DELETE'); ?>: <?php echo html_select("on_delete", array(-1 => "") + explode("|", $on_actions), $row["on_delete"]); ?>
 <?php echo lang('ON UPDATE'); ?>: <?php echo html_select("on_update", array(-1 => "") + explode("|", $on_actions), $row["on_update"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang('Add column'); ?>"></noscript>
<?php } ?>
<?php if ($_GET["name"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
