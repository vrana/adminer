<?php

/** Display views in Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditorViews {

	function tableName($tableStatus) {
		return Adminer\h($tableStatus["Comment"] != "" ? $tableStatus["Comment"] : $tableStatus["Name"]);
	}
}
