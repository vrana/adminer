<?php
//! handle delete

/** Edit fields ending with "_path" by <input type="file"> and link to the uploaded files from select
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFileUpload extends Adminer\Plugin {
	protected $uploadPath, $displayPath, $extensions;

	/**
	* @param string $uploadPath prefix for uploading data (create writable subdirectory for each table containing uploadable fields)
	* @param string $displayPath prefix for displaying data, null stands for $uploadPath
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
			$table = ($_GET["edit"] != "" ? $_GET["edit"] : $_GET["select"]);
			$name = $field["field"];
			if ($_FILES["fields"]["error"][$name] || !preg_match("~(\\.($this->extensions))?\$~", $_FILES["fields"]["name"][$name], $regs2)) {
				return false;
			}
			//! unlink old
			$filename = (function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid("", true)) . $regs2[0];
			if (!move_uploaded_file($_FILES["fields"]["tmp_name"][$name], $this->uploadPath . Adminer\friendly_url($table) . "/$regs[1]-$filename")) {
				return false;
			}
			return Adminer\q($filename);
		}
	}

	function selectVal($val, &$link, $field, $original) {
		if ($val != "" && preg_match('~(.*)_path$~', $field["field"], $regs)) {
			$link = $this->displayPath . Adminer\friendly_url($_GET["select"]) . "/$regs[1]-$val";
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Políčka končící na "_path" upravuje pomocí <input type="file"> a odkazuje na nahrané soubory z výpisu'),
		'de' => array('' => 'Bearbeiten Sie Felder, die mit "_path" enden, um <input type="file"> und verknüpfen Sie sie mit den hochgeladenen Dateien beim Select'),
		'pl' => array('' => 'Edytuj pola kończące się na "_path" za pomocą <input type="file"> i link do przesłanych plików z wybierz'),
		'ro' => array('' => 'Modificați câmpurile care se termină cu "_path" prin <input type="file"> și creați un link către fișierele încărcate din select'),
		'ja' => array('' => '列名が "_path" で終わる列を <input type="file"> で変更し、"選択" からアップロードされたファイルにリンク'),
	);
}
