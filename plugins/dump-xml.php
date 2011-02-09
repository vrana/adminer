<?php

/** Dump to XML format in structure <database name=""><table name=""><column name="">value
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpXml {
	
	function dumpFormat() {
		return array('xml' => 'XML');
	}

	function dumpTable($table, $style, $is_view = false) {
		if ($_POST["format"] == "xml") {
			return true;
		}
	}
	
	function dumpData($table, $style, $query) {
		if ($_POST["format"] == "xml") {
			echo "<database name='" . h(DB) . "'>\n";
			$connection = connection();
			$result = $connection->query($query, 1);
			if ($result) {
				while ($row = $result->fetch_assoc()) {
					echo "\t<table name='" . h($table) . "'>\n";
					foreach ($row as $key => $val) {
						echo "\t\t<column name='" . h($key) . "'>" . h($val) . "</column>\n";
					}
					echo "\t</table>\n";
				}
			}
			echo "</database>\n";
			return true;
		}
	}

	function dumpHeaders($identifier, $multi_table = false) {
		if ($_POST["format"] == "xml") {
			header("Content-Type: text/xml; charset=utf-8");
			return "xml";
		}
	}

}
