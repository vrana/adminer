<?php

/** Display JSON values as table in edit
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerJsonColumn {
	
	function editInput($table, $field, $attrs, $value) {
		if (substr($value, 0, 1) == '{' && ($json = json_decode($value, true))) {
			echo "<table cellspacing='0'>";
			foreach ($json as $key => $val) {
				echo "<tr><th>" . h($key) . "<td><code class='jush-js'>" . h(json_encode($val)) . "</code>";
			}
			echo "</table>";
		}
	}

}
