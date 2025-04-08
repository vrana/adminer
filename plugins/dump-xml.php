<?php

/** Dump to XML format in structure <database name=""><table name=""><column name="">value
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpXml extends Adminer\Plugin {
	protected $database = false;

	function dumpFormat() {
		return array('xml' => 'XML');
	}

	function dumpTable($table, $style, $is_view = 0) {
		if ($_POST["format"] == "xml") {
			return true;
		}
	}

	function dumpData($table, $style, $query) {
		if ($_POST["format"] == "xml") {
			if (!$this->database) {
				$this->database = true;
				echo "<database name='" . Adminer\h(Adminer\DB) . "'>\n";
			}
			$result = Adminer\connection()->query($query, 1);
			if ($result) {
				while ($row = $result->fetch_assoc()) {
					echo "\t<table name='" . Adminer\h($table) . "'>\n";
					foreach ($row as $key => $val) {
						echo "\t\t<column name='" . Adminer\h($key) . "'" . (isset($val) ? "" : " null='null'") . ">" . Adminer\h($val) . "</column>\n";
					}
					echo "\t</table>\n";
				}
			}
			return true;
		}
	}

	function dumpHeaders($identifier, $multi_table = false) {
		if ($_POST["format"] == "xml") {
			header("Content-Type: text/xml; charset=utf-8");
			return "xml";
		}
	}

	function dumpFooter() {
		if ($_POST["format"] == "xml" && $this->database) {
			echo "</database>\n";
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Export do formátu XML ve struktuře <database name=""><table name=""><column name="">value'),
		'de' => array('' => 'Export im XML-Format in der Struktur <database name="><table name=""><column name="">value'),
		'pl' => array('' => 'Zrzut do formatu XML w strukturze <database name=""><table name=""><column name="">value'),
		'ro' => array('' => 'Dump în format XML în structura <database name=""><table name=""><column name="">value'),
		'ja' => array('' => '構造化 XML 形式でエクスポート <database name=""><table name=""><column name="">value'),
	);
}
