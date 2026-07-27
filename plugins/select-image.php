<?php

/** Display images in select
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSelectImage extends Adminer\Plugin {

	function selectVal($val, $link, $field, $original) {
		if ($link != "" && Adminer\is_blob($field) && !Adminer\is_utf8($val) && function_exists('getimagesizefromstring')) {
			$size = @getimagesizefromstring($original); // @ - the notice for other data contains the data; the function is since PHP 5.4
			if ($size) { // the same as in Adminer Editor
				$link = Adminer\h($link);
				return "<a href='$link'><img src='$link' alt='' title='" . $this->lang('%d byte(s)', strlen($original)) . "' $size[3] loading='lazy'></a>"; // $size[3] is width and height
			}
		}
	}

	protected $translations = array(
		'en' => array(
			'%d byte(s)' => array('%d byte', '%d bytes'),
		),
		'cs' => array(
			'' => 'Zobrazí obrázky ve výpisu',
			'%d byte(s)' => array('%d bajt', '%d bajty', '%d bajtů'),
		),
	);
}
