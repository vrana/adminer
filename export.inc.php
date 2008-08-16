<?php
function dump_csv($row) {
	foreach ($row as $key => $val) {
		if (preg_match("~[\"\n,]~", $val)) {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(",", $row) . "\n";
}

function dump_data($table, $style) {
	global $mysql, $max_packet;
	if ($style) {
		if ($_POST["format"] != "csv" && $style == "TRUNCATE, INSERT") {
			echo "TRUNCATE " . idf_escape($table) . ";\n";
		}
		$result = $mysql->query("SELECT * FROM " . idf_escape($table)); //! enum and set as numbers, binary as _binary, microtime
		if ($result) {
			$insert = "INSERT INTO " . idf_escape($table) . " VALUES ";
			$length = 0;
			while ($row = $result->fetch_assoc()) {
				if ($_POST["format"] == "csv") {
					dump_csv($row);
				} elseif ($style == "UPDATE") {
					$set = array();
					foreach ($row as $key => $val) {
						$row[$key] = (isset($val) ? "'" . $mysql->escape_string($val) . "'" : "NULL");
						$set[] = idf_escape($key) . " = " . (isset($val) ? "'" . $mysql->escape_string($val) . "'" : "NULL");
					}
					echo "INSERT INTO " . idf_escape($table) . " (" . implode(", ", array_map('idf_escape', array_keys($row))) . ") VALUES (" . implode(", ", $row) . ") ON DUPLICATE KEY UPDATE " . implode(", ", $set) . ";\n";
				} else {
					foreach ($row as $key => $val) {
						$row[$key] = (isset($val) ? "'" . $mysql->escape_string($val) . "'" : "NULL");
					}
					$s = "(" . implode(", ", $row) . ")";
					if (!$length) {
						echo $insert, $s;
						$length = strlen($insert) + strlen($s);
					} else {
						$length += 2 + strlen($s);
						if ($length < $max_packet) {
							echo ", ", $s;
						} else {
							echo ";\n", $insert, $s;
							$length = strlen($insert) + strlen($s);
						}
					}
				}
			}
			if ($_POST["format"] != "csv" && $style != "UPDATE" && $result->num_rows) {
				echo ";\n";
			}
			$result->free();
		}
		echo "\n";
	}
}
