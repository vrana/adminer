<?php

/** Prefill a field containing "_slug" with the slugified value of the previous field (JavaScript)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSlugify extends Adminer\Plugin {
	protected $from, $to;

	/**
	* @param string $from find these characters ...
	* @param string $to ... and replace them by these
	*/
	function __construct($from = 'áčďéěíňóřšťúůýž', $to = 'acdeeinorstuuyz') {
		$this->from = $from;
		$this->to = $to;
	}

	function editInput($table, $field, $attrs, $value) {
		static $slugify, $printed;
		if (!$_GET["select"] && !$_GET["where"] && $table) {
			if ($slugify === null) {
				$slugify = array();
				$prev = null;
				foreach (Adminer\fields($table) as $name => $val) {
					if ($prev && preg_match('~(^|_)slug(_|$)~', $name)) {
						$slugify[$prev] = $name;
					}
					$prev = $name;
				}
			}
			$slug = $slugify[$field["field"]];
			if ($slug !== null) {
				$script = "";
				if (!$printed) {
					$printed = true;
					$script = Adminer\script("
function slugifyChange(slug, length) {
	const find = '" . Adminer\js_escape($this->from) . "';
	const repl = '" . Adminer\js_escape($this->to) . "';
	this.form['fields[' + slug + ']'].value = this.value.toLowerCase()
		.replace(new RegExp('[' + find + ']', 'g'), function (str) { return repl[find.indexOf(str)]; })
		.replace(/[^a-z0-9_]+/g, '-')
		.replace(/^-|-\$/g, '')
		.slice(0, length || undefined); // no length means no truncation
}", "");
				}
				return "<input value='" . Adminer\h($value) . "' data-maxlength='$field[length]' size='40'$attrs"
					. Adminer\on('change', 'slugifyChange', $slug, $field["length"]) . ">" . $script;
			}
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Předvyplní políčko obsahující "_slug" URLizovanou hodnotou předchozího políčka (JavaScript)'),
		'de' => array('' => 'Feld, das "_slug" enthält, mit dem Slugified-Wert eines vorherigen Felds vorab füllen (JavaScript)'),
		'pl' => array('' => 'Wstępnie wypełnij pole zawierające "_slug" osłabioną wartością poprzedniego pola (JavaScript)'),
		'ro' => array('' => 'Precompletați câmpul care conține "_slug" cu valoarea slugificată a unui câmp anterior (JavaScript)'),
		'ja' => array('' => '列名に "_slug" を含む列を、前列の URL 化された値でプレフィル (JavaScript)'),
		'hr' => array('' => 'Popunjava polje koje sadrži "_slug" slugificiranom vrijednošću prethodnog polja (JavaScript)'),
	);
}
