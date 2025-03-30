<?php
namespace Adminer;

$PROCEDURE = ($_GET["name"] ?: $_GET["call"]);
page_header(lang('Call') . ": " . h($PROCEDURE), $error);

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
		$call[] = (isset($out[$key]) ? "@" . idf_escape($field["field"]) : $val);
	}

	$query = (isset($_GET["callf"]) ? "SELECT" : "CALL") . " " . table($PROCEDURE) . "(" . implode(", ", $call) . ")";
	$start = microtime(true);
	$result = connection()->multi_query($query);
	$affected = connection()->affected_rows; // getting warnings overwrites this
	echo adminer()->selectQuery($query, $start, !$result);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		$connection2 = connect(adminer()->credentials());
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
<input type="submit" value="<?php echo lang('Call'); ?>">
<?php echo input_token(); ?>
</form>

<pre>
<?php
/** Format string as table row
* @return string HTML
*/
function pre_tr(string $s): string {
	return preg_replace('~^~m', '<tr>', preg_replace('~\|~', '<td>', preg_replace('~\|$~m', "", rtrim($s))));
}

$table = '(\+--[-+]+\+\n)';
$row = '(\| .* \|\n)';
echo preg_replace_callback(
	"~^$table?$row$table?($row*)$table?~m",
	function ($match) {
		$first_row = pre_tr($match[2]);
		return "<table>\n" . ($match[1] ? "<thead>$first_row</thead>\n" : $first_row) . pre_tr($match[4]) . "\n</table>";
	},
	preg_replace(
		'~(\n(    -|mysql)&gt; )(.+)~',
		"\\1<code class='jush-sql'>\\3</code>",
		preg_replace('~(.+)\n---+\n~', "<b>\\1</b>\n", h($routine['comment']))
	)
);
?>
</pre>
