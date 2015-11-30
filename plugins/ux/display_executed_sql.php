<?php

/** Display executed SQL
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDisplayExecutedSQL
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// always show executed SQL
			var childs, j;
			var messages = document.getElementsByClassName("message");
			var i, cnt = messages.length;
			for (i=0; i<cnt; i++)
			{
				childs = messages[i].childNodes;
				for (j=0; j<childs.length; j++)
					if (childs[j].tagName && (childs[j].className.split(/\s+/).indexOf("hidden") != -1))
						childs[j].className = childs[j].className.replace(/\bhidden\b/, "");
			}
		});
		</script>
<?
	}
}