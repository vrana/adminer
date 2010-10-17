<?php
header("Content-Type: text/javascript; charset=utf-8");
if ($_GET["token"] != $token) { // CSRF protection
	exit;
}

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $row) {
		$id = addcslashes($row["Name"], "\\'/");
		echo "setHtml('Comment-$id', '" . addcslashes(nbsp($row["Comment"]), "'\\") . "');\n";
		if (!is_view($row)) {
			foreach (array("Engine", "Collation") as $key) {
				echo "setHtml('$key-$id', '" . addcslashes(nbsp($row[$key]), "'\\") . "');\n";
			}
			foreach ($sums + array("Auto_increment" => 0, "Rows" => 0) as $key => $val) {
				if ($row[$key] != "") {
					$val = number_format($row[$key], 0, '.', lang(','));
					echo "setHtml('$key-$id', '" . ($key == "Rows" && $row["Engine"] == "InnoDB" && $val ? "~ $val" : $val) . "');\n";
					if (isset($sums[$key])) {
						$sums[$key] += ($row["Engine"] != "InnoDB" || $key != "Data_free" ? $row[$key] : 0);
					}
				} elseif (array_key_exists($key, $row)) {
					echo "setHtml('$key-$id');\n";
				}
			}
		}
	}
	foreach ($sums as $key => $val) {
		echo "setHtml('sum-$key', '" . number_format($val, 0, '.', lang(',')) . "');\n";
	}
} else { // connect
	foreach (count_tables(get_databases()) as $db => $val) {
		echo "setHtml('tables-" . addcslashes($db, "\\'/") . "', '$val');\n";
	}
}

exit; // don't print footer
