<?php
page_header(lang('Server'), "", null);

?>
<form action=""><p>
<?php echo SID_FORM; ?>
<input name="where[0][val]" value="<?php echo h($_GET["where"][0]["val"]); ?>">
<input type="submit" value="<?php echo lang('Search'); ?>" />
</form>
<?php
if ($_GET["where"][0]["val"] != "") {
	search_tables();
}
