<?php

/** Expanded table indexes structure output
* @link https://www.adminer.org/plugins/#use
* @author Matthew Gamble, https://www.matthewgamble.net/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableIndexesStructure extends Adminer\Plugin {

	function tableIndexesPrint($indexes, $tableStatus): bool {
		echo "<table>\n";
		echo "<thead><tr><th>" . $this->lang('Name') . "<th>" . $this->lang('Type') . "<th>" . $this->lang('Algorithm') . "<th>" . $this->lang('Columns') . "<tbody>\n";
		foreach ($indexes as $name => $index) {
			echo "<tr><th>" . Adminer\h($name) . "<td>" . Adminer\h($index["type"]) . "<td>" . Adminer\h($index["algorithm"]);
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . Adminer\h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . Adminer\h($index["lengths"][$key]) . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}
			echo "<td>" . implode(", ", $print) . "\n";
		}
		echo "</table>\n";
		return true;
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Rozšířené informace o indexech',
			'Name' => 'Název',
			'Type' => 'Typ',
			'Algorithm' => 'Algoritmus',
			'Columns' => 'Sloupce',
		),
		'de' => array(
			'' => 'Erweiterte Ausgabe der Tabellenindizes', // Claude Opus 5
			'Name' => 'Name',
			'Type' => 'Typ',
			'Algorithm' => 'Algorithmus',
			'Columns' => 'Spalten',
		),
		'pl' => array(
			'' => 'Rozszerzone wyjście struktury indeksów tabeli', // Claude Opus 5
			'Name' => 'Nazwa',
			'Type' => 'Typ',
			'Algorithm' => 'Algorytm',
			'Columns' => 'Kolumny',
		),
		'ro' => array(
			'' => 'Ieșirea expandată a structurii indecsilor tabelului',
			'Name' => 'Titlu',
			'Type' => 'Tip',
			'Algorithm' => 'Algoritm',
			'Columns' => 'Coloane',
		),
		'ja' => array(
			'' => 'テーブルのインデックス構造を拡張表示',
			'Name' => '名称',
			'Type' => '型',
			'Algorithm' => 'アルゴリズム',
			'Columns' => 'カラム',
		),
		'sk' => array(
			'' => 'Rozšírený výpis štruktúry indexov tabuľky', // Claude Opus 5
		),
		'hr' => array(
			'' => 'Prošireni prikaz indeksa tablice',
			'Name' => 'Naziv',
			'Type' => 'Tip',
			'Algorithm' => 'Algoritam',
			'Columns' => 'Stupci',
		),
		'zh' => array(
			'' => '扩展显示表索引结构', // Claude Opus 5
			'Name' => '名称', // Claude Opus 5
			'Type' => '类型', // Claude Opus 5
			'Algorithm' => '算法', // Claude Opus 5
			'Columns' => '列', // Claude Opus 5
		),
	);
}
