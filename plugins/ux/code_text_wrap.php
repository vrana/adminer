<?php

/** <code> text wrap
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerCodeTextWrap
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// text wrap in <code> blocks
			var style = document.createElement('style');
			style.type = 'text/css';
			style.innerHTML = 'pre code { white-space: pre-wrap; }';
			document.getElementsByTagName('head')[0].appendChild(style);
		});
		</script>
<?
	}
}