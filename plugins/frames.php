<?php

/** Allow using Adminer inside a frame
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFrames {
	
	function headers() {
		header("X-XSS-Protection: 0");
	}
	
}
