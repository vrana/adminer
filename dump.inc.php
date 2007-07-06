<?php
header("Content-Type: text/plain; charset=utf-8");

function dump($db) {
	static $routines;
	if (!isset($routines)) {
		$routines = array();
		foreach (array("FUNCTION", "PROCEDURE") as $routine) {
			$result = mysql_query("SHOW $routine STATUS");
			while ($row = mysql_fetch_assoc($result)) {
				if (!strlen($_GET["db"]) || $row["Db"] === $_GET["db"]) {
					$routines[$row["Db"]][] = mysql_result(mysql_query("SHOW CREATE $routine " . idf_escape($row["Db"]) . "." . idf_escape($row["Name"])), 0, 2) . ";\n\n"; //! delimiter
				}
			}
			mysql_free_result($result);
		}
	}
	
	$result = mysql_query("SHOW CREATE DATABASE " . idf_escape($db));
	if ($result) {
		echo mysql_result($result, 0) . ";\n";
		mysql_free_result($result);
	}
	echo "USE " . idf_escape($db) . ";\n";
	echo "SET CHARACTER SET utf8;\n\n";
	$result = mysql_query("SHOW TABLES");
	while ($row = mysql_fetch_row($result)) {
		$result1 = mysql_query("SHOW CREATE TABLE " . idf_escape($row[0]));
		if ($result1) {
			echo mysql_result($result1, 0, 1) . ";\n";
			mysql_free_result($result1);
			$result1 = mysql_query("SELECT * FROM " . idf_escape($row[0])); //! except views //! enum and set as numbers
			if ($result1) {
				while ($row1 = mysql_fetch_row($result1)) {
					echo "INSERT INTO " . idf_escape($row[0]) . " VALUES ('" . implode("', '", array_map('mysql_real_escape_string', $row1)) . "');\n";
				}
				mysql_free_result($result1);
			}
			echo "\n";
		}
	}
	mysql_free_result($result);
	
	$result = mysql_query("SHOW TRIGGERS");
	while ($row = mysql_fetch_assoc($result)) {
		echo "CREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . idf_escape($row["Table"]) . " FOR EACH ROW $row[Statement];\n\n"; //! delimiter
	}
	mysql_free_result($result);
	echo "\n\n";
	
	echo implode("", (array) $routines[$db]);
}

if (strlen($_GET["db"])) {
	dump($_GET["db"]);
} else {
	$result = mysql_query("SHOW DATABASES");
	while ($row = mysql_fetch_assoc($result)) {
		if ($row["Database"] != "information_schema" || mysql_get_server_info() < 5) {
			if (mysql_select_db($row["Database"])) {
				dump($row["Database"]);
			}
		}
	}
	mysql_free_result($result);
}
