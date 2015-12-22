<?php

/** Frameset simulator
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFramesetSimulator
{
	private $SCROLL_ONLY_TABLES_LIST;

	function __construct($scroll_only_tables_list = false)
	{
		$this->SCROLL_ONLY_TABLES_LIST = $scroll_only_tables_list;
	}

	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// frameset scrolls simulator (better when this plugin is first)
			var GetStyleOfElement = function(el, css_name)
			{
				if (document.defaultView && document.defaultView.getComputedStyle)
					return document.defaultView.getComputedStyle(el, "").getPropertyValue(css_name);
				if (el.currentStyle)
					return el.currentStyle[ css_name.replace(/-(\w)/g, function(){ return arguments[1].toUpperCase(); }) ];
				return "";
			};

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

			// change menu scrolls
			var restoredScrolls;
			var menu = document.getElementById("menu");
			var tables = document.getElementById("tables");
			var scroll_box = menu;
			if (GetStyleOfElement(menu, "overflow") == "visible")
			{
				// prepare style for correct working, if current skin do not ready for it
				menu.style.position = "fixed !important";
				menu.style.overflow = "auto";
				menu.style.top = "40px";
				menu.style.bottom = "0";
				menu.style.margin = "0";
			}

			tables.removeAttribute("onmouseover");
			tables.onmouseover = null;
			tables.removeAttribute("onmouseout");
			tables.onmouseout = null;
			tables.style.overflow = "visible";

<?
			if ($this->SCROLL_ONLY_TABLES_LIST)
			{
?>
				// tables list scroll only for list
				menu.addEventListener("change", function()
				{
					tables.style.position = "static";
					tables.style.top = tables.offsetTop+"px";
					tables.style.position = "absolute";

					if (restoredScrolls)
					{
						scroll_box.scrollLeft = restoredScrolls[0];
						scroll_box.scrollTop = restoredScrolls[1];
					}
				});

				var event = null;
				if (document.createEvent)
					(event = document.createEvent("Event")).initEvent("change", true, false);
				else
					event = new Event('change');
				menu.dispatchEvent( event );

				tables.style.bottom = 0;
				tables.style.left = GetStyleOfElement(menu, "padding-left");
				tables.style.right = 0;
				tables.style.marginBottom = 0;
				tables.style.overflow = "auto !important";
				tables.style.setProperty("overflow", "auto", "important");

				scroll_box = tables;
<?
			}
?>

			// setup content box
			var content = document.getElementById("content");
			var content_box = document.createElement("DIV");
			content_box.id = "content_scroll_box";
			content.parentNode.insertBefore( content_box, content );
			content_box.appendChild(content);
			content_box.style.position = "absolute";

			// setup content box sizes
			var menu_css_rules = GetCSSRulesOfElement(menu).join("");
			var menu_width_dimension = menu_css_rules.match(/[\s;{]width\s*:\s*[0-9\.]+([a-z%]+)[\s};]/);
			var content_additional_left_shift = content.offsetLeft - menu.offsetWidth;
			if (menu_width_dimension.length && (menu_width_dimension[1] == "%"))	// keep dynamic only for percentage width
			{
				// detect percent alternative of current menu width
				var menu_width_expander = parseInt(GetStyleOfElement(menu, "padding-left")) + parseInt(GetStyleOfElement(menu, "padding-right")) + parseInt(GetStyleOfElement(menu, "border-left-width")) + parseInt(GetStyleOfElement(menu, "border-right-width"))
				var menu_default_width = menu.offsetWidth - menu_width_expander;
				var menu_default_percent = Math.round( menu_default_width / (window.innerWidth / 100) );
				var menu_backconvertion_width = menu_default_percent * (window.innerWidth / 100);
				var content_left_shift_percent = Math.floor( (menu_backconvertion_width + menu_width_expander) / (window.innerWidth / 100) );
				content_box.style.left = content_left_shift_percent+"%";	// "23%";
			}
			else
			{
				content_box.style.left = menu.offsetWidth+"px";
			}
			content_box.style.paddingLeft = content_additional_left_shift+"px";

			content_box.style.right = "0";
			content_box.style.top = GetStyleOfElement(content, "margin-top");
			content_box.style.bottom = "0";
			content_box.style.overflow = "auto";
			content.style.marginLeft = "0";
			content.style.marginTop = "0";
			content_box.focus();


			// remember navigation menu scrolls between page reloads
			if (window.sessionStorage)
			{
				scroll_box.addEventListener("scroll", function()
				{
					sessionStorage.menuScrolls = [ this.scrollLeft, this.scrollTop ].join("x");
				});

				if (sessionStorage.menuScrolls)
				{
					restoredScrolls = sessionStorage.menuScrolls.split("x");
					scroll_box.scrollLeft = restoredScrolls[0];
					scroll_box.scrollTop = restoredScrolls[1];
				}
			}
		});
		</script>
<?
	}
}