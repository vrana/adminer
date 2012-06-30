<?php

/** Hide some databases from the interface - just to improve design, not a security plugin
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDatabaseHide {
	protected $disabled;
	
	/**
	* @param array case insensitive database names in values
	*/
	function AdminerDatabaseHide($disabled) {
		$this->disabled = array_map('strtolower', $disabled);
	}
	
	function databases($flush = true) {
		$return = array();
		foreach (get_databases($flush) as $db) {
			if (!in_array(strtolower($db), $this->disabled)) {
				$return[] = $db;
			}
		}
		return $return;
	}
	
}
