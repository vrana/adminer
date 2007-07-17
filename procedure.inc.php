<?php
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");

if ($_POST && !$error) {
	if (strlen($_GET["procedure"]) && $mysql->query("DROP $routine " . idf_escape($_GET["procedure"])) && $_POST["drop"]) {
		redirect(substr($SELF, 0, -1), lang('Routine has been dropped.'));
	}
	if (!$_POST["drop"]) {
		$set = array();
		ksort($_POST["fields"]);
		foreach ($_POST["fields"] as $field) {
			$set[] = idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
		if ($mysql->query(
			"CREATE $routine " . idf_escape($_POST["name"])
			. " (" . implode(", ", $set) . ")"
			. (isset($_GET["function"]) ? " RETURNS" . process_type($_POST["returns"], "CHARACTER SET") : "") . "
			$_POST[definition]"
		)) {
			redirect(substr($SELF, 0, -1), (strlen($_GET["createp"]) ? lang('Routine has been altered.') : lang('Routine has been created.')));
		}
	}
}

$collations = get_vals("SHOW CHARACTER SETS");
?>
<table border="0" cellspacing="0" cellpadding="2">
<tr><th><?php echo lang('Return type'); ?></th><?php echo edit_type("returns", $row["returns"], $collations); ?></tr>
</table>
<?php echo type_change(count($row["fields"])); ?>
<?php if (isset($_GET["function"])) { ?>
<script type="text/javascript">
document.getElementById('form')['returns[type]'].onchange();
</script>
<?php } ?>
