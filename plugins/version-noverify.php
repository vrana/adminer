<?php

/** Disable version checker
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerVersionNoverify extends Adminer\Plugin {

	function head($dark = null) {
		echo Adminer\script("verifyVersion = () => { };");
	}

	protected $translations = array(
		'cs' => array('' => 'Zakáže kontrolu nových verzí'),
		'de' => array('' => 'Deaktivieren Sie die Versionsprüfung'),
		'pl' => array('' => 'Wyłącz sprawdzanie wersji'),
		'ro' => array('' => 'Dezactivați verificatorul de versiuni'),
		'ja' => array('' => 'バージョンチェックを無効化'),
	);
}
