<?php
$TABLE = $_GET["model"];
$name = $_GET["name"];
$row = (array) model($name, $TABLE);

if ($_POST) {
    if (!$error && $_POST["Name"] && $_POST["Model"]) {
        if ($_POST["drop"]) {
            $location = ME . "table=" . urlencode($TABLE);
            model_drop($TABLE, $name);
            redirect($location, lang('Model has been dropped.'));
        } else {
            if ($name != "") {
                $model = json_decode($_POST["Model"], true);
                if (!$model) {
                    $error = lang('Model is not valid JSON.');
                } else {
                    model_save($name, $_POST["Name"], $model);
                    $location = ME . "model=" . urlencode($TABLE) . '&name=' . urlencode($_POST["Name"]);
                    redirect($location, lang('Model has been updated.'));
                }
            }
        }
    }
    $row = $_POST;
}

page_header(($name != "" ? lang('Alter model') . ": " . h($name) : lang('Create model')), $error, ["table" => $TABLE]);
$model_base = json_encode(model_from_table($TABLE, $name), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>

<form action="" method="post" id="form" style="width:60rem;">
	<p><?php echo lang('Name'); ?>: <input
			name="Name"
			value="<?php echo h($row["Name"]); ?>"
			data-maxlength="64" autocapitalize="off">
		<?php echo script("qs('#form')['Timing'].onchange();"); ?>
	<p><?php monaco("Model", $row["Model"], 25); ?>
    <p style="display:none;"><textarea rows="10" cols="80" disabled class="sqlarea jush-sql" spellcheck="false" wrap="off"><?= h($model_base) ?></textarea>
	<p>
		<input type="submit"
			value="<?php echo lang('Save'); ?>">
		<?php if ($name != "") { ?><input
			type="submit" name="drop"
			value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $name)); ?><?php } ?>
		<input type="hidden" name="token"
			value="<?php echo $token; ?>">
</form>
