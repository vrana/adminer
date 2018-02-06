<?php
//! delete

/** Edit fields ending with "_path" by <input type="file"> and link to the uploaded files from select
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFileUpload {
	/** @access protected */
	var $uploadPath, $displayPath, $extensions;

	/**
	* @param string prefix for uploading data (create writable subdirectory for each table containing uploadable fields)
	* @param string prefix for displaying data, null stands for $uploadPath
	* @param string regular expression with allowed file extensions
	*/
	function __construct($uploadPath = "../static/data/", $displayPath = null, $extensions = "[a-zA-Z0-9]+") {
		$this->uploadPath = $uploadPath;
		$this->displayPath = ($displayPath !== null ? $displayPath : $uploadPath);
		$this->extensions = $extensions;
	}

	function editInput($table, $field, $attrs, $value) {
		if (preg_match('~(.*)_path$~', $field["field"])) {
			return "<input type='file'$attrs>";
		}
	}

	function processInput($field, $value, $function = "") {
		if (preg_match('~(.*)_path$~', $field["field"], $regs)) {
			$table = ($_GET["edit"] != "" ? $_GET["edit"] : $_GET["select"]);
			$name = "fields-$field[field]";
			if ($_FILES[$name]["error"] || !preg_match("~(\\.($this->extensions))?\$~", $_FILES[$name]["name"], $regs2)) {
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

	function selectVal($val, &$link, $field, $original) {
		if ($val != "&nbsp;" && preg_match('~(.*)_path$~', $field["field"], $regs)) {
			$link = "$this->displayPath$_GET[select]/$regs[1]-$val";
		}
	}

}
