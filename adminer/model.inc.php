<?php
$TABLE = $_GET["model"];
$name = $_GET["name"] ?? $_POST["Name"];
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
if ($row["Model"] === '{}') {
    $row["Model"] = $model_base;
}
if (empty($row["Name"])) {
    $row["Name"] = $TABLE;
}
?>

<form action="" method="post" id="form" style="width:60rem;">
    <p><?php echo lang('Name'); ?>: <input
            name="Name"
            value="<?php echo h($row["Name"]); ?>"
            data-maxlength="64" autocapitalize="off">
        <?php echo script("qs('#form')['Timing'].onchange();"); ?>
    <p><?php monaco("Model", $row["Model"], 25); ?>
    <p>
        <input type="submit"
            value="<?php echo lang('Save'); ?>">
        <?php if ($name != "") { ?><input
            type="submit" name="drop"
            value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $name)); ?><?php } ?>
        <input type="hidden" name="token"
            value="<?php echo $token; ?>">
        <input type="button"
            value="<?php echo lang('Reset'); ?>"
            onclick="resetModel()">
    </p>
</form>

<script>
    function resetModel() {
        const
            model_base = <?=json_encode($model_base, JSON_UNESCAPED_UNICODE)?> ;
        console.log(model_base);
        window.monaco_Model.setValue(model_base);
    }
</script>