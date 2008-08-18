<?php
function dump_csv($row) {
	foreach ($row as $key => $val) {
		if (preg_match("~[\"\n,]~", $val)) {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(",", $row) . "\n";
}

function dump_table($table, $style) {
	global $mysql, $max_packet, $types;
	if ($_POST["format"] == "csv") {
		echo "\xef\xbb\xbf";
		if ($style) {
			dump_csv(array_keys(fields($table)));
		}
	} elseif ($style) {
		$result = $mysql->query("SHOW CREATE TABLE " . idf_escape($table));
		if ($result) {
			if ($style == "DROP, CREATE") {
				echo "DROP TABLE " . idf_escape($table) . ";\n";
			}
			$create = $mysql->result($result, 1);
			echo ($style == "CREATE, ALTER" ? preg_replace('~^CREATE TABLE ~', '\\0IF NOT EXISTS ', $create) : $create) . ";\n\n";
			if ($max_packet < 1073741824) { // protocol limit
				$row_size = 21 + strlen(idf_escape($table));
				foreach (fields($table) as $field) {
					$type = $types[$field["type"]];
					$row_size += 5 + ($field["length"] ? $field["length"] : $type) * (preg_match('~char|text|enum~', $field["type"]) ? 3 : 1); // UTF-8 in MySQL uses up to 3 bytes
				}
				if ($row_size > $max_packet) {
					$max_packet = 1024 * ceil($row_size / 1024);
					echo "SET max_allowed_packet = $max_packet, GLOBAL max_allowed_packet = $max_packet;\n";
				}
			}
			$result->free();
		}
		if ($mysql->server_info >= 5) {
			$result = $mysql->query("SHOW TRIGGERS LIKE '" . $mysql->escape_string(addcslashes($table, "%_")) . "'");
			if ($result->num_rows) {
				echo "DELIMITER ;;\n\n";
				while ($row = $result->fetch_assoc()) {
					echo "CREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . idf_escape($row["Table"]) . " FOR EACH ROW $row[Statement];;\n\n";
				}
				echo "DELIMITER ;\n\n";
			}
			$result->free();
		}
	}
}

function dump_data($table, $style, $from = "") {
	global $mysql, $max_packet;
	if ($style) {
		if ($_POST["format"] != "csv" && $style == "TRUNCATE, INSERT") {
			echo "TRUNCATE " . idf_escape($table) . ";\n";
		}
		$result = $mysql->query("SELECT * " . ($from ? $from : "FROM " . idf_escape($table))); //! enum and set as numbers, binary as _binary, microtime
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
		if ($_POST["format"] != "csv") {
			echo "\n";
		}
	}
}

function dump_headers($identifier, $multi_table = false) {
	$filename = (strlen($identifier) ? preg_replace('~[^a-z0-9_]~i', '-', $identifier) : "dump");
	$ext = ($_POST["format"] == "sql" ? "sql" : ($multi_table ? "tar" : "csv"));
	header("Content-Type: " . ($ext == "tar" ? "application/x-tar" : ($ext == "sql" || $_POST["output"] != "file" ? "text/plain" : "text/csv")) . "; charset=utf-8");
	header("Content-Disposition: " . ($_POST["output"] == "file" ? "attachment" : "inline") . "; filename=$filename.$ext");
	return $ext;
}

$dump_options = lang('Output') . ": <select name='output'><option value='text'>" . lang('open') . "</option><option value='file'>" . lang('save') . "</option></select> " . lang('Format') . ": <select name='format'><option value='sql'>" . lang('SQL') . "</option><option value='csv'>" . lang('CSV') . "</option></select>";
$max_packet = 0;
