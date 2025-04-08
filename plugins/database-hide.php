<?php

/** Hide some databases from the interface - just to improve design, not a security plugin
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDatabaseHide extends Adminer\Plugin {
	protected $disabled;

	/**
	* @param list<string> $disabled case insensitive database names in values
	*/
	function __construct(array $disabled) {
		$this->disabled = array_map('strtolower', $disabled);
	}

	function databases($flush = true) {
		$return = array();
		foreach (Adminer\get_databases($flush) as $db) {
			if (!in_array(strtolower($db), $this->disabled)) {
				$return[] = $db;
			}
		}
		return $return;
	}

	protected $translations = array(
		'cs' => array('' => 'Skryje některé databáze z rozhraní – pouze vylepší vzhled, nikoliv bezpečnost'),
		'de' => array('' => 'Verstecken Sie einige Datenbanken vor der Benutzeroberfläche – nur um das Design zu verbessern, verbessert nicht die Sicherheit'),
		'pl' => array('' => 'Ukryj niektóre bazy danych w interfejsie – tylko po to, aby ulepszyć motyw, a nie wtyczkę zabezpieczającą'),
		'ro' => array('' => 'Ascundeți unele baze de date din interfață - doar pentru a îmbunătăți designul, nu un plugin de securitate'),
		'ja' => array('' => '一部データベースを UI 上で表示禁止 (デザイン的な効果のみでセキュリティ的には効果なし)'),
	);
}
