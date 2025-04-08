<?php

/** Dump to Bzip2 format
* @link https://www.adminer.org/plugins/#use
* @uses bzopen(), tempnam("")
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpBz2 extends Adminer\Plugin {
	protected $filename, $fp;

	function dumpOutput() {
		if (!function_exists('bzopen')) {
			return array();
		}
		return array('bz2' => 'bzip2');
	}

	function _bz2($string, $state) {
		bzwrite($this->fp, $string);
		if ($state & PHP_OUTPUT_HANDLER_END) {
			bzclose($this->fp);
			$return = file_get_contents($this->filename);
			unlink($this->filename);
			return $return;
		}
		return "";
	}

	function dumpHeaders($identifier, $multi_table = false) {
		if ($_POST["output"] == "bz2") {
			$this->filename = tempnam("", "bz2");
			$this->fp = bzopen($this->filename, 'w');
			header("Content-Type: application/x-bzip");
			ob_start(array($this, '_bz2'), 1e6);
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Export do formátu Bzip2'),
		'de' => array('' => 'Export im Bzip2-Format'),
		'pl' => array('' => 'Zrzuć do formatu Bzip2'),
		'ro' => array('' => 'Dump în format Bzip2'),
		'ja' => array('' => 'Bzip2 形式でエクスポート'),
	);
}
