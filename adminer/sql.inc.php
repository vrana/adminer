<?php
$history = &$_SESSION["history"][$_GET["server"]][DB];
if (!$error && $_POST["clear"]) {
	$history = array();
	redirect(remove_from_uri("history"));
}

page_header(lang('SQL command'), $error);

if (!$error && $_POST) {
	$fp = false;
	$query = $_POST["query"];
	if ($_POST["webfile"]) {
		$fp = @fopen((file_exists("adminer.sql") ? "adminer.sql"
			: (file_exists("adminer.sql.gz") ? "compress.zlib://adminer.sql.gz"
			: "compress.bzip2://adminer.sql.bz2"
		)), "rb");
		$query = ($fp ? fread($fp, 1e6) : false);
	} elseif ($_POST["file"]) {
		$query = get_file("sql_file", true);
	}
	if (is_string($query)) { // get_file() returns error as number, fread() as false
		if (!$fp && strlen($query) && (!$history || end($history) != $query)) { // don't add repeated 
			$history[] = $query;
		}
		$space = "(\\s|/\\*.*\\*/|(#|-- )[^\n]*\n|--\n)";
		$alter_database = "(CREATE|DROP)$space+(DATABASE|SCHEMA)\\b~isU";
		$databases = &$_SESSION["databases"][$_GET["server"]];
		if (isset($databases) && !preg_match("~\\b$alter_database", $query)) { // quick check - may be inside string
			//! false positive with $fp
			session_write_close();
		}
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$dbh2 = (strlen(DB) ? connect() : null); // connection for exploring indexes and EXPLAIN (to not replace FOUND_ROWS()) //! PDO - silent error
		if (is_object($dbh2)) {
			$dbh2->select_db(DB);
		}
		$explain = 1;
		while (strlen($query)) {
			if (!$offset && preg_match('~^\\s*DELIMITER\\s+(.+)~i', $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));
			} else {
				preg_match('(' . preg_quote($delimiter) . '|[\'`"]|/\\*|-- |#|$)', $query, $match, PREG_OFFSET_CAPTURE, $offset); // should always match
				$found = $match[0][0];
				$offset = $match[0][1] + strlen($found);
				if (!$found && $fp && !feof($fp)) {
					$query .= fread($fp, 1e6);
				} else {
					if (!$found && !strlen(rtrim($query))) {
						break;
					}
					if (!$found || $found == $delimiter) { // end of a query
						$empty = false;
						$q = substr($query, 0, $match[0][1]);
						echo "<pre class='jush-sql'>" . shorten_utf8(trim($q)) . "</pre>\n";
						ob_flush();
						flush(); // can take a long time - show the running query
						$start = explode(" ", microtime()); // microtime(true) is available since PHP 5
						//! don't allow changing of character_set_results, convert encoding of displayed query
						if (!$dbh->multi_query($q)) {
							echo "<p class='error'>" . lang('Error in query') . ": " . h($dbh->error) . "\n";
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
									if (preg_match("~^$space*SELECT$space+~isU", $q)) {
										$id = "explain-$explain";
										echo "<p>" . lang('%d row(s)', $result->num_rows) . ", <a href='#$id' onclick=\"return !toggle('$id');\">EXPLAIN</a>\n";
										echo "<div id='$id' class='hidden'>\n";
										select($dbh2->query("EXPLAIN $q"));
										echo "</div>\n";
										$explain++;
									}
								} else {
									if (preg_match("~^$space*$alter_database", $query)) {
										$databases = null; // clear cache
									}
									echo "<p class='message'>" . lang('Query executed OK, %d row(s) affected.', $dbh->affected_rows) . "\n";
								}
								unset($result); // free resultset
							} while ($dbh->next_result());
						}
						$query = substr($query, $offset);
						$offset = 0;
					} else { // find matching quote or comment end
						while (preg_match('~' . ($found == '/*' ? '\\*/' : (ereg('-- |#', $found) ? "\n" : "$found|\\\\.")) . '|$~s', $query, $match, PREG_OFFSET_CAPTURE, $offset)) { //! respect sql_mode NO_BACKSLASH_ESCAPES
							$s = $match[0][0];
							$offset = $match[0][1] + strlen($s);
							if (!$s && $fp && !feof($fp)) {
								$query .= fread($fp, 1e6);
							} elseif ($s[0] != "\\") {
								break;
							}
						}
					}
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
<p><textarea name="query" rows="20" cols="80" style="width: 98%;"><?php echo h($_POST ? $_POST["query"] : (strlen($_GET["history"]) ? $history[$_GET["history"]] : $_GET["sql"])); ?></textarea>
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

<p><?php echo lang('Webserver file %s', '<code>adminer.sql</code>'); ?> <input type="submit" name="webfile" value="<?php echo lang('Run file'); ?>">

<?php
if ($history) {
	echo "<fieldset><legend>" . lang('History') . "</legend>\n";
	foreach ($history as $key => $val) {
		//! save and display timestamp
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . lang('Edit') . '</a> <code class="jush-sql">' . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace('~^(#|-- ).*~m', '', $val)))), 80, "</code>") . "<br>\n";
	}
	echo "<input type='submit' name='clear' value='" . lang('Clear') . "'>\n";
	echo "</fieldset>\n";
}
?>

</form>
