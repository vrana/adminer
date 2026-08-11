<?php
namespace Adminer;

$PROCEDURE = ($_GET["name"] ?: $_GET["call"]);
page_header(lang('Call') . ": " . h($PROCEDURE), $error);

$routine_type = (isset($_GET["callf"]) ? "FUNCTION" : "PROCEDURE");
$routine = routine($_GET["call"], $routine_type);
$in = array();
$out = array();
foreach ($routine["fields"] as $i => $field) {
	if (substr($field["inout"], -3) == "OUT" && JUSH == 'sql') {
		$out[$i] = "@" . idf_escape($field["field"]) . " AS " . idf_escape($field["field"]);
	}
	if (!$field["inout"] || substr($field["inout"], 0, 2) == "IN") {
		$in[] = $i;
	}
}

if (!$error && $_POST) {
	$call = array();
	foreach ($routine["fields"] as $key => $field) {
		$val = "";
		if (in_array($key, $in)) {
			$val = process_input($field);
			if ($val === false) {
				$val = "''";
			}
			if (isset($out[$key])) {
				connection()->query("SET @" . idf_escape($field["field"]) . " = $val");
			}
		}
		if (isset($out[$key])) {
			$call[] = "@" . idf_escape($field["field"]);
		} elseif (in_array($key, $in)) {
			$call[] = $val;
		}
	}

	$query = (isset($_GET["callf"]) ? "SELECT " : "CALL ") . (idx($routine["returns"], "type") == "record" ? "* FROM " : "") . table($PROCEDURE) . "(" . implode(", ", $call) . ")";
	$start = microtime(true);
	$result = connection()->multi_query($query);
	$affected = connection()->affected_rows; // getting warnings overwrites this
	echo adminer()->selectQuery($query, $start, !$result);

	if (!$result) {
		echo "<p class='error'>" . adminer()->error() . "\n";
	} else {
		$connection2 = connect();
		if ($connection2) {
			$connection2->select_db(DB);
		}

		do {
			$result = connection()->store_result();
			if (is_object($result)) {
				print_select_result($result, $connection2);
			} else {
				echo "<p class='message'>" . lang('Routine has been called, %d row(s) affected.', $affected)
					. " <span class='time'>" . @date("H:i:s") . "</span>\n" // @ - time zone may be not set
				;
			}
		} while (connection()->next_result());

		if ($out) {
			print_select_result(connection()->query("SELECT " . implode(", ", $out)));
		}
	}
}
?>

<form action="" method="post">
<?php
if ($in) {
	echo "<table class='layout'>\n";
	foreach ($in as $key) {
		$field = $routine["fields"][$key];
		$name = $field["field"];
		echo "<tr><th>" . adminer()->fieldName($field);
		$value = idx($_POST["fields"], $name);
		if ($value != "") {
			if ($field["type"] == "set") {
				$value = implode(",", $value);
			}
		}
		input($field, $value, idx($_POST["function"], $name, "")); // param name can be empty
		echo "\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type='submit' value='<?php echo lang('Call'); ?>'>
<?php echo input_token(); ?>
</form>

<?php echo adminer()->commentValue($routine_type, $routine['comment']); ?>
