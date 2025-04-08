<?php

/** Include current date and time in export filename
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpDate extends Adminer\Plugin {

	function dumpFilename($identifier) {
		return Adminer\friendly_url(($identifier != "" ? $identifier : (Adminer\SERVER != "" ? Adminer\SERVER : "localhost")) . "-" . Adminer\get_val("SELECT NOW()"));
	}

	protected $translations = array(
		'cs' => array('' => 'Do názvu souboru s exportem přidá aktuální datum a čas'),
		'de' => array('' => 'Aktuelles Datum und die aktuelle Uhrzeit in den Namen der Exportdatei einfügen'),
		'pl' => array('' => 'Dołącz bieżącą datę i godzinę do nazwy pliku eksportu'),
		'ro' => array('' => 'Includeți data și ora curentă în numele fișierului de export'),
		'ja' => array('' => 'エクスポートファイル名に現在日時を含める'),
	);
}
