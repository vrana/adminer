<?php

/** Cluster invalid login attempts by last part of X-Forwarded-For (useful if Adminer runs behind a reverse proxy)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginReverseProxy extends Adminer\Plugin {

	function bruteForceKey() {
		return preg_replace('~.*,\s*~', '', $_SERVER["HTTP_X_FORWARDED_FOR"]);
	}

	protected $translations = array(
		'cs' => array('' => 'Seskupí neplatné pokusy o přihlášení podle poslední části X-Forwarded-For (užitečné, když Adminer běží za reverzní proxy)'),
	);
}
