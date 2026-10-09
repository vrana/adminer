<?php
namespace Adminer;

$row = $_POST;
$comment = (support("comment") && $_GET["ns"] != "" ? schema_comment($_GET["ns"]) : "");

if ($_POST && !$error) {
	$link = preg_replace('~&ns=[^&]*~', '', ME) . "ns=";
	if ($_POST["drop"]) {
		query_redirect("DROP SCHEMA " . idf_escape($_GET["ns"]), $link, lang('Schema has been dropped.'));
	} else {
		$name = trim($row["name"]);
		$link .= url_escape($name);
		$new_comment = (support("comment") ? $row["comment"] : $comment);
		$result = true;
		$message = lang('Schema has been altered.');
		if ($_GET["ns"] == "") {
			$result = queries("CREATE SCHEMA " . idf_escape($name));
			$message = lang('Schema has been created.');
		} elseif ($_GET["ns"] != $name) {
			$result = queries("ALTER SCHEMA " . idf_escape($_GET["ns"]) . " RENAME TO " . idf_escape($name)); //! sp_rename in MS SQL
		} elseif ($new_comment === $comment) {
			redirect($link);
		}
		if ($result && $new_comment !== $comment) {
			$result = set_schema_comment($name, $new_comment);
		}
		queries_redirect($link, $message, $result);
	}
}

$statement = ($_GET["ns"] != "" ? "alter" : "create");
page_header($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema'), $error, array(), "", false, doc_link(array(
	'pgsql' => "sql-$statement" . "schema.html",
	'cockroach' => "$statement-schema",
	'mssql' => "t-sql/statements/create-schema-transact-sql", // ALTER SCHEMA only transfers objects
)));

if (!$row) {
	$row["name"] = $_GET["ns"];
	$row["comment"] = $comment;
}
?>

<form action="" method="post">
<p><input name="name" autofocus value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<input type='submit' value='<?php echo lang('Save'); ?>'>
<?php
if ($_GET["ns"] != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm(lang('Drop %s?', $_GET["ns"])) . ">\n";
}
echo (support("comment") ? "<p>" . lang('Comment') . ": " . adminer()->commentInput('SCHEMA', " name='comment'", $row["comment"]) . "\n" : "");
echo input_token();
?>
</form>
