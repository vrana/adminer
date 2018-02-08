<?php

/** Display jQuery UI Timepicker for each date and datetime field
* @link https://www.adminer.org/plugins/#use
* @uses jQuery-Timepicker, http://trentrichardson.com/examples/timepicker/
* @uses jQuery UI: core, widget, mouse, slider, datepicker
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditCalendar {
	/** @access protected */
	var $prepend, $langPath;

	/**
	* @param string text to append before first calendar usage
	* @param string path to language file, %s stands for language code
	*/
	function __construct($prepend = null, $langPath = "jquery-ui/i18n/jquery.ui.datepicker-%s.js") {
		if ($prepend === null) {
			$prepend = "<link rel='stylesheet' type='text/css' href='jquery-ui/jquery-ui.css'>\n"
				. script_src("jquery-ui/jquery.js")
				. script_src("jquery-ui/jquery-ui.js")
				. script_src("jquery-ui/jquery-ui-timepicker-addon.js")
			;
		}
		$this->prepend = $prepend;
		$this->langPath = $langPath;
	}

	function head() {
		echo $this->prepend;
		if ($this->langPath && function_exists('get_lang')) { // since Adminer 3.2.0
			$lang = get_lang();
			$lang = ($lang == "zh" ? "zh-CN" : ($lang == "zh-tw" ? "zh-TW" : $lang));
			if ($lang != "en" && file_exists(sprintf($this->langPath, $lang))) {
				echo script_src(sprintf($this->langPath, $lang));
				echo script("jQuery(function () { jQuery.timepicker.setDefaults(jQuery.datepicker.regional['$lang']); });");
			}
		}
	}

	function editInput($table, $field, $attrs, $value) {
		if (preg_match("~date|time~", $field["type"])) {
			$dateFormat = "changeYear: true, dateFormat: 'yy-mm-dd'"; //! yy-mm-dd regional
			$timeFormat = "showSecond: true, timeFormat: 'HH:mm:ss', timeInput: true";
			return "<input id='fields-" . h($field["field"]) . "' value='" . h($value) . "'" . (@+$field["length"] ? " maxlength='" . (+$field["length"]) . "'" : "") . "$attrs>" . script(
				"jQuery('#fields-" . js_escape($field["field"]) . "')."
				. ($field["type"] == "time" ? "timepicker({ $timeFormat })"
					: (preg_match("~time~", $field["type"]) ? "datetimepicker({ $dateFormat, $timeFormat })"
						: "datepicker({ $dateFormat })"
					)) . ";");
		}
	}

}
