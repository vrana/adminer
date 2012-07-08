<?php

/** Allow using Adminer inside a frame (disables ClickJacking protection)
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFrames {
	/** @access protected */
	var $sameOrigin;
	
	/**
	* @param bool allow running from the same origin only
	*/
	function AdminerFrames($sameOrigin = false) {
		$this->sameOrigin = $sameOrigin;
	}
	
	function headers() {
		if ($this->sameOrigin) {
			header("X-Frame-Options: SameOrigin");
		}
		header("X-XSS-Protection: 0");
		return false;
	}
	
}
