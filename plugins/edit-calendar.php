<?php

/** Display jQuery UI Timepicker for each date and datetime field
* @link https://www.adminer.org/plugins/#use
* @uses jQuery-Timepicker, http://trentrichardson.com/examples/timepicker/
* @uses jQuery UI: core, widget, mouse, slider, datepicker
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditCalendar extends Adminer\Plugin {
	protected $prepend, $langPath;

	/**
	* @param string $prepend text to append before first calendar usage
	* @param string $langPath path to language file, %s stands for language code
	*/
	function __construct($prepend = null, $langPath = "jquery-ui/i18n/jquery.ui.datepicker-%s.js") {
		$this->prepend = $prepend;
		$this->langPath = $langPath;
	}

	function head($dark = null) {
		echo ($this->prepend !== null ? $this->prepend :
			"<link rel='stylesheet' type='text/css' href='jquery-ui/jquery-ui.css'>\n"
			. Adminer\script_src("jquery-ui/jquery.js")
			. Adminer\script_src("jquery-ui/jquery-ui.js")
			. Adminer\script_src("jquery-ui/jquery-ui-timepicker-addon.js")
		);
		if ($this->langPath) {
			$lang = Adminer\LANG;
			$lang = ($lang == "zh" ? "zh-CN" : ($lang == "zh-tw" ? "zh-TW" : $lang));
			if ($lang != "en" && file_exists(sprintf($this->langPath, $lang))) {
				echo Adminer\script_src(sprintf($this->langPath, $lang));
				echo Adminer\script("jQuery(() => { jQuery.timepicker.setDefaults(jQuery.datepicker.regional['$lang']); });");
			}
		}
	}

	function editInput($table, $field, $attrs, $value) {
		if (preg_match("~date|time~", $field["type"])) {
			$dateFormat = "changeYear: true, dateFormat: 'yy-mm-dd'"; //! yy-mm-dd regional
			$timeFormat = "showSecond: true, timeFormat: 'HH:mm:ss', timeInput: true";
			return "<input id='fields-" . Adminer\h($field["field"]) . "' value='" . Adminer\h($value) . "'" . (@+$field["length"] ? " data-maxlength='" . (+$field["length"]) . "'" : "") . "$attrs>" . Adminer\script(
				"jQuery('#fields-" . Adminer\js_escape($field["field"]) . "')."
				. ($field["type"] == "time" ? "timepicker({ $timeFormat })"
					: (preg_match("~time~", $field["type"]) ? "datetimepicker({ $dateFormat, $timeFormat })"
						: "datepicker({ $dateFormat })"
					)) . ";"
			);
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Zobrazí jQuery UI Timepicker pro každé datumové a časové políčko'),
		'de' => array('' => 'Zeigen Sie die jQuery-UI Timepicker für jedes Datums- und Datum/Uhrzeit-Feld an'),
		'pl' => array('' => 'Wyświetl interfejs jQuery Timepicker dla każdego pola daty i godziny'),
		'ro' => array('' => 'Afișați jQuery UI Timepicker pentru fiecare câmp de dată și dată-timp'),
		'ja' => array('' => '各日時列に jQuery UI の Timepicker を表示'),
	);
}
