<?php

/** Disable highlight in <textarea> and <code>
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDisableHighlight
{
	private $TYPES_LIST = ["textarea", "code"];

	function __construct($types_list = [])
	{
		if ($types_list)
		{
			if (!is_array($types_list))
				$types_list = array($types_list);
			$this->TYPES_LIST = $types_list;
		}
	}

	function navigation()
	{
		if (in_array("textarea", $this->TYPES_LIST))
		{
?>
			<script>
			// disable jush for <textarea>
			var textareas = document.getElementsByTagName("TEXTAREA");
			cnt = textareas.length;
			for (i=0; i<cnt; i++)
				textareas[i].className = textareas[i].className.replace(/\bjush-[^\s]+/, "");
			</script>
<?php
		}

		if (in_array("code", $this->TYPES_LIST))
		{
?>
			<script>
			// disable jush for <code>
			var textareas = document.getElementsByTagName("CODE");
			cnt = textareas.length;
			for (i=0; i<cnt; i++)
				textareas[i].className = textareas[i].className.replace(/\bjush-[^\s]+/, "");
			</script>
<?php
		}

		if (in_array("textarea", $this->TYPES_LIST) && in_array("code", $this->TYPES_LIST))
		{
?>
			<script>
			document.addEventListener("DOMContentLoaded", function(event) { window.jush = null; });
			</script>
<?php
		}
	}
}