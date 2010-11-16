<?php
header("Content-Type: text/javascript; charset=utf-8");

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $row) {
		$id = js_escape($row["Name"]);
		json_row("Comment-$id", nbsp($row["Comment"]));
		if (!is_view($row)) {
			foreach (array("Engine", "Collation") as $key) {
				json_row("$key-$id", nbsp($row[$key]));
			}
			foreach ($sums + array("Auto_increment" => 0, "Rows" => 0) as $key => $val) {
				if ($row[$key] != "") {
					$val = number_format($row[$key], 0, '.', lang(','));
					json_row("$key-$id", ($key == "Rows" && $row["Engine"] == "InnoDB" && $val ? "~ $val" : $val));
					if (isset($sums[$key])) {
						$sums[$key] += ($row["Engine"] != "InnoDB" || $key != "Data_free" ? $row[$key] : 0);
					}
				} elseif (array_key_exists($key, $row)) {
					json_row("$key-$id");
				}
			}
		}
	}
	foreach ($sums as $key => $val) {
		json_row("sum-$key", number_format($val, 0, '.', lang(',')));
	}
	json_row("");
} else { // connect
	foreach (count_tables(get_databases()) as $db => $val) {
		json_row("tables-" . js_escape($db), $val);
	}
	json_row("");
}

exit; // don't print footer
