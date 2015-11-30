<?php

/** Duplicate pages list
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDuplicatePagesList
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// Duplicate pages list
			var table_box = document.getElementById("table");
			if (table_box)
			{
				var pages_box = table_box;
				while (pages_box && (!pages_box.className || (pages_box.className.split(/\s+/).indexOf("pages") < 0)))
					pages_box = pages_box.nextSibling;
				if (pages_box)
				{
					pages_box.style.position = "static";

					var new_pages_box = pages_box.cloneNode(true)
					var a_list = new_pages_box.getElementsByTagName("A");
					if (a_list[a_list.length-1].className.split(/\s+/).indexOf("loadmore") != -1)
						new_pages_box.removeChild(a_list[a_list.length-1]);
					new_pages_box = table_box.parentNode.insertBefore( new_pages_box, table_box );

					// copy also rows number
					var rows_count_box = pages_box;
					while (rows_count_box && (!rows_count_box.className || (rows_count_box.className.split(/\s+/).indexOf("count") < 0)))
						rows_count_box = rows_count_box.nextSibling;
					if (rows_count_box)
					{
						var new_rows_count_box = document.createElement("SPAN");
						var i, rows_count_box_childs = rows_count_box.childNodes;
						for (i=0; i<rows_count_box_childs.length; i++)
							if (!rows_count_box_childs[i].tagName)
								new_rows_count_box.appendChild( rows_count_box_childs[i].cloneNode() );

						var new_rows_count_box = new_pages_box.appendChild( new_rows_count_box );		// at end of upper pages chooser
						new_rows_count_box.className = rows_count_box.className;
						new_rows_count_box.style.marginLeft = "1ex";
					}
				}
			}
		});
		</script>
<?
	}
}