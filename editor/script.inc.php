<?php
namespace Adminer;

if ($_GET["script"] == "kill") {
	if (!$error) {
		connection()->query("KILL " . number($_POST["kill"]));
	}

} elseif (list($table, $id, $name) = adminer()->_foreignColumn(column_foreign_keys($_GET["source"]), $_GET["field"])) { // complete
	$limit = 11;
	$exact = (preg_match('~^[0-9]+$~', $_GET["value"]) ? "$id = $_GET[value]" : "");
	// the row with the typed ID first, the limit would cut it off otherwise; CASE - MS SQL and Oracle can't order by ($exact) DESC
	$result = connection()->query("SELECT" . limit(
		"$id, $name FROM " . table($table),
		" WHERE " . ($exact ? "$exact OR " : "") . "$name LIKE " . q("$_GET[value]%") . " ORDER BY " . ($exact ? "CASE WHEN $exact THEN 0 ELSE 1 END, " : "") . "2",
		$limit
	));
	for ($i=1; ($row = $result->fetch_row()) && $i < $limit; $i++) {
		echo "<a href='" . h(ME . "edit=" . url_escape($table) . "&where[" . url_escape(bracket_escape(idf_unescape($id))) . "]=" . url_escape($row[0])) . "'>" . h($row[1]) . "</a><br>\n";
	}
	if ($row) {
		echo "...\n";
	}
}

exit; // don't print footer
