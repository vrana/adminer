<?php
page_header(lang('SQL command'));

if ($_POST && $error) {
	echo "<p class='error'>$error</p>\n";
} elseif ($_POST && is_string($query = (isset($_POST["query"]) ? $_POST["query"] : get_file("sql_file")))) {
	$delimiter = ";";
	$offset = 0;
	$empty = true;
	while (rtrim($query)) {
		if (!$offset && preg_match('~^\\s*DELIMITER\\s+(.+)~i', $query, $match)) {
			$delimiter = preg_quote($match[1], '~');
			$query = substr($query, strlen($match[0]));
		} elseif (preg_match("~$delimiter|['`\"]|\$~", $query, $match, PREG_OFFSET_CAPTURE, $offset)) {
			if ($match[0][0] && $match[0][0] != $delimiter) {
				preg_match('~\\G([^\\\\' . $match[0][0] . ']*|\\\\.)+(' . $match[0][0] . '|$)~s', $query, $match, PREG_OFFSET_CAPTURE, $match[0][1] + 1);
				$offset = $match[0][1] + strlen($match[0][0]);
			} else {
				$empty = false;
				echo "<pre>" . htmlspecialchars(substr($query, 0, $match[0][1])) . "</pre>\n";
				$result = mysql_query(substr($query, 0, $match[0][1]));
				$query = substr($query, $match[0][1] + strlen($match[0][0]));
				$offset = 0;
				if (!$result) {
					echo "<p class='error'>" . lang('Error in query') . ": " . htmlspecialchars(mysql_error()) . "</p>\n";
				} elseif ($result === true) {
					//~ if (token_delete()) {
						//~ $token = token();
					//~ }
					echo "<p class='message'>" . lang('Query executed OK, %d row(s) affected.', mysql_affected_rows()) . "</p>\n";
				} else {
					select($result);
					mysql_free_result($result);
				}
			}
		}
	}
	if ($empty) {
		echo "<p class='message'>" . lang('No commands to execute.') . "</p>\n";
	}
} elseif ($_GET["sql"] == "upload") {
	echo "<p class='error'>" . lang('Unable to upload a file.') . "</p>\n";
}
?>
<form action="<?php echo htmlspecialchars($SELF); ?>sql=" method="post">
<p><textarea name="query" rows="20" cols="80"><?php echo htmlspecialchars($_POST["query"]); ?></textarea></p>
<p><input type="hidden" name="token" value="<?php echo $token; ?>" /><input type="submit" value="<?php echo lang('Execute'); ?>" /></p>
</form>

<?php
if (!ini_get("file_uploads")) {
	echo "<p>" . lang('File uploads are disabled.') . "</p>\n";
} else { ?>
<form action="<?php echo htmlspecialchars($SELF); ?>sql=upload" method="post" enctype="multipart/form-data">
<p>
<?php echo lang('File upload'); ?>: <input type="file" name="sql_file" />
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Execute'); ?>" />
</p>
</form>
<?php } ?>
