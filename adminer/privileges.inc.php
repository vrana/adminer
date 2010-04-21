<?php
page_header(lang('Privileges'));

$result = $connection->query("SELECT User, Host FROM mysql.user ORDER BY Host, User");
if (!$result) {
	?>
<form action=""><p>
<?php hidden_fields_get(); ?>
<?php echo lang('Username'); ?>: <input name="user">
<?php echo lang('Server'); ?>: <input name="host" value="localhost">
<input type="hidden" name="grant" value="">
<input type="submit" value="<?php echo lang('Edit'); ?>">
</form>
<?php
	// list logged user, information_schema.USER_PRIVILEGES lists just the current user too
	$result = $connection->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");
}
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th>&nbsp;<th>" . lang('Username') . "<th>" . lang('Server') . "</thead>\n";
while ($row = $result->fetch_assoc()) {
	echo '<tr' . odd() . '><td><a href="' . h(ME . 'user=' . urlencode($row["User"]) . '&host=' . urlencode($row["Host"])) . '">' . lang('edit') . '</a><td>' . h($row["User"]) . "<td>" . h($row["Host"]) . "\n";
}
echo "</table>\n";

echo '<p><a href="' . h(ME) . 'user=">' . lang('Create user') . "</a>";
