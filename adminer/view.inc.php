<?php
$TABLE = $_GET["view"];
$row = $_POST;

if ($_POST && !$error) {
	$name = trim($row["name"]);
	$as = " AS\n$row[select]";
	$location = ME . "table=" . urlencode($name);
	$message = lang('View has been altered.');
	
	if (!$_POST["drop"] && $TABLE == $name && $jush != "sqlite") {
		query_redirect(($jush == "mssql" ? "ALTER" : "CREATE OR REPLACE") . " VIEW " . table($name) . $as, $location, $message);
	} else {
		$temp_name = $name . "_adminer_" . uniqid();
		drop_create(
			"DROP VIEW " . table($TABLE),
			"CREATE VIEW " . table($name) . $as,
			"DROP VIEW " . table($name),
			"CREATE VIEW " . table($temp_name) . $as,
			"DROP VIEW " . table($temp_name),
			($_POST["drop"] ? substr(ME, 0, -1) : $location),
			lang('View has been dropped.'),
			$message,
			lang('View has been created.'),
			$TABLE,
			$name
		);
	}
}

if (!$_POST && $TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
	if (!$error) {
		$error = $connection->error;
	}
}

page_header(($TABLE != "" ? lang('Alter view') : lang('Create view')), $error, array("table" => $TABLE), h($TABLE));
?>

<form action="" method="post">
<p><?php echo lang('Name'); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" maxlength="64" autocapitalize="off">
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($_GET["view"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
