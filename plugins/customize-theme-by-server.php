<?php

/** Enable customization of the active theme based on the active server
 *
 * It tries to add a SERVER specific CSS file using the following convention
 * - same directory as adminer.css
 * - name it server-[SERVER]-adminer.css
 * - add any additional CSS styles, you want to apply to the adminer.css or the default.css
 *
 * See the example
 * - test-customize-theme-by-server.php
 * - server-MY.LOCAL-adminer.css
 * - server-MY.LOCAL-ICON.png
 * - set server to MY.LOCAL
 * @link https://www.adminer.org/plugins/#use
 * @author Michael Mokroß, https://github.com/mmokross/adminer
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerCustomizeThemeBasedOnServer {
	
	/**
	 */
	function __construct() {
	}
	
	/** Get URLs of the CSS files
	 * @return array of strings
	 */
	function css() {
		$return = array();
		$filenames = array(
			"adminer.css",
			"server-" . SERVER . "-adminer.css",
		);
		foreach ($filenames as $filename) {
			if (file_exists($filename)) {
				$return[] = "$filename?v=" . crc32(file_get_contents($filename));
			}
		}
		return $return;
	}
	
}