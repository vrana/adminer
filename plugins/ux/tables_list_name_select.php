<?php

/** Exchange "Show structure" <-> "Select table" in navigation menu
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTablesListNameSelect
{
	function head()
	{
		if (Adminer::database() === null)
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			var GetCSSRulesOfElement = function(el)
			{
				var sheets = document.styleSheets, arr = [];
				el.matches = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector || el.oMatchesSelector;
				for (var i in sheets)
				{
					var rules = sheets[i].rules || sheets[i].cssRules;
					for (var r in rules)
						if (el.matches(rules[r].selectorText))
							arr.push(rules[r].cssText);
				}
				return arr;
			};

			// swap Show structure <-> Select table from top links
			var structure_icon = "";
			var content_box = document.getElementById("content");
			if (content_box)
			{
				var links = content_box.childNodes[0];
				while (links && (!links.className || links.className.indexOf("links") < 0))
					links = links.nextSibling;
				if (links)
				{
					var a_list = links.getElementsByTagName("A");
					if ((a_list.length > 1) && (a_list[0].href.indexOf("&select=") > 0) && (a_list[1].href.indexOf("&table=") > 0))
					{
						var structure_styles = GetCSSRulesOfElement(a_list[1]);
						for (var i=structure_styles.length-1; i>=0; i--)
							if (structure_styles[i].indexOf("url(") && structure_styles[i].match(/[\s: ](url\([^\)]+\))/i))
							{
								structure_icon = RegExp.$1;
								break;
							}
						links.insertBefore(a_list[1], a_list[0]);
					}
				}
			}

			// swap Show structure <-> Select table from navigation menu
			var tables_box = document.getElementById("tables");
			if (tables_box)
			{
				var tbl_links = tables_box.getElementsByTagName("A");
				var first_link;
				var i, cnt = tbl_links.length;
				if (cnt > 1)
				{
					// rewrite old broken CSS rules
					if (!document.styleSheets)
						return;
					var cssSelector, cssText, styleSheets = document.styleSheets;
					var j, rules = [];
					for (i=0; i<styleSheets.length; i++)
					{
						if (styleSheets[i].cssRules)
							rules = styleSheets[i].cssRules;
						else if (styleSheets[i].rules)
							rules = styleSheets[i].rules;
						else
							break;

						for (j=rules.length-1; j>=0; j--)
						{
							if ((rules[j].cssText.indexOf('#menu p a[href*="&select="]') >= 0)
								|| (rules[j].cssText.indexOf("#menu p a[href*='&select=']") >= 0)
								|| (rules[j].cssText.indexOf("#menu p a.select") >= 0)
								)
							{
//								rules[j].cssText = rules[j].cssText.replace(/\#menu p a\[href\*\=["']\&select\=["']\]/, '#menu p a.select');	// Firefox and IE did not support this method
								cssSelector = rules[j].selectorText.replace(/\#menu p a\[href\*\=["']\&select\=["']\]/, '#menu p a.select');
								cssText = rules[j].style.cssText;
								if (structure_icon && (cssText.indexOf("content:") >= 0))
									cssText = cssText.replace(/content:\s*url\([^\)]+\);/, "content: "+structure_icon+";") + " opacity:0.7;";
								styleSheets[i].deleteRule(j);										// removeRule() uses other indexes
								styleSheets[i].insertRule(cssSelector + " {" + cssText + "}", j);	// addRule() uses other indexes
							}
						}
					}
				}
				for (i=0; i<cnt; i+=2)
				{
					first_link = [ tbl_links[i].href, tbl_links[i].title ];

					tbl_links[i].href = tbl_links[i+1].href;
					tbl_links[i].title = tbl_links[i+1].title;

					tbl_links[i+1].href = first_link[0];
					tbl_links[i+1].title = first_link[1];
					if (tbl_links[i].className.split(/\s+/).indexOf("active") != -1)
						tbl_links[i+1].className += " active";
				}
			}
		});
		</script>
<?php
	}
}