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
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTranslation extends Adminer\Plugin {

	private function translate($idf) {
		static $translations, $lang;
		if ($lang === null) {
			$lang = Adminer\LANG;
		}
		if ($idf == "" || $lang == "en") {
			return $idf;
		}
		if ($translations === null) {
			$translations = Adminer\get_key_vals("SELECT idf, translation FROM translation WHERE language_id = " . Adminer\q($lang));
		}
		$return = &$translations[$idf];
		if ($return === null) {
			$return = $idf;
			Adminer\connection()->query("INSERT INTO translation (language_id, idf, translation) VALUES (" . Adminer\q($lang) . ", " . Adminer\q($idf) . ", " . Adminer\q($idf) . ")");
		}
		return $return;
	}

	function tableName(&$tableStatus) {
		$tableStatus["Comment"] = $this->translate($tableStatus["Comment"]);
	}

	function fieldName(&$field, $order = 0) {
		$field["comment"] = $this->translate($field["comment"]);
	}

	function editVal(&$val, $field) {
		if ($field["type"] == "enum") {
			$val = $this->translate($val);
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Přeloží všechny komentáře tabulek a sloupců, hodnoty políček enum a set pomocí tabulky "translation" (automaticky vkládá nové překlady)'),
		'de' => array('' => 'Übersetzen Sie alle Tabellen- und Feldkommentare, enum- und set-Werte aus der "translation"-Tabelle (fügt automatisch neue Übersetzungen ein)'),
		'pl' => array('' => 'Przetłumacz wszystkie komentarze do tabeli i pól, wartości enum i set z tabeli "translation" (automatycznie wstawia nowe tłumaczenia)'),
		'ro' => array('' => 'Traduceți toate comentariile tabelelor și câmpurilor, valorile enum și set din tabelul "translation" (inserează automat noi traduceri)'),
		'ja' => array('' => 'テーブル "translation" を用いてすべてのテーブルや列のコメント、列挙型、セット値を翻訳 (自動的に翻訳文で更新)'),
	);
}
