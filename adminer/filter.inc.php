<?php
$TABLE = $_GET["filter"];
$name = $_GET["name"];
$row = (array) filter($name, $TABLE);

if ($_POST) {
    if (!$error && $_POST["Name"] && $_POST["Filter"]) {
        if ($_POST["drop"]) {
            $location = ME . "table=" . urlencode($TABLE);
            filter_drop($TABLE, $name);
            redirect($location, lang('Filter has been dropped.'));
        } else {
            if ($name != "") {
                filter_save($TABLE, $name, $_POST["Name"], $_POST["Filter"]);
                $location = ME . "filter=" . urlencode($TABLE) . '&name=' . urlencode($_POST["Name"]);
                redirect($location, lang('Filter has been updated.'));
            }
        }
    }
    $row = $_POST;
}

page_header(($name != "" ? lang('Alter filter') . ": " . h($name) : lang('Create filter')), $error, ["table" => $TABLE]);
?>

<form action="" method="post" id="form" style="width:60rem;">
	<p><?php echo lang('Name'); ?>: <input
			name="Name"
			value="<?php echo h($row["Name"]); ?>"
			data-maxlength="64" autocapitalize="off">
		<?php echo script("qs('#form')['Timing'].onchange();"); ?>
	<p><?php monaco("Filter", $row["Filter"], 25, 60, "sql"); ?></p>
	<p>
		<input type="submit"
			value="<?php echo lang('Save'); ?>">
		<?php if ($name != "") { ?><input
			type="submit" name="drop"
			value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $name)); ?><?php } ?>
		<input type="hidden" name="token"
			value="<?php echo $token; ?>">
</form>