<?php
namespace Adminer;

$TABLE = $_GET["foreign"];
$name = $_GET["name"];
$row = $_POST;

if ($_POST && !$error && !$_POST["add"] && !$_POST["change"] && !$_POST["change-js"]) {
	if (!$_POST["drop"]) {
		$row["source"] = array_filter($row["source"], 'strlen');
		ksort($row["source"]); // enforce input order
		$target = array();
		foreach ($row["source"] as $key => $val) {
			$target[$key] = $row["target"][$key];
		}
		$row["target"] = $target;
	}

	if (JUSH == "sqlite") {
		$result = recreate_table($TABLE, $TABLE, array(), array(), array(" $name" => ($row["drop"] ? "" : " " . format_foreign_key($row))));
	} else {
		$alter = "ALTER TABLE " . table($TABLE);
		$result = ($name == "" || queries("$alter DROP " . (JUSH == "sql" ? "FOREIGN KEY " : "CONSTRAINT ") . idf_escape($name)));
		if (!$row["drop"]) {
			$result = queries("$alter ADD" . format_foreign_key($row));
		}
	}
	queries_redirect(
		ME . "table=" . urlencode($TABLE),
		($row["drop"] ? lang('Foreign key has been dropped.') : ($name != "" ? lang('Foreign key has been altered.') : lang('Foreign key has been created.'))),
		$result
	);
	if (!$row["drop"]) {
		$error = lang('Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.'); //! no partitioning
	}
}

page_header(lang('Foreign key'), $error, array("table" => $TABLE), h($TABLE));

if ($_POST) {
	ksort($row["source"]);
	if ($_POST["add"]) {
		$row["source"][] = "";
	} elseif ($_POST["change"] || $_POST["change-js"]) {
		$row["target"] = array();
	}
} elseif ($name != "") {
	$foreign_keys = foreign_keys($TABLE);
	$row = $foreign_keys[$name];
	$row["source"][] = "";
} else {
	$row["table"] = $TABLE;
	$row["source"] = array("");
}
?>

<form action="" method="post">
<?php
$source = array_keys(fields($TABLE)); //! no text and blob
if ($row["db"] != "") {
	connection()->select_db($row["db"]);
}
if ($row["ns"] != "") {
	$orig_schema = get_schema();
	set_schema($row["ns"]);
}
$referencable = array_keys(array_filter(table_status('', true), 'Adminer\fk_support'));
$target = array_keys(fields(in_array($row["table"], $referencable) ? $row["table"] : reset($referencable)));
$onchange = "this.form['change-js'].value = '1'; this.form.submit();";
echo "<p><label>" . lang('Target table') . ": " . html_select("table", $referencable, $row["table"], $onchange) . "</label>\n";
if (support("scheme")) {
	$schemas = array_filter(adminer()->schemas(), function ($schema) {
		return !preg_match('~^information_schema$~i', $schema);
	});
	echo "<label>" . lang('Schema') . ": " . html_select("ns", $schemas, $row["ns"] != "" ? $row["ns"] : $_GET["ns"], $onchange) . "</label>";
	if ($row["ns"] != "") {
		set_schema($orig_schema);
	}
} elseif (JUSH != "sqlite") {
	$dbs = array();
	foreach (adminer()->databases() as $db) {
		if (!information_schema($db)) {
			$dbs[] = $db;
		}
	}
	echo "<label>" . lang('DB') . ": " . html_select("db", $dbs, $row["db"] != "" ? $row["db"] : $_GET["db"], $onchange) . "</label>";
}
echo input_hidden("change-js");
?>
<noscript><p><input type="submit" name="change" value="<?php echo lang('Change'); ?>"></noscript>
<table>
<thead><tr><th id="label-source"><?php echo lang('Source'); ?><th id="label-target"><?php echo lang('Target'); ?></thead>
<?php
$j = 0;
foreach ($row["source"] as $key => $val) {
	echo "<tr>";
	echo "<td>" . html_select("source[" . (+$key) . "]", array(-1 => "") + $source, $val, ($j == count($row["source"]) - 1 ? "foreignAddRow.call(this);" : ""), "label-source");
	echo "<td>" . html_select("target[" . (+$key) . "]", $target, idx($row["target"], $key), "", "label-target");
	$j++;
}
?>
</table>
<p>
<label><?php echo lang('ON DELETE'); ?>: <?php echo html_select("on_delete", array(-1 => "") + explode("|", driver()->onActions), $row["on_delete"]); ?></label>
<label><?php echo lang('ON UPDATE'); ?>: <?php echo html_select("on_update", array(-1 => "") + explode("|", driver()->onActions), $row["on_update"]); ?></label>
<?php echo doc_link(array(
	'sql' => "innodb-foreign-key-constraints.html",
	'mariadb' => "foreign-keys/",
	'pgsql' => "sql-createtable.html#SQL-CREATETABLE-REFERENCES",
	'mssql' => "t-sql/statements/create-table-transact-sql",
	'oracle' => "SQLRF01111",
)); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang('Add column'); ?>"></noscript>
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $name)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
