<?php
mysql_result(mysql_query("SHOW CREATE " . ($_GET["type"] == "FUNCTION" ? "FUNCTION" : "PROCEDURE")), 0, 2);
if ($_POST) {
	if (isset($_GET["function"])) {
		
	} else {
		
	}
	$result = mysql_query("CALL " . idf_escape($_GET["call"])); //! params
	if ($result === true) {
		redirect(substr($SELF, 0, -1), lang('Routine has been called, %d row(s) affected.', mysql_affected_rows()));
	} elseif (!$result) {
		$error = mysql_error();
	}
}

page_header(lang('Call') . ": " . htmlspecialchars($_GET["call"]));

if ($_POST) {
	if (!$result) {
		echo "<p class='error'>" . lang('Error during calling') . ": " . htmlspecialchars($error) . "</p>\n";
	} else {
		select($result);
	}
}
?>
<form action="" method="post">
<?php
if ($params) {
	echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
	foreach ($params as $key => $val) {
		echo "<tr><th>" . htmlspecialchars($key) . "</th><td>" . input("param[]", $val["type"]) . "</td></tr>\n";
	}
	echo "</table>\n";
}
?>
<p><input type="hidden" name="token" value="<?php echo $token; ?>" /><input type="submit" value="<?php echo lang('Call'); ?>" /></p>
</form>
