<?php

/** Use <textarea> for char and varchar
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditTextarea extends Adminer\Plugin {

	function editInput($table, $field, $attrs, $value) {
		if (preg_match('~char~', $field["type"])) {
			return "<textarea cols='30' rows='1'$attrs>" . Adminer\h($value) . '</textarea>';
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Použije <textarea> pro char a varchar'),
		'de' => array('' => 'Verwenden Sie <textarea> für char und varchar Felder'),
		'pl' => array('' => 'Użyj <textarea> dla char i varchar'),
		'ro' => array('' => 'Utilizați <textarea> pentru char și varchar'),
		'ja' => array('' => 'char や varchar に <textarea> を使用'),
	);
}
