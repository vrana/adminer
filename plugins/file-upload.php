<?php
/** Edit fields ending with postfix by <input type="file"> and link to the uploaded files from select
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFileUpload {
	/** @access protected */
	var $uploadPath, $displayPath, $allowedMimeTypes, $fieldPostfix;

	/**
	* @param string prefix for uploading data (create writable subdirectory for each table containing uploadable fields)
	* @param string prefix for displaying data, null stands for $uploadPath
	* @param array with allowed mime types (empty array means all mime types are allowed)
	* @param string field postfix
	*/
	function __construct($uploadPath = "../static/data/", $displayPath = null, $allowedMimeTypes = [], $fieldPostfix = "_path" ) {
		$this->uploadPath = $uploadPath;
		$this->displayPath = ($displayPath !== null ? $displayPath : $uploadPath);
		$this->allowedMimeTypes = $allowedMimeTypes;
		$this->fieldPostfix = $fieldPostfix;
	}

	function editInput($table, $field, $attrs, $value) {
		if (preg_match('~(.*)'.$this->fieldPostfix.'$~', $field["field"])) {
			return '<input type="file"'.$attrs.'><br><input type="text" name="Previous['.$field["field"].']" value="'.$value.'" size="45" readonly>';
		}
	}

	function processInput($field, $value, $function = "") {
		$fname = $field["field"];
		if (preg_match('~(.*)'.$this->fieldPostfix.'$~', $fname, $regs)) {
			$previous_file = $_POST["Previous"][$fname] != "" ? q($_POST["Previous"][$fname]) : false;
			// check for file upload and $_FILES[$name], because search by this field does not have $_FILES
			if (isset($_FILES["fields"]["name"][$fname]) && $_FILES["fields"]["error"][$fname] === UPLOAD_ERR_OK) {
				$table = ($_GET["edit"] != "" ? $_GET["edit"] : $_GET["select"]);
				$mime_type = mime_content_type($_FILES["fields"]["tmp_name"][$fname]);
				if (count($this->allowedMimeTypes) && !in_array($mime_type, $this->allowedMimeTypes)) {
					return $previous_file;
				}
				$file_extension = pathinfo($_FILES["fields"]["name"][$fname], PATHINFO_EXTENSION);
				$title = pathinfo($_FILES["fields"]["name"][$fname], PATHINFO_FILENAME);
				$filename = $regs[1]."-".uniqid().$title.".".$file_extension;

				if (move_uploaded_file($_FILES["fields"]["tmp_name"][$fname], "$this->uploadPath$table/$filename")) {
					/* Additional data and db fields can be filled/changed after succesfull upload.
					$fileinfo = @getimagesize("$this->uploadPath$table/$filename");
					if($fileinfo){
						$_POST["fields"]["width"] = $fileinfo[0];
						$_POST["fields"]["height"] = $fileinfo[1];
					}
					$_POST["fields"]["file_title"] = $title;
					$_POST["fields"]["file_extension"] = $file_extension;
					$_POST["fields"]["file_type"] = $mime_type;
					$_POST["fields"]["file_checksum"] = md5_file("$this->uploadPath$table/$filename");
					$_POST["fields"]["file_size"] = $_FILES["fields"]["size"][$fname];
					$_POST["fields"]["file_name_original"] = $title.".".$file_extension;
					$_POST["fields"]["file_uploaded_on"] = date("Y-m-d H:i:s");
					*/
					if($previous_file){
						//! unlink old
						unlink("$this->uploadPath$table/".trim($previous_file, "'"));
					}
					return q($filename);
				}
			}
			return $previous_file;
		}
	}

	function selectVal($val, &$link, $field, $original) {
		if ($val != "" && preg_match('~(.*)'.$this->fieldPostfix.'$~', $field["field"])) {
			$link = "$this->displayPath$_GET[select]/$val";
		}
	}

}
