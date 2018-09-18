<?php

/** Use <textarea> for char and varchar
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditTextarea {

	function editInput($table, $field, $attrs, $value) {
		if (preg_match('~char~', $field["type"])) {
			return "<textarea cols='30' rows='1' style='height: 1.2em;'$attrs>" . h($value) . '</textarea>';
		}
	}

}
