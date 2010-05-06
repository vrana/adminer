<?php
page_header(lang('Server'), "", false);

?>
<form action=""><p>
<?php echo lang('Search data in tables'); ?>:
<?php hidden_fields_get(); ?>
<input name="where[0][val]" value="<?php echo h($_GET["where"][0]["val"]); ?>">
<input type="submit" value="<?php echo lang('Search'); ?>" />
</form>
<?php
if ($_GET["where"][0]["val"] != "") {
	search_tables();
}
