<?php
namespace Adminer;

$SEQUENCE = $_GET["sequence"];
$row = $_POST;

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		query_redirect("DROP SEQUENCE " . idf_escape($SEQUENCE), $link, lang('Sequence has been dropped.'));
	} elseif ($SEQUENCE == "") {
		query_redirect("CREATE SEQUENCE " . idf_escape($name), $link, lang('Sequence has been created.'));
	} elseif ($SEQUENCE != $name) {
		query_redirect("ALTER SEQUENCE " . idf_escape($SEQUENCE) . " RENAME TO " . idf_escape($name), $link, lang('Sequence has been altered.'));
	} else {
		redirect($link);
	}
}

// not information_schema.sequences which omits the sequences of identity columns, the same as in db.inc.php
$not_found = (!$_POST && $SEQUENCE != ""
	&& !get_val("SELECT relname FROM pg_class WHERE relkind = 'S' AND relnamespace = " . driver()->nsOid . " AND relname = " . q($SEQUENCE))
);

page_header(
	($SEQUENCE != "" ? lang('Alter sequence') . ": " . h($SEQUENCE) : lang('Create sequence')),
	$error,
	array(),
	"",
	$not_found
);

if (!$row) {
	$row["name"] = $SEQUENCE;
}
?>

<form action="" method="post">
<p><input name="name" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<input type='submit' value='<?php echo lang('Save'); ?>'>
<?php
if ($SEQUENCE != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm(lang('Drop %s?', $SEQUENCE)) . ">\n";
}
echo input_token();
?>
</form>
