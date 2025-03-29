<?php

/** Dump to PHP format
* @author Martin Zeman (Zemistr), http://www.zemistr.eu/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpPhp {
	protected $output = array();

	function dumpFormat() {
		return array('php' => 'PHP');
	}

	function dumpHeaders() {
		if ($_POST['format'] == 'php') {
			header('Content-Type: text/plain; charset=utf-8');
			return 'php';
		}
	}

	function dumpTable($table, $style, $is_view = 0) {
		if ($_POST['format'] == 'php') {
			$this->output[$table] = array();
			return true;
		}
	}

	function dumpData($table, $style, $query) {
		if ($_POST['format'] == 'php') {
			$result = Adminer\connection()->query($query, 1);
			if ($result) {
				while ($row = $result->fetch_assoc()) {
					$this->output[$table][] = $row;
				}
			}
			return true;
		}
	}

	function dumpFooter() {
		if ($_POST['format'] == 'php') {
			echo "<?php\n";
			var_export($this->output);
			echo ";\n";
		}
	}
}
