<?php
header("Content-Type: text/javascript; charset=utf-8");

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $table_status) {
		$id = js_escape($table_status["Name"]);
		json_row("Comment-$id", nbsp($table_status["Comment"]));
		if (!is_view($table_status)) {
			foreach (array("Engine", "Collation") as $key) {
				json_row("$key-$id", nbsp($table_status[$key]));
			}
			foreach ($sums + array("Auto_increment" => 0, "Rows" => 0) as $key => $val) {
				if ($table_status[$key] != "") {
					$val = number_format($table_status[$key], 0, '.', lang(','));
					if ($key == "Rows") {
						if (
							$table_status["Engine"] == "InnoDB" ||	// MySQL InnoDB
							$table_status["Engine"] == "table"	// Postgres table reltype
						) {
							$val = "~ $val";
						}
					}
					json_row("$key-$id", $val);
					if (isset($sums[$key])) {
						$sums[$key] += ($table_status["Engine"] != "InnoDB" || $key != "Data_free" ? $table_status[$key] : 0);
					}
				} elseif (array_key_exists($key, $table_status)) {
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
	foreach (count_tables($adminer->databases()) as $db => $val) {
		json_row("tables-" . js_escape($db), $val);
	}
	json_row("");
}

exit; // don't print footer
