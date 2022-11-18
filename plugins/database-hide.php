<?php

/** Hide some databases from the interface - just to improve design, not a security plugin
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDatabaseHide {
	protected $disabled;
	protected $enabled;
	
	/**
	* @param array case insensitive database names in values
	*/
	function __construct($disabled, $enabled = null) {
		$this->disabled = array_map('strtolower', $disabled);
        if ($enabled) {
            $this->enabled = array_map('strtolower', $enabled);
        }
	}
	
	function databases($flush = true) {
		$return = array();
		// filter disables databases
		foreach (get_databases($flush) as $db) {
			if (!in_array(strtolower($db), $this->disabled)) {
				$return[] = $db;
			}
		}
		// filter enables databases
		if ($this->enabled) {
			$return = array_intersect($return, $this->enabled);
		}
		return $return;
	}
	
}
