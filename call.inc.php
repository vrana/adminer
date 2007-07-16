<?php
page_header(lang('Call') . ": " . htmlspecialchars($_GET["call"]));

$routine = routine($_GET["call"], (isset($_GET["callf"]) ? "FUNCTION" : "PROCEDURE"));
$in = array();
$out = array();
foreach ($routine["fields"] as $i => $field) {
	if (substr($field["inout"], -3) == "OUT") {
		$out[$i] = "@" . idf_escape($field["field"]) . " AS " . idf_escape($field["field"]);
	}
	if (!$field["inout"] || substr($field["inout"], 0, 2) == "IN") {
		$in[] = $i;
	}
}

if ($_POST) {
	$call = array();
	foreach ($routine["fields"] as $key => $field) {
		if (in_array($key, $in)) {
			$val = process_input($key, $field);
			if ($val === false) {
				$val = "''";
			}
			if (isset($out[$key])) {
				$mysql->query("SET @" . idf_escape($field["field"]) . " = " . $val);
			}
		}
		$call[] = (isset($out[$key]) ? "@" . idf_escape($field["field"]) : $val);
	}
	$result = $mysql->multi_query((isset($_GET["callf"]) ? "SELECT" : "CALL") . " " . idf_escape($_GET["call"]) . "(" . implode(", ", $call) . ")");
	if (!$result) {
		echo "<p class='error'>" . lang('Error during calling') . ": " . htmlspecialchars($mysql->error) . "</p>\n";
	} else {
		do {
			$result = $mysql->store_result();
			if (is_object($result)) {
				select($result);
			} else {
				echo "<p class='message'>" . lang('Routine has been called, %d row(s) affected.', $mysql->affected_rows) . "</p>\n";
			}
		} while ($mysql->next_result());
		if ($out) {
			select($mysql->query("SELECT " . implode(", ", $out)));
		}
	}
}
?>

<form action="" method="post">
<?php
if ($in) {
	echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
	foreach ($in as $key) {
		$field = $routine["fields"][$key];
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
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Call'); ?>" />
</p>
</form>
