<?php
namespace Adminer;

$TABLE = $_GET["check"];
$name = "$_GET[name]"; // drop_create() and idf_escape() don't accept null
$row = $_POST;

if ($row && !$error) {
	$location = ME . "table=" . url_escape($TABLE);
	$message_drop = lang('Check has been dropped.');
	$message_alter = lang('Check has been altered.');
	$message_create = lang('Check has been created.');
	if (JUSH == "sqlite") {
		queries_redirect(
			$location,
			($row["drop"] ? $message_drop : ($name != "" ? $message_alter : $message_create)),
			recreate_table($TABLE, $TABLE, array(), array(), array(), "", array(), "$name", ($row["drop"] ? "" : $row["clause"]))
		);
	} else {
		// there's no ALTER CHECK so the constraint is dropped and created again, the old one must survive an invalid new one
		$alter = "ALTER TABLE " . table($TABLE);
		$check = " CHECK ($row[clause])"; //! SQL injection
		$temp_name = "adminer_" . uniqid();
		drop_create(
			"$alter DROP CONSTRAINT " . idf_escape($name),
			"$alter ADD" . ($row["name"] != "" ? " CONSTRAINT " . idf_escape($row["name"]) : "") . $check,
			"$alter DROP CONSTRAINT " . idf_escape($row["name"]),
			"$alter ADD CONSTRAINT " . idf_escape($temp_name) . $check,
			"$alter DROP CONSTRAINT " . idf_escape($temp_name),
			$location,
			$message_drop,
			$message_alter,
			$message_create,
			$name,
			$row["name"]
		);
	}
}

page_header(
	($name != "" ? lang('Alter check') : lang('Create check')),
	$error,
	array("table" => $TABLE),
	h($name != "" ? $name : $TABLE)
);

if (!$row) {
	$checks = driver()->checkConstraints($TABLE);
	$row = array("name" => $name, "clause" => $checks[$name]);
}
?>

<form action="" method="post">
<p><?php
if (JUSH != "sqlite") {
	echo lang('Name') . ': <input name="name" value="' . h($row["name"]) . '" data-maxlength="64" autocapitalize="off"> ';
}
echo doc_link(array(
	'sql' => "create-table-check-constraints.html",
	'mariadb' => "constraint/",
	'pgsql' => "ddl-constraints.html#DDL-CONSTRAINTS-CHECK-CONSTRAINTS",
	'mssql' => "relational-databases/tables/create-check-constraints",
	'sqlite' => "lang_createtable.html#check_constraints",
), "?");
?>
<p><?php textarea("clause", $row["clause"]); ?>
<p><input type='submit' value='<?php echo lang('Save'); ?>'>
<?php if ($name != "") { ?>
<input type='submit' name='drop' value='<?php echo lang('Drop'); ?>'<?php echo confirm(lang('Drop %s?', $name)); ?>>
<?php } ?>
<?php echo input_token(); ?>
</form>
