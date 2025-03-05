<?php

/** Dump to JSON format
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpJson {
	/** @access protected */
	var $database = false;

	function dumpFormat() {
		return array('json' => 'JSON');
	}

	function dumpTable($table, $style, $is_view = 0) {
		if ($_POST["format"] == "json") {
			return true;
		}
	}

	function _database() {
		echo "}\n";
	}

	function dumpData($table, $style, $query) {
		if ($_POST["format"] == "json") {
			if ($this->database) {
				echo ",\n";
			} else {
				$this->database = true;
				echo "{\n";
				register_shutdown_function(array($this, '_database'));
			}
			$connection = Adminer\connection();
			$result = $connection->query($query, 1);
			if ($result) {
				echo '"' . addcslashes($table, "\r\n\"\\") . "\": [\n";
				$first = true;
				while ($row = $result->fetch_assoc()) {
					echo ($first ? "" : ", ");
					$first = false;
					foreach ($row as $key => $val) {
						Adminer\json_row($key, $val);
					}
					Adminer\json_row("");
				}
				echo "]";
			}
			return true;
		}
	}

	function dumpHeaders($identifier, $multi_table = false) {
		if ($_POST["format"] == "json") {
			header("Content-Type: application/json; charset=utf-8");
			return "json";
		}
	}
}
