<?php

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
	* @param string $uploadPath prefix for uploading data (create writable subdirectory for each table containing uploadable fields)
	* @param string|null $displayPath prefix for displaying data, null stands for $uploadPath
	* @param string $extensions regular expression with allowed file extensions
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
			$tableName = ($_GET["edit"] != "" ? $_GET["edit"] : $_GET["select"]);
			$fieldName = $field["field"];
			$files = $_FILES["fields"];

			// Check upload error and file extension.
			if ($files["error"][$fieldName] || !preg_match('~\.(' . $this->extensions . ')$~', $files["name"][$fieldName], $regs2)) {
				return false;
			}

			// Generate random unique file name.
			do {
				$filename = $this->generateName() . $regs2[0];

				$targetPath = $this->uploadPath . $this->fsEncode($tableName) . "/" . $this->fsEncode($regs[1]) . "-$filename";
			} while (file_exists($targetPath));

			// Move file to final destination.
			if (!move_uploaded_file($files["tmp_name"][$fieldName], $targetPath)) {
				return false;
			}

			return q($filename);
		}
	}

	private function fsEncode($value) {
		// Encode special filesystem characters.
		return strtr($value, [
			'.' => '%2E',
			'/' => '%2F',
			'\\' => '%5C',
		]);
	}

	private function generateName()
	{
		$rand = function_exists("random_int") ? "random_int" : "rand";

		$result = '';
		for ($i = 0; $i < 16; $i++) {
			$code = $rand(97, 132); // random ASCII code for a-z and shifted 0-9
			$result .= chr($code > 122 ? $code - 122 + 47 : $code);
		}

		return $result;
	}

	function selectVal($val, &$link, $field, $original) {
		if ($val != "" && preg_match('~(.*)_path$~', $field["field"], $regs)) {
			$link = $this->displayPath . "$_GET[select]/$regs[1]-$val";
		}
	}
}
