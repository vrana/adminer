<?php
$history = &$_SESSION["history"][$_GET["server"]][$_GET["db"]];
if (!$error && $_POST["clear"]) {
	$history = array();
	redirect(remove_from_uri("history"));
}

page_header(lang('SQL command'), $error);

if (!$error && $_POST) {
	$query = (isset($_POST["file"]) ? get_file("sql_file") : $_POST["query"]);
	if (is_string($query)) { // get_file() returns error as number
		@set_time_limit(0); // set_time_limit() can be disabled
		$query = str_replace("\r", "", $query); // parser looks for \n
		$query = rtrim($query);
		if (strlen($query) && (!$history || end($history) != $query)) { // don't add repeated 
			$history[] = $query;
		}
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$space = "(\\s|/\\*.*\\*/|(#|-- )[^\n]*\n|--\n)";
		$dbh2 = (strlen($_GET["db"]) ? connect() : null); // connection for exploring indexes (to not replace FOUND_ROWS()) //! PDO - silent error
		if (is_object($dbh2)) {
			$dbh2->select_db($_GET["db"]);
		}
		while (strlen($query)) {
			if (!$offset && preg_match('~^\\s*DELIMITER\\s+(.+)~i', $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));
			} elseif (preg_match('(' . preg_quote($delimiter) . '|[\'`"]|/\\*|-- |#|$)', $query, $match, PREG_OFFSET_CAPTURE, $offset)) {
				if ($match[0][0] && $match[0][0] != $delimiter) {
					// is not end of a query - find closing part
					$pattern = ($match[0][0] == "-- " || $match[0][0] == "#" ? '~.*~' : ($match[0][0] == "/*" ? '~.*\\*/~sU' : '~\\G([^\\\\' . $match[0][0] . ']|\\\\.)*(' . $match[0][0] . '|$)~s')); //! respect sql_mode NO_BACKSLASH_ESCAPES
					preg_match($pattern, $query, $match, PREG_OFFSET_CAPTURE, $match[0][1] + 1);
					$offset = $match[0][1] + strlen($match[0][0]);
				} else {
					$empty = false;
					echo "<pre class='jush-sql'>" . shorten_utf8(trim(substr($query, 0, $match[0][1]))) . "</pre>\n";
					ob_flush();
					flush(); // can take a long time - show the running query
					$start = explode(" ", microtime()); // microtime(true) is available since PHP 5
					//! don't allow changing of character_set_results, convert encoding of displayed query
					if (!$dbh->multi_query(substr($query, 0, $match[0][1]))) {
						echo "<p class='error'>" . lang('Error in query') . ": " . h($dbh->error) . "\n";
						if ($_POST["error_stops"]) {
							break;
						}
					} else {
						$end = explode(" ", microtime());
						$i = 0;
						do {
							$result = $dbh->store_result();
							if (!$i) {
								echo "<p class='time'>" . (is_object($result) ? lang('%d row(s)', $result->num_rows) . ", ": "") . lang('%.3f s', max(0, $end[0] - $start[0] + $end[1] - $start[1])) . "\n";
								$i++;
							}
							if (is_object($result)) {
								select($result, $dbh2);
							} else {
								if (preg_match("~^$space*(CREATE|DROP)$space+(DATABASE|SCHEMA)\\b~isU", $query)) {
									unset($_SESSION["databases"][$_GET["server"]]); // clear cache
								}
								echo "<p class='message'>" . lang('Query executed OK, %d row(s) affected.', $dbh->affected_rows) . "\n";
							}
						} while ($dbh->next_result());
					}
					$query = substr($query, $match[0][1] + strlen($match[0][0]));
					$offset = 0;
				}
			}
		}
		if ($empty) {
			echo "<p class='message'>" . lang('No commands to execute.') . "\n";
		}
	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data">
<p><textarea name="query" rows="20" cols="80" style="width: 98%;"><?php echo h($_POST ? $_POST["query"] : (strlen($_GET["history"]) ? $_SESSION["history"][$_GET["server"]][$_GET["db"]][$_GET["history"]] : $_GET["sql"])); ?></textarea>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Execute'); ?>">
<label><input type="checkbox" name="error_stops" value="1"<?php echo ($_POST["error_stops"] ? " checked" : ""); ?>><?php echo lang('Stop on error'); ?></label>

<p>
<?php
if (!ini_get("file_uploads")) {
	echo lang('File uploads are disabled.');
} else { ?>
<?php echo lang('File upload'); ?>: <input type="file" name="sql_file">
<input type="submit" name="file" value="<?php echo lang('Run file'); ?>">
<?php } ?>

<?php
if ($history) {
	echo "<fieldset><legend>" . lang('History') . "</legend>\n";
	foreach ($history as $key => $val) {
		//! save and display timestamp
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . lang('Edit') . '</a> <code class="jush-sql">' . shorten_utf8(ltrim(str_replace("\n", " ", preg_replace('~^(#|-- ).*~m', '', $val))), 80, "</code>") . "<br>\n";
	}
	echo "<input type='submit' name='clear' value='" . lang('Clear') . "'>\n";
	echo "</fieldset>\n";
}
?>

</form>
