<?php
/* Requires this table:
CREATE TABLE translation (
	id int NOT NULL AUTO_INCREMENT, -- optional
	language_id varchar(5) NOT NULL,
	idf text NOT NULL COLLATE utf8_bin,
	translation text NOT NULL,
	UNIQUE (language_id, idf(100)),
	PRIMARY KEY (id)
);
*/

/** Translate all table and field comments, enum and set values from the translation table (inserts new translations)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTranslation {
	
	function _translate($idf) {
		static $translations, $lang;
		if ($lang === null) {
			$lang = get_lang();
		}
		if ($idf == "" || $lang == "en") {
			return $idf;
		}
		if ($translations === null) {
			$translations = get_key_vals("SELECT idf, translation FROM translation WHERE language_id = " . q($lang));
		}
		$return = &$translations[$idf];
		if ($return === null) {
			$return = $idf;
			$connection = connection();
			$connection->query("INSERT INTO translation (language_id, idf, translation) VALUES (" . q($lang) . ", " . q($idf) . ", " . q($idf) . ")");
		}
		return $return;
	}
	
	function tableName(&$tableStatus) {
		$tableStatus["Comment"] = $this->_translate($tableStatus["Comment"]);
	}
	
	function fieldName(&$field, $order = 0) {
		$field["comment"] = $this->_translate($field["comment"]);
	}
	
	function editVal(&$val, $field) {
		if ($field["type"] == "enum") {
			$val = $this->_translate($val);
		}
	}
	
}
