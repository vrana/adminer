<?php

/** Add followers (pages list, SQL code,...) to table horizontal scroll
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableHScrollFollowers
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// move table main controls on horizontal scroll
			if (document.getElementById("table"))
			{
				// do not overpain top bar
				if (document.getElementById("lang"))	// english only adminer has not lang selector
					document.getElementById("lang").style.zIndex = "1";
				document.getElementById("breadcrumb").style.zIndex = "1";
				document.getElementById("logout").parentNode.style.zIndex = "1";

				var scroll_box = document.getElementById("content_scroll_box");		// Support plugin, which sumilate frameset scrolls
				if (!scroll_box)
					scroll_box = window;
				scroll_box.addEventListener("scroll", function(event)
				{
					var scrollLeft = this.scrollX || this.scrollLeft;
					var tbl = document.getElementById("table");

					var i, el, directions = {
											down_pages:		{src_obj:tbl, shift_attr:"nextSibling"},
											up_pages:		{src_obj:tbl, shift_attr:"previousSibling"},
											up_elements:	{src_obj:tbl.parentNode, shift_attr:"previousSibling"}
											};
					for (i in directions)
					{
						var el = directions[i].src_obj;
						while (el && (el = el[ directions[i].shift_attr ]))
							if (el.tagName
								&& (((i != "up_elements") && (el.className.split(/\s+/).indexOf("pages") >= 0))
									|| ((i == "up_elements") && (el.id != "breadcrumb"))))
							{
								if (!el.myOriginalPosition)
								{
									el.myOriginalPosition = el.style.position;
									el.style.position = "relative";
								}
								el.style.left = scrollLeft + "px";
							}
					}
				}, false);
			}
		});
		</script>
<?
	}
}