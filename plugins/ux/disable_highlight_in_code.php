<?php

/** Disable highlight in <code>
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDisableHighlightInCode
{
	function navigation()
	{
?>
		<script>
		// disable jush for <code>
		var textareas = document.getElementsByTagName("CODE");
		cnt = textareas.length;
		for (i=0; i<cnt; i++)
			textareas[i].className = textareas[i].className.replace(/\bjush-[^\s]+/, "");
		</script>
<?
	}
}