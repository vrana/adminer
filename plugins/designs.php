<?php

/** Allow switching designs
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDesigns {
	/** @access protected */
	var $designs;
	
	/**
	* @param array URL in key, name in value
	*/
	function __construct($designs) {
		$this->designs = $designs;
	}
	
	function headers() {
		if (isset($_POST["design"]) && verify_token()) {
			restart_session();
			$_SESSION["design"] = $_POST["design"];
			redirect($_SERVER["REQUEST_URI"]);
		}
	}
	
	function css() {
		$return = array();
		if (array_key_exists($_SESSION["design"], $this->designs)) {
			$return[] = $_SESSION["design"];
		}
		return $return;
	}
	
	function navigation($missing) {
		echo "<form action='' method='post' style='position: fixed; bottom: .5em; right: .5em;'>";
		echo html_select("design", array("" => "(design)") + $this->designs, $_SESSION["design"], "this.form.submit();");
		echo '<input type="hidden" name="token" value="' . get_token() . '">';
		echo "</form>\n";
	}

}
