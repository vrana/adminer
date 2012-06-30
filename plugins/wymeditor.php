<?php

/** Edit all fields containing "_html" by HTML editor WYMeditor and display the HTML in select
* @link http://www.adminer.org/plugins/#use
* @uses WYMeditor, http://www.wymeditor.org/
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerWymeditor {
	/** @access protected */
	var $scripts, $options;
	
	/**
	* @param array
	* @param string in format "skin: 'custom', preInit: function () { }"
	*/
	function AdminerWymeditor($scripts = array("jquery/jquery.js", "wymeditor/jquery.wymeditor.min.js"), $options = "") {
		$this->scripts = $scripts;
		$this->options = $options;
	}
	
	function head() {
		foreach ($this->scripts as $script) {
			echo "<script type='text/javascript' src='" . h($script) . "'></script>\n";
		}
	}
	
	function selectVal(&$val, $link, $field) {
		// copied from tinymce.php
		if (ereg("_html", $field["field"]) && $val != '&nbsp;') {
			$shortened = (substr($val, -10) == "<i>...</i>");
			if ($shortened) {
				$val = substr($val, 0, -10);
			}
			//! shorten with regard to HTML tags - http://php.vrana.cz/zkraceni-textu-s-xhtml-znackami.php
			$val = preg_replace('~<[^>]*$~', '', html_entity_decode($val, ENT_QUOTES)); // remove ending incomplete tag (text can be shortened)
			if ($shortened) {
				$val .= "<i>...</i>";
			}
			if (class_exists('DOMDocument')) { // close all opened tags
				$dom = new DOMDocument;
				if (@$dom->loadHTML("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'></head>$val")) { // @ - $val can contain errors
					$val = preg_replace('~.*<body[^>]*>(.*)</body>.*~is', '\\1', $dom->saveHTML());
				}
			}
		}
	}
	
	function editInput($table, $field, $attrs, $value) {
		static $lang = "";
		if (!$lang && ereg("text", $field["type"]) && ereg("_html", $field["field"])) {
			$lang = "en";
			if (function_exists('get_lang')) { // since Adminer 3.2.0
				$lang = get_lang();
				$lang = ($lang == "zh" || $lang == "zh-tw" ? "zh_cn" : $lang);
			}
			return "<textarea$attrs id='fields-" . h($field["field"]) . "' rows='12' cols='50'>" . h($value) . "</textarea><script type='text/javascript'>
jQuery('#fields-" . js_escape($field["field"]) . "').wymeditor({ updateSelector: '#form [type=\"submit\"]', lang: '$lang'" . ($this->options ? ", $this->options" : "") . " });
</script>";
		}
	}
	
}
