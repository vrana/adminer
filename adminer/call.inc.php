<?php

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
		if (in_array($key, $in)) {
			$val = process_input($field);
			if ($val === false) {
				$val = "''";
			}
			if (isset($out[$key])) {
				$connection->query("SET @" . idf_escape($field["field"]) . " = $val");
			}
		}
		$call[] = (isset($out[$key]) ? "@" . idf_escape($field["field"]) : $val);
	}

	$query = (isset($_GET["callf"]) ? "SELECT" : "CALL") . " " . table($PROCEDURE) . "(" . implode(", ", $call) . ")";
	$start = microtime(true);
	$result = $connection->multi_query($query);
	$affected = $connection->affected_rows; // getting warnings overwrites this
	echo $adminer->selectQuery($query, $start, !$result);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		$connection2 = connect();
		if (is_object($connection2)) {
			$connection2->select_db(DB);
		}

		do {
			$result = $connection->store_result();
			if (is_object($result)) {
				select($result, $connection2);
			} else {
				echo "<p class='message'>" . lang('Routine has been called, %d row(s) affected.', $affected)
					. " <span class='time'>" . @date("H:i:s") . "</span>\n" // @ - time zone may be not set
				;
			}
		} while ($connection->next_result());

		if ($out) {
			select($connection->query("SELECT " . implode(", ", $out)));
		}
	}
}

echo "<form action='' method='post'>\n";

if ($in) {
	echo "<table cellspacing='0' class='layout'>\n";
	foreach ($in as $key) {
		$field = $routine["fields"][$key];
		$name = $field["field"];
		echo "<tr><th>" . $adminer->fieldName($field);
		$value = $_POST["fields"][$name];
		if ($value != "") {
			if ($field["type"] == "enum") {
				$value = +$value;
			}
			if ($field["type"] == "set") {
				$value = array_sum($value);
			}
		}
		input($field, $value, (string) $_POST["function"][$name]); // param name can be empty
		echo "\n";
	}
	echo "</table>\n";
}

echo "<p>",
	"<input type='submit' value='", lang('Call'), "'>",
	"<input type='hidden' name='token' value='$token'>",
	"</p>\n",
	"</form>\n";

$comment = $routine["comment"];
if ($comment !== null && $comment !== "") {
	$comment = h(trim($routine["comment"], "\n"));

	// Remove indenting of all lines (used in MySQL routines in 'sys' database).
	if (preg_match('~^ +~', $comment, $matches)) {
		preg_match_all("~^($matches[0]|$)~m", $comment, $linesWithIndent);

		if (count($linesWithIndent[0]) == substr_count($comment, "\n")) {
			$comment = preg_replace("~^($matches[0])~m", "", $comment);
		}
	}

	// Format common headlines (used in MySQL routines in 'sys' database).
	$comment = preg_replace('~(^|[^\n]\n)(Description|Parameters|Example)\n~', "$1\n<strong>$2</strong>\n", $comment);

	echo "<pre class='comment'>$comment</pre>\n";
}
