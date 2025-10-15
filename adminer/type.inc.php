<?php

namespace Adminer;

$TYPE = $_GET["type"];
$row = $_POST;

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	if ($_POST["drop"]) {
		query_redirect("DROP TYPE " . idf_escape($TYPE), $link, lang('Type has been dropped.'));
	} else {
		query_redirect("CREATE TYPE " . idf_escape(trim($row["name"])) . " $row[as]", $link, lang('Type has been created.'));
	}
}

page_header($TYPE != "" ? lang('Alter type') . ": " . h($TYPE) : lang('Create type'), $error);

if (!$row) {
	$row["as"] = "AS ";
}
?>

<form action="" method="post">
<p>
<?php
if ($TYPE != "") {
	$types = driver()->types();
	$enums = type_values($types[$TYPE]);
	if ($enums) {
		echo "<code class='jush-" . JUSH . "'>ENUM (" . h($enums) . ")</code>\n<p>";
	}
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'>" . confirm(lang('Drop %s?', $TYPE)) . "\n";
} else {
	echo lang('Name') . ": <input name='name' value='" . h($row['name']) . "' autocapitalize='off'>\n";
	echo doc_link(array(
		'pgsql' => "datatype-enum.html",
	), "?");
	textarea("as", $row["as"]);
	echo "<p><input type='submit' value='" . lang('Save') . "'>\n";
}
echo input_token();
?>
</form>
