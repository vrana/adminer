<?php

/** Prefill field containing "_slug" with slugified value of a previous field (JavaScript)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSlugify {
	/** @access protected */
	var $from, $to;

	/**
	* @param string find these characters ...
	* @param string ... and replace them by these
	*/
	function __construct($from = 'áčďéěíňóřšťúůýž', $to = 'acdeeinorstuuyz') {
		$this->from = $from;
		$this->to = $to;
	}

	function editInput($table, $field, $attrs, $value) {
		static $slugify;
		if (!$_GET["select"] && !$_GET["where"]) {
			if ($slugify === null) {
				$slugify = array();
				$prev = null;
				foreach (fields($table) as $name => $val) {
					if ($prev && preg_match('~(^|_)slug(_|$)~', $name)) {
						$slugify[$prev] = $name;
					}
					$prev = $name;
				}
			}
			$slug = $slugify[$field["field"]];
			if ($slug !== null) {
				return "<input value='" . h($value) . "' maxlength='$field[length]' size='40'$attrs onchange=\"var find = '$this->from'; var repl = '$this->to'; this.form['fields[$slug]'].value = this.value.toLowerCase().replace(new RegExp('[' + find + ']', 'g'), function (str) { return repl[find.indexOf(str)]; }).replace(/[^a-z0-9_]+/g, '-').replace(/^-|-\$/g, '').substr(0, $field[length]);\">";
			}
		}
	}

}
