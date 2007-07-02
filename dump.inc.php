<?php
header("Content-Type: text/plain; charset=utf-8"); //! Content-Disposition

function dump($db) {
	static $routines;
	if (!isset($routines)) {
		$routines = array();
		foreach (array("FUNCTION", "PROCEDURE") as $routine) {
			$result = mysql_query("SHOW $routine STATUS");
			while ($row = mysql_fetch_assoc($result)) {
				if (!strlen($_GET["db"]) || $row["Db"] === $_GET["db"]) {
					$routines[$row["Db"]][] = mysql_result(mysql_query("SHOW CREATE $routine " . idf_escape($row["Db"]) . "." . idf_escape($row["Name"])), 0, 2) . ";\n\n";
				}
			}
			mysql_free_result($result);
		}
	}
	
	//! CREATE DATABASE
	echo "USE $db;\n";
	echo "SET CHARACTER SET utf8;\n\n";
	$result = mysql_query("SHOW TABLES");
	while ($row = mysql_fetch_row($result)) {
		echo mysql_result(mysql_query("SHOW CREATE TABLE " . idf_escape($row[0])), 0, 1) . ";\n\n";
		//! data except views
	}
	mysql_free_result($result);
	
	echo implode("", (array) $routines[$db]); //! delimiter
}

if (strlen($_GET["db"])) {
	dump($_GET["db"]);
} else {
	$result = mysql_query("SHOW DATABASES");
	while ($row = mysql_fetch_assoc($result)) {
		if ($row["Database"] != "information_schema") {
			if (mysql_select_db($row["Database"])) {
				dump($row["Database"]);
			}
		}
	}
	mysql_free_result($result);
}


exit;
