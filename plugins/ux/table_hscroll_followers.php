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
			var content = document.getElementById("content");
			if (content)
			{
				// sql command result table has not ID => search it this way
				var result_table = document.getElementById("table");
				if (!result_table)
				{
					var i, cnt = content.childNodes.length;
					for (i=0; i<cnt; i++)
						if (content.childNodes[i].tagName == "TABLE")
						{
							result_table = content.childNodes[i];
							break;
						}
				}

				if (result_table)
				{
					var GetStyleOfElement = function(el, css_name)
					{
						if (document.defaultView && document.defaultView.getComputedStyle)
							return document.defaultView.getComputedStyle(el, "").getPropertyValue(css_name);
						if (el.currentStyle)
							return el.currentStyle[ css_name.replace(/-(\w)/g, function(){ return arguments[1].toUpperCase(); }) ];
						return "";
					};

					// did not overpain top bar
					var lang_el = document.getElementById("lang");
					if (lang_el && ([0, "auto"].indexOf(GetStyleOfElement(lang_el, "z-index")) >= 0))	// english only adminer has not lang selector
						document.getElementById("lang").style.zIndex = "1";
					var breadcrumb_el = document.getElementById("breadcrumb");
					if ([0, "auto"].indexOf(GetStyleOfElement(breadcrumb_el, "z-index")) >= 0)
						document.getElementById("breadcrumb").style.zIndex = "1";
					var logout_el = document.getElementById("logout");
					if ([0, "auto"].indexOf(GetStyleOfElement(logout_el, "z-index")) >= 0)
						document.getElementById("logout").parentNode.style.zIndex = "1";

					var scroll_box = document.getElementById("content_scroll_box");		// Support plugin, which sumilate frameset scrolls
					if (!scroll_box)
						scroll_box = window;

					var funcShiftElements = function()
					{
						var scrollLeft = scroll_box.scrollX || scroll_box.scrollLeft;
						var i, el, directions = {
												down_pages:		{src_obj:result_table, shift_attr:"nextSibling"},
												up_pages:		{src_obj:result_table, shift_attr:"previousSibling"},
												up_elements:	{src_obj:result_table.parentNode, shift_attr:"previousSibling"}
												};
						for (i in directions)
						{
							var el = directions[i].src_obj;
							while (el && (el = el[ directions[i].shift_attr ]))
							{
								if (el.tagName
									&& (((i != "up_elements") && ((result_table.parentNode.tagName != "FORM") || (el.className.split(/\s+/).indexOf("pages") >= 0)))
										|| (i == "up_elements")
										)
									&& (el.id != "breadcrumb")
									&& (!el.getElementsByTagName("TABLE").length)		// forms with EXPLAIN table do not scroll
									)
								{
									if (!el.myOriginalPosition)
									{
										el.myOriginalPosition = el.style.position;
										el.style.position = "relative";
									}
									el.style.left = scrollLeft + "px";
								}
							}
						}
					};

					funcShiftElements(scroll_box);
					scroll_box.addEventListener("scroll", funcShiftElements, false);
				}
			}
		});
		</script>
<?php
	}
}