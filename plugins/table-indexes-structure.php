<?php

/** Expanded table indexes structure output
* @link https://www.adminer.org/plugins/#use
* @author Matthew Gamble, https://www.matthewgamble.net/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableIndexesStructure {

	/** Print table structure in tabular format
	* @param Index[] $indexes data about all indexes on a table
	*/
	function tableIndexesPrint(array $indexes): bool {
		echo "<table>\n";
		echo "<thead><tr><th>" . Adminer\lang('Name') . "<th>" . Adminer\lang('Type') . "<th>" . Adminer\lang('Columns') . "</thead>\n";
		foreach ($indexes as $name => $index) {
			echo "<tr><th>" . Adminer\h($name) . "<td>" . $index['type'];
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . Adminer\h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}
			echo "<td>" . implode(", ", $print) . "\n";
		}
		echo "</table>\n";
		return true;
	}
}
