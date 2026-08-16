<?php
namespace Adminer;

$PROCEDURE = ($_GET["name"] ?: $_GET["procedure"]);
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");
$row = $_POST;
$row["fields"] = (array) $row["fields"];

if ($_POST && !process_fields($row["fields"]) && !$error) {
	foreach ($row["fields"] as $key => $field) {
		if ($field["field"] == "") {
			unset($row["fields"][$key]);
		}
	}

	$old_id = routine_id($PROCEDURE, routine($_GET["procedure"], $routine));
	$new_id = routine_id($row["name"], $row);
	$create = create_routine($routine, $row);
	$location = substr(ME, 0, -1);
	$message = lang('Routine has been altered.');

	if (!$_POST["drop"] && $old_id == $new_id && connection()->flavor != "mysql") {
		query_redirect(substr_replace($create, ' OR REPLACE', 6, 0), $location, $message); // 6 - strlen('CREATE')
	} else {
		$temp_name = "adminer_" . uniqid();
		drop_create(
			"DROP $routine $old_id",
			$create,
			"DROP $routine $new_id",
			create_routine($routine, array("name" => $temp_name) + $row),
			"DROP $routine " . routine_id($temp_name, $row),
			$location,
			lang('Routine has been dropped.'),
			$message,
			lang('Routine has been created.'),
			$PROCEDURE,
			$row["name"]
		);
	}
}

page_header(($PROCEDURE != ""
	? (isset($_GET["function"]) ? lang('Alter function') : lang('Alter procedure')) . ": " . h($PROCEDURE)
	: (isset($_GET["function"]) ? lang('Create function') : lang('Create procedure'))
), $error);

if (!$_POST) {
	if ($PROCEDURE == "") {
		$row["language"] = "sql";
	} else {
		$row = routine($_GET["procedure"], $routine);
		$row["name"] = $PROCEDURE;
	}
}

$collations = (JUSH == "sql" ? flat_collations() : array()); // other drivers don't support collation in routine parameters
$routine_languages = routine_languages();
echo ($collations ? "<datalist id='collations'>" . optionlist($collations) . "</datalist>" : "");
?>

<form action="" method="post" id="form">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? "<label>" . lang('Language') . ": "
	. html_select("language", array_keys($routine_languages), $row["language"], on('change', 'routineLanguage', $routine_languages))
	. "</label>\n" : ""); ?>
<input type='submit' value='<?php echo lang('Save'); ?>'>
<?php echo doc_link(array(
	'sql' => "create-procedure.html", // the same page documents CREATE FUNCTION
	'mariadb' => ($routine == "FUNCTION" ? "create-function/" : "create-procedure/"),
	'pgsql' => ($routine == "FUNCTION" ? "sql-createfunction.html" : "sql-createprocedure.html"),
), "?"); ?>
<div class="scrollable">
<table id="edit-fields" class="nowrap">
<?php
edit_fields($row["fields"], $collations, $routine);
if (isset($_GET["function"])) {
	echo "<tr><td>" . lang('Return type');
	edit_type("returns", (array) $row["returns"], $collations, array(), (JUSH == "pgsql" ? array("void", "trigger") : array()));
}
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p><?php textarea("definition", $row["definition"], 20, 80, ($routine_languages[$row["language"]] ?: JUSH)); ?>
<p>
<input type='submit' value='<?php echo lang('Save'); ?>'>
<?php if ($PROCEDURE != "") { ?>
<input type='submit' name='drop' value='<?php echo lang('Drop'); ?>'<?php echo confirm(lang('Drop %s?', $PROCEDURE)); ?>>
<?php } ?>
<?php
$routine_options = routine_options($routine);
if ($routine_options) {
	$row["options"] = (array) $row["options"];
	$options_visible = false;
	foreach ($routine_options as $key => $values) {
		$default = ($values ? reset($values) : "");
		$row["options"][$key] = idx($row["options"], $key, $default);
		if ($row["options"][$key] != $default) {
			$options_visible = true; // expand the fieldset only if the routine has a non-default characteristic
		}
	}
	print_fieldset("options", lang('Options'), $options_visible);
	echo "<table class='layout'>\n";
	foreach ($routine_options as $key => $values) {
		$label = "label-option-$key";
		$title = str_replace("_", " ", $key);
		$select = array();
		foreach ($values as $value) {
			$select[$value] = (strpos($value, "$title ") === 0 ? substr($value, strlen($title) + 1) : $value); // don't repeat the header in the options, e.g. SQL SECURITY: DEFINER
		}
		echo "<tr><th id='$label'>$title<td>" . ($select
			? html_select("options[$key]", $select, $row["options"][$key], "", $label)
			: "<input name='options[$key]' value='" . h($row["options"][$key]) . "' aria-labelledby='$label' autocapitalize='off'>"
		) . "\n";
	}
	echo "</table>\n</div></fieldset>\n";
}
echo input_token();
?>
</form>
