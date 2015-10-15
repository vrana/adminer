<?php

/** Dump to ZIP format
* @link https://www.adminer.org/plugins/#use
* @uses ZipArchive, tempnam("")
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpZip {
	/** @access protected */
	var $filename, $data;

	function dumpOutput() {
		if (!class_exists('ZipArchive')) {
			return array();
		}
		return array('zip' => 'ZIP');
	}

	function _zip($string, $state) {
		// ZIP can be created without temporary file by gzcompress - see PEAR File_Archive
		$this->data .= $string;
		if ($state & PHP_OUTPUT_HANDLER_END) {
			$zip = new ZipArchive;
			$zipFile = tempnam("", "zip");
			$zip->open($zipFile, ZipArchive::OVERWRITE); // php://output is not supported
			$zip->addFromString($this->filename, $this->data);
			$zip->close();
			$return = file_get_contents($zipFile);
			unlink($zipFile);
			return $return;
		}
		return "";
	}

	function dumpHeaders($identifier, $multi_table = false) {
		if ($_POST["output"] == "zip") {
			$this->filename = "$identifier." . ($multi_table && preg_match("~[ct]sv~", $_POST["format"]) ? "tar" : $_POST["format"]);
			header("Content-Type: application/zip");
			ob_start(array($this, '_zip'));
		}
	}

}
