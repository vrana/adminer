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
				echo "<pre class='jush-sql'>" . htmlspecialchars(substr($query, 0, $match[0][1])) . "</pre>\n";
				if (!$mysql->multi_query(substr($query, 0, $match[0][1]))) {
					echo "<p class='error'>" . lang('Error in query') . ": " . htmlspecialchars($mysql->error) . "</p>\n";
				} else{
					do {
						$result = $mysql->store_result();
						if (is_object($result)) {
							select($result);
						} else {
							echo "<p class='message'>" . lang('Query executed OK, %d row(s) affected.', $mysql->affected_rows) . "</p>\n";
						}
					} while ($mysql->next_result());
				}
				$query = substr($query, $match[0][1] + strlen($match[0][0]));
				$offset = 0;
			}
		}
	}
	if ($empty) {
		echo "<p class='message'>" . lang('No commands to execute.') . "</p>\n";
	}
} elseif ($_POST) {
	echo "<p class='error'>" . lang('Unable to upload a file.') . "</p>\n";
}
?>

<form action="" method="post">
<p><textarea name="query" rows="20" cols="80"><?php echo htmlspecialchars($_POST["query"]); ?></textarea></p>
<p><input type="hidden" name="token" value="<?php echo $token; ?>" /><input type="submit" value="<?php echo lang('Execute'); ?>" /></p>
</form>

<?php
if (!ini_get("file_uploads")) {
	echo "<p>" . lang('File uploads are disabled.') . "</p>\n";
} else { ?>
<form action="" method="post" enctype="multipart/form-data">
<p>
<?php echo lang('File upload'); ?>: <input type="file" name="sql_file" />
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Execute'); ?>" />
</p>
</form>
<?php } ?>
