<?php
$length = '(?:[^\'")]*|\'(?:[^\\\\\']*|\\.)+\'|"(?:[^\\\\\"]*|\\.)+")+';
$pattern = "\\s*(IN|OUT|INOUT)?\\s*(?:`((?:[^`]*|``)+)`\\s*|\\b(\\S+)\\s+)([a-z]+)(?:\\s*\\(($length)\\))?\\s*(?:zerofill\\s+)?(unsigned)?";
$create = mysql_result(mysql_query("SHOW CREATE " . (isset($_GET["callf"]) ? "FUNCTION" : "PROCEDURE") . " " . idf_escape($_GET["call"])), 0, 2);
preg_match("~\\($pattern(?:\\s*,$pattern)*~is", $create, $match);
$in = array();
$out = array();
$params = array();
preg_match_all("~$pattern~is", $match[0], $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$field = array(
		"field" => str_replace("``", "`", $match[2]) . $match[3],
		"type" => $match[4], //! type aliases
		"length" => $match[5], //! replace \' by '', replace "" by ''
		"unsigned" => ($match[6] ? "unsigned" : ""), // zerofill ignored
		"null" => true,
	);
	if (strcasecmp("out", substr($match[1], -3)) == 0) {
		$out[$i] = "@" . idf_escape($field["field"]) . " AS " . idf_escape($field["field"]);
	}
	if (!$match[1] || strcasecmp("in", substr($match[1], 0, 2)) == 0) {
		$in[] = $i;
	}
	$params[$i] = $field;
}
if ($_POST) {
	$call = array();
	foreach ($params as $key => $field) {
		if (in_array($key, $in)) {
			$val = process_input($key, $field);
			if (isset($out[$key])) {
				mysql_query("SET @" . idf_escape($field["field"]) . " = " . $val);
			}
		}
		$call[] = (isset($out[$key]) ? "@" . idf_escape($field["field"]) : $val);
	}
	$result = mysql_query((isset($_GET["callf"]) ? "SELECT" : "CALL") . " " . idf_escape($_GET["call"]) . "(" . implode(", ", $call) . ")");
	if (!$result) {
		$error = mysql_error();
	} elseif ($result === true) {
		$message = lang('Routine has been called, %d row(s) affected.', mysql_affected_rows());
		if (!$out) {
			redirect(substr($SELF, 0, -1), $message);
		}
	}
}

page_header(lang('Call') . ": " . htmlspecialchars($_GET["call"]));

if ($_POST) {
	if (!$result) {
		echo "<p class='error'>" . lang('Error during calling') . ": " . htmlspecialchars($error) . "</p>\n";
	} else {
		if ($result === true) {
			echo "<p class='message'>$message</p>\n";
		} else {
			select($result);
		}
		if ($out) {
			select(mysql_query("SELECT " . implode(", ", $out)));
		}
	}
}
?>

<form action="" method="post">
<?php
if ($in) {
	echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
	foreach ($in as $key) {
		$field = $params[$key];
		echo "<tr><th>" . htmlspecialchars($field["field"]) . "</th><td>";
		$value = $_POST["fields"][$key];
		if (strlen($value) && ($field["type"] == "enum" || $field["type"] == "set")) {
			$value = intval($value);
		}
		input($key, $field, $value); // param name can be empty
		echo "</td></tr>\n";
	}
	echo "</table>\n";
}
?>
<p><input type="hidden" name="token" value="<?php echo $token; ?>" /><input type="submit" value="<?php echo lang('Call'); ?>" /></p>
</form>
