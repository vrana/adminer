<?php

/** Dump to JSON format
 *
 * @link    http://www.adminer.org/plugins/#use
 * @author  Martin Zeman (Zemistr), http://www.zemistr.eu/
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerDumpJson {
	var $output = array();
	var $shutdown_callback = false;

	function dumpFormat() {
		return array('json' => 'JSON');
	}

	function dumpHeaders() {
		if ($_POST["format"] == "json") {
			header("Content-Type: application/json; charset=utf-8");

			return "json";
		}
	}

	function dumpDatabase() {
		if ($_POST['format'] == 'json') {
			if (!$this->shutdown_callback) {
				$this->shutdown_callback = true;
				register_shutdown_function(array($this, '_export'));
			}

			return true;
		}
	}

	function dumpTable($table) {
		if ($_POST['format'] == 'json') {
			$this->output[$table] = array();

			return true;
		}
	}

	function dumpData($table, $style, $query) {
		if ($_POST['format'] == 'json') {
			$connection = connection();
			$result = $connection->query($query, 1);

			if ($result) {
				while ($row = $result->fetch_assoc()) {
					$this->output[$table][] = $row;
				}
			}

			return true;
		}
	}

	function _export() {
		echo json_encode($this->output, 128);
	}
}
