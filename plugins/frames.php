<?php

/** Allow using Adminer inside a frame
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFrames extends Adminer\Plugin {
	protected $sameOrigin;

	/**
	* @param bool $sameOrigin allow running from the same origin only
	*/
	function __construct($sameOrigin = false) {
		$this->sameOrigin = $sameOrigin;
	}

	function headers() {
		if ($this->sameOrigin) {
			header("X-Frame-Options: SameOrigin");
		} elseif (function_exists('header_remove')) {
			header_remove("X-Frame-Options");
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Dovolí pracovat Admineru uvnitř rámu'),
		'de' => array('' => 'Erlauben Sie die Verwendung von Adminer innerhalb eines Frames'),
		'pl' => array('' => 'Zezwalaj na używanie Adminera wewnątrz ramki'),
		'ro' => array('' => 'Permiteți utilizarea Adminer în interiorul unui cadru'),
		'ja' => array('' => 'フレーム内での Adminer 利用を許可'),
	);
}
