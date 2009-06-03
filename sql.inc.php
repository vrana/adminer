<?php
if (isset($_POST["query"])) {
	setcookie("highlight", $_POST["highlight"], strtotime("+1 month"), preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]));
	$_COOKIE["highlight"] = $_POST["highlight"];
}
page_header(lang('SQL command'), $error);

if (!$error && $_POST) {
	if (is_string($query = (isset($_POST["query"]) ? $_POST["query"] : get_file("sql_file")))) {
		@set_time_limit(0);
		$query = str_replace("\r", "", $query);
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$space = "(\\s+|/\\*.*\\*/|(#|-- )[^\n]*\n|--\n)";
		$dbh2 = (strlen($_GET["db"]) ? connect() : null); // connection for exploring indexes (to not replace FOUND_ROWS()) //! PDO - silent error
		if (is_object($dbh2)) {
			$dbh2->select_db($_GET["db"]);
		}
		while (rtrim($query)) {
			if (!$offset && preg_match('~^\\s*DELIMITER\\s+(.+)~i', $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));
			} elseif (preg_match('(' . preg_quote($delimiter) . '|[\'`"]|/\\*|-- |#|$)', $query, $match, PREG_OFFSET_CAPTURE, $offset)) {
				if ($match[0][0] && $match[0][0] != $delimiter) {
					$pattern = ($match[0][0] == "-- " || $match[0][0] == "#" ? '~.*~' : ($match[0][0] == "/*" ? '~.*\\*/~sU' : '~\\G([^\\\\' . $match[0][0] . ']+|\\\\.)*(' . $match[0][0] . '|$)~s'));
					preg_match($pattern, $query, $match, PREG_OFFSET_CAPTURE, $match[0][1] + 1);
					$offset = $match[0][1] + strlen($match[0][0]);
				} else {
					$empty = false;
					echo "<pre class='jush-sql'>" . htmlspecialchars(substr($query, 0, $match[0][1])) . "</pre>\n";
					flush();
					$start = explode(" ", microtime());
					//! don't allow changing of character_set_results, convert encoding of displayed query
					if (!$dbh->multi_query(substr($query, 0, $match[0][1]))) {
						echo "<p class='error'>" . lang('Error in query') . ": " . htmlspecialchars($dbh->error) . "</p>\n";
						if ($_POST["error_stops"]) {
							break;
						}
					} else {
						$end = explode(" ", microtime());
						echo "<p class='time'>" . lang('%.3f s', max(0, $end[0] - $start[0] + $end[1] - $start[1])) . "</p>\n";
						do {
							$result = $dbh->store_result();
							if (is_object($result)) {
								select($result, $dbh2);
							} else {
								if (preg_match("~^$space*(CREATE|DROP)$space+(DATABASE|SCHEMA)\\b~isU", $query)) {
									unset($_SESSION["databases"][$_GET["server"]]);
								}
								echo "<p class='message'>" . lang('Query executed OK, %d row(s) affected.', $dbh->affected_rows) . "</p>\n";
							}
						} while ($dbh->next_result());
					}
					$query = substr($query, $match[0][1] + strlen($match[0][0]));
					$offset = 0;
				}
			}
		}
		if ($empty) {
			echo "<p class='message'>" . lang('No commands to execute.') . "</p>\n";
		}
	} else {
		echo "<p class='error'>" . lang('Unable to upload a file.') . "</p>\n";
	}
}
?>

<form action="" method="post">
<p><textarea name="query" rows="20" cols="80" style="width: 98%;"><?php echo htmlspecialchars($_POST ? $_POST["query"] : $_GET["sql"]); ?></textarea></p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Execute'); ?>" />
<label><input type="checkbox" name="error_stops" value="1"<?php echo ($_POST["error_stops"] ? " checked='checked'" : ""); ?> /><?php echo lang('Stop on error'); ?></label>
</p>
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
