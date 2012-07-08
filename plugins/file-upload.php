<?php
//! delete

/** Edit fields ending with "_path" by <input type="file"> and link to the uploaded files from select
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFileUpload {
	/** @access protected */
	var $uploadPath, $displayPath, $extensions;
	
	/**
	* @param string prefix for uploading data (create writable subdirectory for each table containing uploadable fields)
	* @param string prefix for displaying data, null stands for $uploadPath
	* @param string regular expression with allowed file extensions
	*/
	function AdminerFileUpload($uploadPath = "../static/data/", $displayPath = null, $extensions = "[a-zA-Z0-9]+") {
		$this->uploadPath = $uploadPath;
		$this->displayPath = ($displayPath !== null ? $displayPath : $uploadPath);
		$this->extensions = $extensions;
	}
	
	function editInput($table, $field, $attrs, $value) {
		if (ereg('(.*)_path$', $field["field"])) {
			return "<input type='file' name='fields-$field[field]'>";
		}
	}
	
	function processInput($field, $value, $function = "") {
		if (ereg('(.*)_path$', $field["field"], $regs)) {
			$table = ($_GET["edit"] != "" ? $_GET["edit"] : $_GET["select"]);
			$name = "fields-$field[field]";
			if ($_FILES[$name]["error"] || !ereg("(\\.($this->extensions))?\$", $_FILES[$name]["name"], $regs2)) {
				return false;
			}
			//! unlink old
			$filename = uniqid() . $regs2[0];
			if (!move_uploaded_file($_FILES[$name]["tmp_name"], "$this->uploadPath$table/$regs[1]-$filename")) {
				return false;
			}
			return q($filename);
		}
	}
	
	function selectVal($val, &$link, $field) {
		if ($val != "&nbsp;" && ereg('(.*)_path$', $field["field"], $regs)) {
			$link = "$this->displayPath$_GET[select]/$regs[1]-$val";
		}
	}
	
}
