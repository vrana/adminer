<?php
namespace Adminer;

page_header(lang('Privileges'));

echo '<p class="links"><a href="' . h(ME) . 'user=">' . lang('Create user') . "</a>";

$result = connection()->query("SELECT User, Host FROM mysql." . (DB == "" ? "user" : "db WHERE " . q(DB) . " LIKE Db") . " ORDER BY Host, User");
$grant = $result;
if (!$result) {
	// list logged user, information_schema.USER_PRIVILEGES lists just the current user too
	$result = connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");
}

echo "<form action=''><p>\n";
hidden_fields_get();
echo input_hidden("db", DB);
echo ($grant ? "" : input_hidden("grant"));
echo "<table class='odds'>\n";
echo "<thead><tr><th>" . lang('Username') . "<th>" . lang('Server') . "<td class='hover'><tbody>\n";

while ($row = $result->fetch_assoc()) {
	echo '<tr><td>' . h($row["User"]);
	echo "<td>" . h($row["Host"]);
	echo '<td class="hover"><a href="' . h(ME . 'user=' . url_escape($row["User"]) . '&host=' . url_escape($row["Host"])) . '">' . lang('Edit') . "</a>\n";
}

if (!$grant || DB != "") {
	echo "<tr><td><input name='user' autocapitalize='off'><td><input name='host' value='localhost' autocapitalize='off'><td><input type='submit' value='" . lang('Edit') . "'>\n";
}

echo "</table>\n";
echo "</form>\n";
