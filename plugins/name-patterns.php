<?php

/** Name new indexes, foreign keys, checks and triggers by your own convention
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerNamePatterns extends Adminer\Plugin {
	protected $patterns;

	/**
	* @param string[] $patterns type => pattern, e.g. array('UNIQUE' => '{table}_{columns}_ukey', 'TRIGGER' => '{table}_{timing}{event}_trigger'), see Adminer::namePattern()
	*/
	function __construct($patterns) {
		$this->patterns = $patterns;
	}

	function namePattern($type) {
		return $this->patterns[$type];
	}

	protected $translations = array(
		'cs' => array('' => 'Pojmenuje nové indexy, cizí klíče, kontroly a triggery podle vlastní konvence'),
		'de' => array('' => 'Neue Indizes, Fremdschlüssel, Checks und Trigger nach eigener Konvention benennen'), // Claude Opus 5
		'hr' => array('' => 'Imenuje nove indekse, strane ključeve, provjere i okidače prema vlastitoj konvenciji'), // Claude Opus 5
		'ja' => array('' => '新しいインデックス、外部キー、チェック制約、トリガーに独自の命名規則で名前を付ける'), // Claude Opus 5
		'pl' => array('' => 'Nazywaj nowe indeksy, klucze obce, ograniczenia CHECK i wyzwalacze według własnej konwencji'), // Claude Opus 5
		'ro' => array('' => 'Denumiți indexurile, cheile externe, verificările și declanșatoarele noi după propria convenție'), // Claude Opus 5
		'sk' => array('' => 'Pomenuje nové indexy, cudzie kľúče, kontroly a triggery podľa vlastnej konvencie'), // Claude Opus 5
	);
}
