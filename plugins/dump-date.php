<?php

/** Include current date and time in export filename
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDumpDate {

	function dumpFilename($identifier) {
		return Adminer\friendly_url(($identifier != "" ? $identifier : (Adminer\SERVER != "" ? Adminer\SERVER : "localhost")) . "-" . Adminer\get_val("SELECT NOW()"));
	}
}
