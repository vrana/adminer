<?php
if (!$error && $_POST["export"]) {
	dump_headers("sql");
	$adminer->dumpTable("", "");
	$adminer->dumpData("", "table", $_POST["query"]);
	exit;
}

restart_session();
$history_all = &get_session("queries");
$history = &$history_all[DB];
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
	} elseif ($_FILES && $_FILES["sql_file"]["error"] != UPLOAD_ERR_NO_FILE) {
		$query = get_file("sql_file", true);
	}
	if (is_string($query)) { // get_file() returns error as number, fread() as false
		if (function_exists('memory_get_usage')) {
			@ini_set("memory_limit", max(ini_bytes("memory_limit"), 2 * strlen($query) + memory_get_usage() + 8e6)); // @ - may be disabled, 2 - substr and trim, 8e6 - other variables
		}
		if ($query != "" && strlen($query) < 1e6) { // don't add big queries
			$q = $query . (ereg(";[ \t\r\n]*\$", $query) ? "" : ";"); //! doesn't work with DELIMITER |
			if (!$history || reset(end($history)) != $q) { // no repeated queries
				restart_session();
				$history[] = array($q, time());
				set_session("queries", $history_all); // required because reference is unlinked by stop_session()
				stop_session();
			}
		}
		$space = "(?:\\s|/\\*.*\\*/|(?:#|-- )[^\n]*\n|--\n)";
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$connection2 = connect(); // connection for exploring indexes and EXPLAIN (to not replace FOUND_ROWS()) //! PDO - silent error
		if (is_object($connection2) && DB != "") {
			$connection2->select_db(DB);
		}
		$commands = 0;
		$errors = array();
		$line = 0;
		$parse = '[\'"' . ($jush == "sql" ? '`#' : ($jush == "sqlite" ? '`[' : ($jush == "mssql" ? '[' : ''))) . ']|/\\*|-- |$' . ($jush == "pgsql" ? '|\\$[^$]*\\$' : '');
		$total_start = microtime();
		parse_str($_COOKIE["adminer_export"], $adminer_export);
		$dump_format = $adminer->dumpFormat();
		unset($dump_format["sql"]);
		while ($query != "") {
			if (!$offset && preg_match("~^$space*DELIMITER\\s+(\\S+)~i", $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));
			} else {
				preg_match('(' . preg_quote($delimiter) . "\\s*|$parse)", $query, $match, PREG_OFFSET_CAPTURE, $offset); // should always match
				list($found, $pos) = $match[0];
				if (!$found && $fp && !feof($fp)) {
					$query .= fread($fp, 1e5);
				} else {
					if (!$found && rtrim($query) == "") {
						break;
					}
					$offset = $pos + strlen($found);
					if ($found && rtrim($found) != $delimiter) { // find matching quote or comment end
						while (preg_match('(' . ($found == '/*' ? '\\*/' : ($found == '[' ? ']' : (ereg('^-- |^#', $found) ? "\n" : preg_quote($found) . "|\\\\."))) . '|$)s', $query, $match, PREG_OFFSET_CAPTURE, $offset)) { //! respect sql_mode NO_BACKSLASH_ESCAPES
							$s = $match[0][0];
							if (!$s && $fp && !feof($fp)) {
								$query .= fread($fp, 1e5);
							} else {
								$offset = $match[0][1] + strlen($s);
								if ($s[0] != "\\") {
									break;
								}
							}
						}
					} else { // end of a query
						$empty = false;
						$q = substr($query, 0, $pos);
						$commands++;
						$print = "<pre id='sql-$commands'><code class='jush-$jush'>" . shorten_utf8(trim($q), 1000) . "</code></pre>\n";
						if (!$_POST["only_errors"]) {
							echo $print;
							ob_flush();
							flush(); // can take a long time - show the running query
						}
						$start = microtime(); // microtime(true) is available since PHP 5
						//! don't allow changing of character_set_results, convert encoding of displayed query
						if ($connection->multi_query($q) && is_object($connection2) && preg_match("~^$space*USE\\b~isU", $q)) {
							$connection2->query($q);
						}
						do {
							$result = $connection->store_result();
							$end = microtime();
							$time = format_time($start, $end) . (strlen($q) < 1000 ? " <a href='" . h(ME) . "sql=" . urlencode(trim($q)) . "'>" . lang('Edit') . "</a>" : ""); // 1000 - maximum length of encoded URL in IE is 2083 characters
							if ($connection->error) {
								echo ($_POST["only_errors"] ? $print : "");
								echo "<p class='error'>" . lang('Error in query') . ": " . error() . "\n";
								$errors[] = " <a href='#sql-$commands'>$commands</a>";
								if ($_POST["error_stops"]) {
									break 2;
								}
							} elseif (is_object($result)) {
								$orgtables = select($result, $connection2);
								if (!$_POST["only_errors"]) {
									echo "<form action='' method='post'>\n";
									echo "<p>" . ($result->num_rows ? lang('%d row(s)', $result->num_rows) : "") . $time;
									$id = "export-$commands";
									$export = ", <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('Export') . "</a><span id='$id' class='hidden'>: "
										. html_select("output", $adminer->dumpOutput(), $adminer_export["output"]) . " "
										. html_select("format", $dump_format, $adminer_export["format"])
										. "<input type='hidden' name='query' value='" . h($q) . "'>"
										. " <input type='submit' name='export' value='" . lang('Export') . "'><input type='hidden' name='token' value='$token'></span>\n"
									;
									if ($connection2 && preg_match("~^($space|\\()*SELECT\\b~isU", $q) && ($explain = explain($connection2, $q))) {
										$id = "explain-$commands";
										echo ", <a href='#$id' onclick=\"return !toggle('$id');\">EXPLAIN</a>$export";
										echo "<div id='$id' class='hidden'>\n";
										select($explain, $connection2, ($jush == "sql" ? "http://dev.mysql.com/doc/refman/" . substr($connection->server_info, 0, 3) . "/en/explain-output.html#explain_" : ""), $orgtables);
										echo "</div>\n";
									} else {
										echo $export;
									}
									echo "</form>\n";
								}
							} else {
								if (preg_match("~^$space*(CREATE|DROP|ALTER)$space+(DATABASE|SCHEMA)\\b~isU", $q)) {
									restart_session();
									set_session("dbs", null); // clear cache
									stop_session();
								}
								if (!$_POST["only_errors"]) {
									echo "<p class='message' title='" . h($connection->info) . "'>" . lang('Query executed OK, %d row(s) affected.', $connection->affected_rows) . "$time\n";
								}
							}
							$start = $end;
						} while ($connection->next_result());
						$line += substr_count($q.$found, "\n");
						$query = substr($query, $offset);
						$offset = 0;
					}
				}
			}
		}
		if ($empty) {
			echo "<p class='message'>" . lang('No commands to execute.') . "\n";
		} elseif ($_POST["only_errors"]) {
			echo "<p class='message'>" . lang('%d query(s) executed OK.', $commands - count($errors)) . format_time($total_start, microtime()) . "\n";
		} elseif ($errors && $commands > 1) {
			echo "<p class='error'>" . lang('Error in query') . ": " . implode("", $errors) . "\n";
		}
		//! MS SQL - SET SHOWPLAN_ALL OFF
	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data" id="form">
<p><?php
$q = $_GET["sql"]; // overwrite $q from if ($_POST) to save memory
if ($_POST) {
	$q = $_POST["query"];
} elseif ($_GET["history"] == "all") {
	$q = $history;
} elseif ($_GET["history"] != "") {
	$q = $history[$_GET["history"]][0];
}
textarea("query", $q, 20);
echo ($_POST ? "" : "<script type='text/javascript'>document.getElementsByTagName('textarea')[0].focus();</script>\n");
echo "<p>" . (ini_bool("file_uploads")
	? lang('File upload') . ': <input type="file" name="sql_file"' . ($_FILES && $_FILES["sql_file"]["error"] != 4 ? '' : ' onchange="this.form[\'only_errors\'].checked = true;"') . '> (&lt; ' . ini_get("upload_max_filesize") . 'B)' // ignore post_max_size because it is for all form fields together and bytes computing would be necessary
	: lang('File uploads are disabled.')
);

?>
<p>
<input type="submit" value="<?php echo lang('Execute'); ?>" title="Ctrl+Enter">
<input type="hidden" name="token" value="<?php echo $token; ?>">
<?php
echo checkbox("error_stops", 1, $_POST["error_stops"], lang('Stop on error')) . "\n";
echo checkbox("only_errors", 1, $_POST["only_errors"], lang('Show only errors')) . "\n";

print_fieldset("webfile", lang('From server'), $_POST["webfile"], "document.getElementById('form')['only_errors'].checked = true; ");
$compress = array();
foreach (array("gz" => "zlib", "bz2" => "bz2") as $key => $val) {
	if (extension_loaded($val)) {
		$compress[] = ".$key";
	}
}
echo lang('Webserver file %s', "<code>adminer.sql" . ($compress ? "[" . implode("|", $compress) . "]" : "") . "</code>");
echo ' <input type="submit" name="webfile" value="' . lang('Run file') . '">';
echo "</div></fieldset>\n";

if ($history) {
	print_fieldset("history", lang('History'), $_GET["history"] != "");
	foreach ($history as $key => $val) {
		list($q, $time) = $val;
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . lang('Edit') . "</a> <span class='time'>" . @date("H:i:s", $time) . "</span> <code class='jush-$jush'>" . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace('~^(#|-- ).*~m', '', $q)))), 80, "</code>") . "<br>\n"; // @ - time zone may be not set
	}
	echo "<input type='submit' name='clear' value='" . lang('Clear') . "'>\n";
	echo "<a href='" . h(ME . "sql=&history=all") . "'>" . lang('Edit all') . "</a>\n";
	echo "</div></fieldset>\n";
}
?>

</form>
