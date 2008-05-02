<?php
header("Content-Type: text/plain; charset=utf-8");
$filename = (strlen($_GET["db"]) ? preg_replace('~[^a-z0-9_]~i', '-', (strlen($_GET["dump"]) ? $_GET["dump"] : $_GET["db"])) : "dump");
header("Content-Disposition: inline; filename=$filename.sql");

function dump_table($table, $data = true) {
	global $mysql, $max_packet;
	$result = $mysql->query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		echo $mysql->result($result, 1) . ";\n\n";
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
		if ($data) {
			$result = $mysql->query("SELECT * FROM " . idf_escape($table)); //! enum and set as numbers, binary as _binary, microtime
			if ($result) {
				if ($result->num_rows) {
					$insert = "INSERT INTO " . idf_escape($table) . " VALUES ";
					$length = 0;
					while ($row = $result->fetch_row()) {
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
					echo ";\n";
				}
				$result->free();
			}
			echo "\n";
		}
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

function dump($db) {
	global $mysql;
	static $routines;
	if (!isset($routines)) {
		$routines = array();
		if ($mysql->server_info >= 5) {
			foreach (array("FUNCTION", "PROCEDURE") as $routine) {
				$result = $mysql->query("SHOW $routine STATUS");
				while ($row = $result->fetch_assoc()) {
					if (!strlen($_GET["db"]) || $row["Db"] === $_GET["db"]) {
						$routines[$row["Db"]][] = $mysql->result($mysql->query("SHOW CREATE $routine " . idf_escape($row["Db"]) . "." . idf_escape($row["Name"])), 2) . ";;\n\n";
					}
				}
				$result->free();
			}
		}
	}
	
	$views = array();
	$result = $mysql->query("SHOW TABLE STATUS");
	while ($row = $result->fetch_assoc()) {
		if (isset($row["Engine"])) {
			dump_table($row["Name"]);
		} else {
			$views[] = $row["Name"];
		}
	}
	$result->free();
	foreach ($views as $view) {
		dump_table($view, false);
	}
	
	if ($routines[$db]) {
		echo "DELIMITER ;;\n\n" . implode("", $routines[$db]) . "DELIMITER ;\n\n";
	}
	
	echo "\n\n";
}

$max_packet = 16777216;
echo "SET NAMES utf8;\n";
echo "SET foreign_key_checks = 0;\n";
echo "SET time_zone = '" . $mysql->escape_string($mysql->result($mysql->query("SELECT @@time_zone"))) . "';\n";
echo "SET max_allowed_packet = $max_packet, GLOBAL max_allowed_packet = $max_packet;\n";
echo "\n";

if (!strlen($_GET["db"])) {
	$result = $mysql->query("SHOW DATABASES");
	while ($row = $result->fetch_assoc()) {
		if ($row["Database"] != "information_schema" || $mysql->server_info < 5) {
			if ($mysql->select_db($row["Database"])) {
				$result1 = $mysql->query("SHOW CREATE DATABASE " . idf_escape($row["Database"]));
				if ($result1) {
					echo $mysql->result($result1, 1) . ";\n";
					$result1->free();
				}
				echo "USE " . idf_escape($row["Database"]) . ";\n";
				dump($row["Database"]);
			}
		}
	}
	$result->free();
} elseif (strlen($_GET["dump"])) {
	dump_table($_GET["dump"]);
} else {
	dump($_GET["db"]);
}
