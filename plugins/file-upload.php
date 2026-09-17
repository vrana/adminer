<?php
//! handle delete

/** Edit fields ending with "_path" with <input type="file"> and link to the uploaded files from select
* The uploaded file can have any extension by default, e.g. *.php can be a legitimate download, so disable executing scripts in the upload directory by PHP config "engine Off".
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
			if ($_FILES["fields"]["error"][$name] || !preg_match("~(\\.($this->extensions))\$~", $_FILES["fields"]["name"][$name], $regs2)) {
				return false;
			}
			//! unlink old
			$filename = Adminer\rand_string() . $regs2[0];
			if (!move_uploaded_file($_FILES["fields"]["tmp_name"][$name], $this->uploadPath . Adminer\friendly_url($table) . "/" . Adminer\friendly_url($regs[1]) . "-$filename")) {
				return false;
			}
			return Adminer\q($filename);
		}
	}

	function selectVal($val, &$link, $field, $original) {
		if ($val != "" && preg_match('~(.*)_path$~', $field["field"], $regs)) {
			$link = $this->displayPath . Adminer\friendly_url($_GET["select"]) . "/" . Adminer\friendly_url($regs[1]) . "-$val";
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Políčka končící na "_path" upravuje pomocí <input type="file"> a odkazuje na nahrané soubory z výpisu'),
		'de' => array('' => 'Bearbeiten Sie Felder, die mit "_path" enden, mit <input type="file"> und verknüpfen Sie sie mit den hochgeladenen Dateien beim Select'),
		'pl' => array('' => 'Edytuj pola kończące się na "_path" za pomocą <input type="file"> oraz linkuj do przesłanych plików w widoku danych'), // Claude Opus 5
		'ro' => array('' => 'Modificați câmpurile care se termină cu "_path" prin <input type="file"> și creați un link către fișierele încărcate din select'),
		'ja' => array('' => '列名が "_path" で終わる列を <input type="file"> で変更し、一覧からアップロードされたファイルにリンク'), // Claude Opus 5
		'sk' => array('' => 'Upravuje políčka končiace na "_path" pomocou <input type="file"> a odkazuje na nahrané súbory z výpisu'), // Claude Opus 5
		'hr' => array('' => 'Uređuje polja koja završavaju s "_path" putem <input type="file"> i povezuje ih s učitanim datotekama'),
		'zh' => array('' => '用 <input type="file"> 编辑以 "_path" 结尾的字段，并在选择数据时链接到已上传的文件'),
	);
}
