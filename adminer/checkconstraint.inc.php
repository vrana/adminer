<?php
$TABLE = $_GET["checkconstraint"];
$name = $_GET["name"];
$clause = check_constraint($name, $TABLE);

if ($_POST) {
	if (!$error) {
		$drop = "ALTER TABLE " . idf_escape($TABLE) . " DROP CONSTRAINT " . idf_escape($name);
		$location = ME . "table=" . urlencode($TABLE);
		if ($_POST["drop"]) {
			query_redirect($drop, $location, lang('Check constraint has been dropped.'));
		} else {
			if ($name != "") {
				queries($drop);
			}
			queries_redirect(
				$location,
				($name != "" ? lang('Check constraint has been altered.') : lang('Check constraint has been created.')),
				queries(create_check_constraint($TABLE, $_POST['CheckConstraint'], $_POST['Clause']))
			);
			if ($name != "") {
				queries(create_check_constraint($TABLE, $name, $clause));
			}
		}
	}
	$row = $_POST;
}

page_header(($name != "" ? lang('Alter check constraint') . ": " . h($name) : lang('Create check constraint')), $error, array("table" => $TABLE));
?>

<form action="" method="post" id="form">
<p><?php echo lang('Name'); ?>: <input name="CheckConstraint" value="<?php echo h($name); ?>" data-maxlength="64" autocapitalize="off" autofocus>
<p>Clause:<?php textarea("Clause", $clause); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($name != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $name)); ?><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
