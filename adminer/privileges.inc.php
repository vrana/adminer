<?php
page_header(lang('Privileges'));

$result = $connection->query("SELECT User, Host FROM mysql." . (DB == "" ? "user" : "db WHERE " . q(DB) . " LIKE Db") . " ORDER BY Host, User");
$grant = $result;
if (!$result) {
	// list logged user, information_schema.USER_PRIVILEGES lists just the current user too
	$result = $connection->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");
}
echo "<form action=''><p>\n";
hidden_fields_get();
echo "<input type='hidden' name='db' value='" . h(DB) . "'>\n";
echo ($grant ? "" : "<input type='hidden' name='grant' value=''>\n");
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th>" . lang('Username') . "<th>" . lang('Server') . "<th>&nbsp;</thead>\n";
while ($row = $result->fetch_assoc()) {
	echo '<tr' . odd() . '><td>' . h($row["User"]) . "<td>" . h($row["Host"]) . '<td><a href="' . h(ME . 'user=' . urlencode($row["User"]) . '&host=' . urlencode($row["Host"])) . '">' . lang('Edit') . "</a>\n";
}
if (!$grant || DB != "") {
	echo "<tr" . odd() . "><td><input name='user'><td><input name='host' value='localhost'><td><input type='submit' value='" . lang('Edit') . "'>\n";
}
echo "</table>\n";
echo "</form>\n";

echo '<p><a href="' . h(ME) . 'user=">' . lang('Create user') . "</a>";
