<?php

/** Expanded table structure output
* @link https://www.adminer.org/plugins/#use
* @author Matthew Gamble, https://www.matthewgamble.net/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableStructure extends Adminer\Plugin {

	/** Print table structure in tabular format
	* @param Field[] $fields data about individual fields
	*/
	function tableStructurePrint(array $fields, $tableStatus = null): bool {
		echo "<div class='scrollable'>\n";
		echo "<table class='nowrap odds'>\n";
		echo "<thead><tr>"
			. "<th>" . Adminer\lang('Column')
			. "<th>" . Adminer\lang('Type')
			. "<th>" . Adminer\lang('Collation')
			. "<th>" . Adminer\lang('Nullable')
			. "<th>" . Adminer\lang('Default')
			. (Adminer\support("comment") ? "<th>" . Adminer\lang('Comment') : "")
			. "</thead>\n"
		;
		foreach ($fields as $field) {
			echo "<tr><th>" . Adminer\h($field["field"]) . ($field["primary"] ? " (PRIMARY)" : "");
			echo "<td><span>" . Adminer\h($field["full_type"]) . "</span>";
			echo ($field["auto_increment"] ? " <i>" . Adminer\lang('Auto Increment') . "</i>" : "");
			echo "<td>" . ($field["collation"] ? " <i>" . Adminer\h($field["collation"]) . "</i>" : "");
			echo "<td>" . ($field["null"] ? Adminer\lang('Yes') : Adminer\lang('No'));
			echo "<td>" . Adminer\h($field["default"]);
			echo (Adminer\support("comment") ? "<td>" . Adminer\h($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
		return true;
	}

	protected $translations = array(
		'cs' => array('' => 'Rozšířené informace o tabulkách'),
		'de' => array('' => 'Erweiterte Ausgabe der Tabellenstruktur'),
		'pl' => array('' => 'Rozszerzone wyjście struktury tabeli'),
		'ro' => array('' => 'Ieșirea expandată a structurii tabelei'),
		'ja' => array('' => 'テーブル構造を拡張表示'),
	);
}
