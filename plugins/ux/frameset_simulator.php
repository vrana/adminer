<?php

/** Frameset simulator
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerFramesetSimulator
{
	private $DETECT_SINGLE_LANGUAGE_MODE;
	private $SCROLL_ONLY_TABLES_LIST;

	function __construct($scroll_only_tables_list = false, $detect_single_language_mode = true)
	{
		$this->DETECT_SINGLE_LANGUAGE_MODE = $detect_single_language_mode;
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

			// detect single lang version for possibility to move menu to top
<?php
			if ($this->DETECT_SINGLE_LANGUAGE_MODE)
			{
?>
				if (!document.getElementById("lang"))
					document.body.className += " single-lang";
<?php
			}
?>

			// change menu scrolls
			var restoredMenuScrolls;
			var menu = document.getElementById("menu");
			var tables = document.getElementById("tables");
			var menu_scroll_box = menu;
			if (GetStyleOfElement(menu, "overflow") == "visible")
			{
				// prepare style for correct working, if current skin do not ready for it
				menu.style.position = "fixed !important";
				if (GetStyleOfElement(menu, "position") == "static")
					menu.style.top = "40px";
				menu.style.bottom = "0";
				menu.style.margin = "0";
			}

			if (tables)
			{
				tables.removeAttribute("onmouseover");
				tables.onmouseover = null;
				tables.removeAttribute("onmouseout");
				tables.onmouseout = null;
				tables.style.overflow = "visible";
			}

<?php
			if ($this->SCROLL_ONLY_TABLES_LIST)
			{
?>
				if (tables && (GetStyleOfElement(menu, "overflow") == "auto"))
					menu.style.overflow = "visible";

				// tables list scroll only for list
				menu.addEventListener("change", function()
				{
					if (tables)
					{
						tables.style.position = "static";			// temporary, for next .offsetTop
						tables.style.top = tables.offsetTop+"px";
						tables.style.position = "absolute";
					}

					if (restoredMenuScrolls)
					{
						menu_scroll_box.scrollLeft = restoredMenuScrolls[0];
						menu_scroll_box.scrollTop = restoredMenuScrolls[1];
					}
				});

				var event = null;
				if (document.createEvent)
					(event = document.createEvent("Event")).initEvent("change", true, false);
				else
					event = new Event('change');
				menu.dispatchEvent( event );

				if (tables && (GetStyleOfElement(menu, "overflow") == "visible"))
				{
					tables.style.bottom = 0;
					tables.style.left = 0;
					tables.style.paddingLeft = GetStyleOfElement(menu, "padding-left");
					tables.style.right = 0;
					tables.style.marginBottom = 0;
					tables.style.overflow = "auto !important";
					tables.style.setProperty("overflow", "auto", "important");

					menu_scroll_box = tables;
				}
<?php
			}
?>

			// setup content box
			var content = document.getElementById("content");
			if (GetStyleOfElement(content, "position") == "absolute")
				content.style.position = "static";

			var content_scroll_box = document.createElement("DIV");
			content_scroll_box.id = "content_scroll_box";
			content_scroll_box.tabIndex = -1;									// without tabIndex focus() did not work
			content.parentNode.insertBefore( content_scroll_box, content );
			content_scroll_box.appendChild(content);
			content_scroll_box.style.position = "absolute";

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
				content_scroll_box.style.left = content_left_shift_percent+"%";	// "23%";
			}
			else
			{
				content_scroll_box.style.left = menu.offsetWidth+"px";
			}
			content_scroll_box.style.paddingLeft = content_additional_left_shift+"px";

			content_scroll_box.style.right = "0";
			content_scroll_box.style.top = GetStyleOfElement(content, "margin-top");
			content_scroll_box.style.bottom = "0";
			content_scroll_box.style.overflow = "auto";
			document.body.style.overflow = "hidden";
			content.style.marginLeft = "0";
			content.style.marginTop = "0";
			content.style.overflow = "visible !important";
			content_scroll_box.focus();

			// fix breadcrumb position in some skins
			var breadcrumb = document.getElementById("breadcrumb");
			if (GetStyleOfElement(breadcrumb, "position") == "absolute")
			{
				breadcrumb.style.position = "fixed";
				if (GetStyleOfElement(breadcrumb, "left") == "auto")
					breadcrumb.style.left = content_scroll_box.style.left;
			}

			// fix forms overflow in some skins
			var forms = document.getElementsByTagName("FORM");
			if (forms.length && (GetStyleOfElement(forms[0], "overflow") == "auto"))
				for (var i=0; i<forms.length; i++)
					forms[i].style.overflow = "visible";

			// resizer
			var style = document.createElement('style');
			style.type = 'text/css';
			style.innerHTML = '.ux-unselectable { -webkit-touch-callout:none; -webkit-user-select:none; -khtml-user-select:none; -moz-user-select:none; -ms-user-select:none; user-select: none; }';
			document.getElementsByTagName('HEAD')[0].appendChild(style);

			var resize_bar = document.createElement("DIV");
			resize_bar.style.position = "fixed";
			resize_bar.style.top = "0";
			resize_bar.style.bottom = "0";
			resize_bar.style.backgroundColor = "transparent";
			var menu_right_border = menu_css_rules.match(/[\s;{]border-right-width\s*:\s*([0-9\.]+px)[\s};]/);
			if (!menu_right_border)
				menu_right_border = menu_css_rules.match(/[\s;{]border-right\s*:\s*([0-9\.]+px)[\s};]/);
			if (menu_right_border)
			{
				resize_bar.style.left = (parseInt(content_scroll_box.style.left) - parseInt(menu_right_border[1])) + "px";
				resize_bar.style.width = menu_right_border[1];
			}
			else
			{
				resize_bar.style.left = content_scroll_box.style.left;
				resize_bar.style.width = "5px";
			}
			resize_bar.style.cursor = "w-resize";

			var default_values = {
									w: menu.offsetWidth - parseInt(GetStyleOfElement(menu, "padding-left")) - parseInt(GetStyleOfElement(menu, "padding-right")) - parseInt(GetStyleOfElement(menu, "border-left-width")) - parseInt(GetStyleOfElement(menu, "border-right-width")),
									l: resize_bar.style.left
									};

			resize_bar.addEventListener("dblclick", function(event)
			{
				menu.style.width = default_values.w + "px";
				content_scroll_box.style.left = menu.offsetWidth + "px";
				resize_bar.style.left = default_values.l;
				if (window.sessionStorage)
					sessionStorage.menuSize = menu.style.width;
			});
			resize_bar.addEventListener("mousedown", function(event)
			{
				resize_bar["myResizeOffset"] = { x:event.pageX, w:menu.offsetWidth - parseInt(GetStyleOfElement(menu, "padding-left")) - parseInt(GetStyleOfElement(menu, "padding-right")) - parseInt(GetStyleOfElement(menu, "border-left-width")) - parseInt(GetStyleOfElement(menu, "border-right-width")) };
				document.body.className += " ux-unselectable";
			});
			document.addEventListener("mouseup", function(event)
			{
				if (resize_bar["myResizeOffset"])
				{
					if (window.sessionStorage)
						sessionStorage.menuSize = menu.style.width;
					resize_bar["myResizeOffset"] = null;
					document.body.className = document.body.className.replace(/ux-unselectable/g, "");
				}
			});
			document.addEventListener("mousemove", function(event)
			{
				var resizeOffset;
				if (resizeOffset = resize_bar["myResizeOffset"])
				{
					var newWidth = resizeOffset.w - (resizeOffset.x - event.pageX);
					menu.style.width = newWidth + "px";
					content_scroll_box.style.left = menu.offsetWidth + "px";

					if (menu_right_border)
						resize_bar.style.left = (parseInt(content_scroll_box.style.left) - parseInt(menu_right_border[1])) + "px";
					else
						resize_bar.style.left = content_scroll_box.style.left;
				}
			});
			menu.parentNode.appendChild(resize_bar);

			// remember navigation menu scrolls between page reloads
			if (window.sessionStorage)
			{
				if (sessionStorage.menuSize)
				{
					menu.style.width = sessionStorage.menuSize;
					content_scroll_box.style.left = menu.offsetWidth + "px";

					if (menu_right_border)
						resize_bar.style.left = (parseInt(content_scroll_box.style.left) - parseInt(menu_right_border[1])) + "px";
					else
						resize_bar.style.left = content_scroll_box.style.left;
				}


				// menu scroll
				if (sessionStorage.menuScrolls)
				{
					restoredMenuScrolls = sessionStorage.menuScrolls.split("x");
					menu_scroll_box.scrollLeft = restoredMenuScrolls[0];
					menu_scroll_box.scrollTop = restoredMenuScrolls[1];
				}

				var funcStoreMenuScrolls = function()
				{
					sessionStorage.menuScrolls = [ menu_scroll_box.scrollLeft, menu_scroll_box.scrollTop ].join("x");
				};
				// watch for scrolls and focus (for cases with windows with different scrolls)
				window.addEventListener("focus", funcStoreMenuScrolls);
				menu_scroll_box.addEventListener("scroll", funcStoreMenuScrolls);


				// content of select scroll
				var page_url_mask = document.location.search.match(/.*&select=[^&]+/);
				if (page_url_mask)
				{
					page_url_mask = page_url_mask[0];
					if (sessionStorage.contentScrolls && (sessionStorage.lastContentUrlMask == page_url_mask))
					{
						var restoredScrolls = sessionStorage.contentScrolls.split("x");
						content_scroll_box.scrollLeft = restoredScrolls[0];
						content_scroll_box.scrollTop = restoredScrolls[1];
					}

					var funcStoreContentScrolls = function()
					{
						sessionStorage.lastContentUrlMask = page_url_mask;
						sessionStorage.contentScrolls = [ content_scroll_box.scrollLeft, content_scroll_box.scrollTop ].join("x");
					};
					// watch for scrolls and focus (for cases with windows with different scrolls)
					funcStoreContentScrolls();
					window.addEventListener("focus", funcStoreContentScrolls);
					content_scroll_box.addEventListener("scroll", funcStoreContentScrolls);
				}
				else
					sessionStorage.lastContentUrlMask = "";
			}
		});
		</script>
<?php
	}
}