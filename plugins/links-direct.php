<?php

/** Avoid redirecting of external links through adminer.org and disclose the URL of installed Adminer to visited links
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLinksDirect {
	
	function selectLink($val, $field) {
		if (is_url($val)) {
			return $val;
		}
	}
	
}
