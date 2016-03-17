<?php

/** Copy "Export" link to table links list
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerExportPerTable
{
	function head()
	{
		if (Adminer::database() === null)
			return;
		if (function_exists("get_page_table") && (get_page_table() === ""))
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// add "SQL commmand" to table content
			var export_link;
			var menu_box = document.getElementById("menu");
			var i, p_list = menu_box.getElementsByTagName("P");
			for (i=0; i<p_list.length; i++)
				if (p_list[i].className.split(/\s+/).indexOf("links") != -1)
				{
					export_link = p_list[i].getElementsByTagName("A")[2];
					break;
				}
			if (export_link)
			{
				var content_box = document.getElementById("content");
				p_list = content_box.getElementsByTagName("P");
				for (i=0; i<p_list.length; i++)
					if (p_list[i].className.split(/\s+/).indexOf("links") != -1)
					{
						var a_list = p_list[i].getElementsByTagName("A");
						if (a_list[0].href.indexOf("&select=") > 0 && a_list[a_list.length-1].href.indexOf("&dump=") < 0)
							p_list[i].appendChild( export_link.cloneNode(true) );

						break;
					}
			}
		});
		</script>
<?
	}
}