<?php
namespace Adminer;

$TYPE = $_GET["type"];
$row = $_POST;
// types(true) is the list used for the links to this page in table structure
$type = ($TYPE != "" ? type_definition(+array_search($TYPE, types(true))) : array());
$object = ($type["kind"] == 'd' ? "DOMAIN" : "TYPE"); // domains are created, altered and dropped as DOMAIN

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	$name = trim($row["name"]);
	$as = trim($row["as"]);
	// CREATE TYPE accepts AS ENUM (...), AS RANGE (...), AS (...) and (INPUT = ...), anything else after AS is a base type of a domain
	$new_object = (preg_match('~^AS\s+(?!ENUM\b|RANGE\b|\()~i', $as) ? "DOMAIN" : "TYPE");
	$message = lang('Type has been altered.');
	if (!$_POST["drop"] && $TYPE != "" && $as == $type["definition"] && $new_object == $object) {
		if ($TYPE == $name) {
			redirect($link);
		}
		query_redirect("ALTER $object " . idf_escape($TYPE) . " RENAME TO " . idf_escape($name), $link, $message);
	} else {
		// there's no CREATE OR REPLACE TYPE, drop_create() verifies the new definition before dropping the original type
		$temp_name = $name . "_adminer_" . uniqid();
		drop_create(
			"DROP $object " . idf_escape($TYPE),
			"CREATE $new_object " . idf_escape($name) . " $as",
			"DROP $new_object " . idf_escape($name),
			"CREATE $new_object " . idf_escape($temp_name) . " $as",
			"DROP $new_object " . idf_escape($temp_name),
			$link,
			lang('Type has been dropped.'),
			$message,
			lang('Type has been created.'),
			$TYPE,
			$name
		);
	}
}

page_header($TYPE != "" ? lang('Alter type') . ": " . h($TYPE) : lang('Create type'), $error);

if (!$row) {
	$row["name"] = $TYPE;
	$row["as"] = ($TYPE != "" ? $type["definition"] : "AS ");
}
?>

<form action="" method="post">
<p>
<?php
echo lang('Name') . ": <input name='name' value='" . h($row['name']) . "' autocapitalize='off'>\n";
echo doc_link(array(
	'pgsql' => "sql-createtype.html",
), "?");
textarea("as", $row["as"]);
echo "<p><input type='submit' value='" . lang('Save') . "'>\n";
if ($TYPE != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm(lang('Drop %s?', $TYPE)) . ">\n";
}
echo input_token();
?>
</form>
