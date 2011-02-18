<?php

/** Edit all fields containing "_html" by HTML editor TinyMCE and display the HTML in select
* @uses TinyMCE, http://tinymce.moxiecode.com/
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTinymce {
	var $path;
	
	/**
	* @param string
	*/
	function AdminerTinymce($path = "tiny_mce/tiny_mce.js") {
		$this->path = $path;
	}
	
	function selectVal(&$val, $link, $field) {
		if (ereg("_html", $field["field"]) && $val != '&nbsp;') {
			$val = preg_replace('~<[^>]*$~', '', html_entity_decode($val, ENT_QUOTES)); //! close all opened tags (text can be shortened)
		}
	}
	
	function editInput($table, $field, $attrs, $value) {
		static $tiny_mce = false;
		if (ereg("text", $field["type"]) && ereg("_html", $field["field"])) {
			if (!$tiny_mce) {
				$tiny_mce = true;
				$lang = "en";
				if (function_exists('get_lang')) { // since Adminer 3.2.0
					$lang = get_lang();
					$lang = ($lang == "zh" ? "zh-cn" : ($lang == "zh-tw" ? "zh" : $lang));
					if (!file_exists(dirname($this->path) . "/langs/$lang.js")) {
						$lang = "en";
					}
				}
				?>
<script type="text/javascript" src="<?php echo h($this->path); ?>"></script>
<script type="text/javascript">
tinyMCE.init({
	mode: 'none',
	theme: 'advanced',
	plugins: 'contextmenu,paste,table',
	entity_encoding: 'raw',
	theme_advanced_buttons1: 'bold,italic,link,unlink,|,sub,sup,|,bullist,numlist,|,cleanup,code',
	theme_advanced_buttons2: 'tablecontrols',
	theme_advanced_buttons3: '',
	theme_advanced_toolbar_location: 'top',
	theme_advanced_toolbar_align: 'left',
	language: '<?php echo $lang; ?>'
});
</script>
<?php
			}
			return "<textarea$attrs id='fields-" . h($field["field"]) . "' rows='12' cols='50'>" . h($value) . "</textarea><script type='text/javascript'>tinyMCE.execCommand('mceAddControl', true, 'fields-" . js_escape($field["field"]) . "');</script>";
		}
	}
	
}
