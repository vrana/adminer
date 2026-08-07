<?php
namespace Adminer;

header("Content-Type: application/json; charset=utf-8");

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $name => $table_status) {
		json_row("Comment-$name", h($table_status["Comment"]));
		if (!is_view($table_status) || preg_match('~materialized~i', $table_status["Engine"])) {
			foreach (array("Engine", "Collation") as $key) {
				json_row("$key-$name", h($table_status[$key]));
			}
			foreach (array_keys($sums + array("Auto_increment" => 0, "Rows" => 0)) as $key) {
				if (array_key_exists($key, $table_status)) {
					json_row("$key-$name", format_status($table_status, $key));
				}
				if ($table_status[$key] != "" && isset($sums[$key])) {
					// ignore innodb_file_per_table because it is not active for tables created before it was enabled
					$sums[$key] += ($table_status["Engine"] != "InnoDB" || $key != "Data_free" ? $table_status[$key] : 0);
				}
			}
		}
	}
	if (function_exists('Adminer\db_status')) {
		$sums = db_status();
	}
	foreach ($sums as $key => $val) {
		json_row("sum-$key", format_number($val));
	}
	json_row("");

} elseif ($_GET["script"] == "kill") {
	connection()->query("KILL " . number($_POST["kill"]));

} else { // connect
	foreach (count_tables(adminer()->databases(false)) as $db => $val) {
		json_row("tables-$db", $val);
		json_row("size-$db", db_size($db));
	}
	json_row("");
}

exit; // don't print footer
