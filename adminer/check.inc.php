<?php
namespace Adminer;

$TABLE = $_GET["check"];
$name = $_GET["name"];
$row = $_POST;

if ($row && !$error) {
	if (JUSH == "sqlite") {
		$result = recreate_table($TABLE, $TABLE, array(), array(), array(), 0, array(), $name, ($row["drop"] ? "" : $row["clause"]));
	} else {
		$result = ($name == "" || queries("ALTER TABLE " . table($TABLE) . " DROP CONSTRAINT " . idf_escape($name)));
		if (!$row["drop"]) {
			$result = queries("ALTER TABLE " . table($TABLE) . " ADD" . ($row["name"] != "" ? " CONSTRAINT " . idf_escape($row["name"]) : "") . " CHECK ($row[clause])"); //! SQL injection
		}
	}
	queries_redirect(
		ME . "table=" . urlencode($TABLE),
		($row["drop"] ? lang('Check has been dropped.') : ($name != "" ? lang('Check has been altered.') : lang('Check has been created.'))),
		$result
	);
}

page_header(($name != "" ? lang('Alter check') . ": " . h($name) : lang('Create check')), $error, array("table" => $TABLE));

if (!$row) {
	$checks = $driver->checkConstraints($TABLE);
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
<p><input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($name != "") { ?>
<input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $name)); ?>
<?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
