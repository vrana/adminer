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
		$temp_name = "$row[name]_adminer_" . uniqid();
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

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? lang('Alter function') : lang('Alter procedure')) . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? lang('Create function') : lang('Create procedure'))), $error);

if (!$_POST) {
	if ($PROCEDURE == "") {
		$row["language"] = "sql";
	} else {
		$row = routine($_GET["procedure"], $routine);
		$row["name"] = $PROCEDURE;
	}
}

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$routine_languages = routine_languages();
echo ($collations ? "<datalist id='collations'>" . optionlist($collations) . "</datalist>" : "");
?>

<form action="" method="post" id="form">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? "<label>" . lang('Language') . ": " . html_select("language", $routine_languages, $row["language"]) . "</label>\n" : ""); ?>
<input type="submit" value="<?php echo lang('Save'); ?>">
<div class="scrollable">
<table class="nowrap">
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
<p><?php textarea("definition", $row["definition"], 20); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($PROCEDURE != "") { ?>
<input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $PROCEDURE)); ?>
<?php } ?>
<?php echo input_token(); ?>
</form>
