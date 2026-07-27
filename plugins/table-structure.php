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
			. "<th>" . $this->lang('Column')
			. "<th>" . $this->lang('Type')
			. "<th>" . $this->lang('Collation')
			. "<th>" . $this->lang('Nullable')
			. "<th>" . $this->lang('Default')
			. (Adminer\support("comment") ? "<th>" . $this->lang('Comment') : "")
			. "<tbody>\n"
		;
		foreach ($fields as $field) {
			echo "<tr><th>" . Adminer\h($field["field"]) . ($field["primary"] ? " (PRIMARY)" : "");
			echo "<td><span>" . Adminer\h($field["full_type"]) . "</span>";
			echo ($field["auto_increment"] ? " <i>" . $this->lang('Auto Increment') . "</i>" : "");
			echo "<td>" . ($field["collation"] ? " <i>" . Adminer\h($field["collation"]) . "</i>" : "");
			echo "<td>" . ($field["null"] ? $this->lang('Yes') : $this->lang('No'));
			echo "<td>" . Adminer\h($field["default"]);
			echo (Adminer\support("comment") ? "<td>" . Adminer\h($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
		return true;
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Rozšířené informace o tabulkách',
			'Column' => 'Sloupec',
			'Type' => 'Typ',
			'Collation' => 'Porovnávání',
			'Comment' => 'Komentář',
			'Auto Increment' => 'Auto Increment',
		),
		'de' => array(
			'' => 'Erweiterte Ausgabe der Tabellenstruktur',
			'Column' => 'Spalte',
			'Type' => 'Typ',
			'Collation' => 'Kollation',
			'Comment' => 'Kommentar',
			'Auto Increment' => 'Auto-Inkrement',
		),
		'pl' => array(
			'' => 'Rozszerzone wyjście struktury tabeli',
			'Column' => 'Kolumna',
			'Type' => 'Typ',
			'Collation' => 'Porównywanie znaków',
			'Comment' => 'Komentarz',
			'Auto Increment' => 'Automatyczny przyrost',
		),
		'ro' => array(
			'' => 'Ieșirea expandată a structurii tabelei',
			'Column' => 'Coloană',
			'Type' => 'Tip',
			'Collation' => 'Colaționare',
			'Comment' => 'Comentariu',
			'Auto Increment' => 'Creșterea automată',
		),
		'ja' => array(
			'' => 'テーブル構造を拡張表示',
			'Column' => 'カラム',
			'Type' => '型',
			'Collation' => 'コレーション',
			'Comment' => 'コメント',
			'Auto Increment' => '連番',
		),
		'hr' => array(
			'' => 'Prošireni prikaz strukture tablice',
			'Column' => 'Stupac',
			'Type' => 'Tip',
			'Collation' => 'Uspoređivanje',
			'Comment' => 'Komentar',
			'Auto Increment' => 'Auto-inkrement',
		),
	);
}
