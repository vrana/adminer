<?php

/** Use filter in tables list (more universal - compatible with skins)
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTablesListFilter
{
	function head()
	{
		if (Adminer::database() === null)
			return;
?>
		<script>
		// text wrap in <code> blocks
		var style = document.createElement('style');
		style.type = 'text/css';
		style.innerHTML = '#menu p .hidden { display: none !important; }';
		document.getElementsByTagName('head')[0].appendChild(style);

		document.addEventListener("DOMContentLoaded", function(evt)
		{
			var tables_box = document.getElementById("tables");
			if (!tables_box)	// no table -> nothing to do.
				return;

			var search_field = document.createElement("INPUT");
			search_field.style.width = "89%";
			search_field.style.backgroundImage = "url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAPklEQVR42mNgGBSgoaHhP6kYwwBSAIYBpBiCVTOxhuDVTMgQojTDALohJGmGAZghZGkGgVEDIKABW7IddAAAliX2vncoc8sAAAAASUVORK5CYII=)";
			search_field.style.backgroundRepeat = "no-repeat";
			search_field.style.backgroundPosition = "0 center";
			search_field.style.paddingLeft = "23px";
			search_field.style.marginBottom = "5px";

			search_field.addEventListener("input", function(evt)		// in IE/Edge 'input' work also for clear field by "X"
			{
				if (window.sessionStorage)
					sessionStorage.tableFilter = this.value;

				var reString = /\s+/;
				var reHiddenClass = /(^|\s)hidden(\s|$)/g;

				var orig_display_style = tables_box.style.display;
				tables_box.style.display = "none";

				var tables_links = tables_box.getElementsByTagName("A");
				for (var i=1; i<tables_links.length; i+=2)
				{
					var el;
					var text = tables_links[i].innerText || tables_links[i].textContent;
					if (text.indexOf(this.value) == -1)
					{
						if (tables_links[i].className.split(reString).indexOf("hidden") == -1)
						{
							tables_links[i-1].className += " hidden";	// icon
							tables_links[i].className += " hidden";		// name
							// <br>
							el = tables_links[i];
							while (el && (el.tagName != "BR"))
								el = el.nextSibling;
							if (el)
								el.className += " hidden";
						}
					}
					else
					{
						tables_links[i-1].className = tables_links[i-1].className.replace(reHiddenClass, " ");	// icon
						tables_links[i].className = tables_links[i].className.replace(reHiddenClass, " ");	// name
						// <br>
						el = tables_links[i];
						while (el && (el.tagName != "BR"))
							el = el.nextSibling;
						if (el)
							el.className = el.className.replace(reHiddenClass, " ");
						tables_links[i].innerHTML = text.replace(this.value, '<b>' + this.value + '</b>');
					}
				}
				tables_box.style.display = orig_display_style;
			});

			search_field.addEventListener("keydown", function(evt)		// Opera can't catch Esc-key on keyup
			{
				if ((evt || event).keyCode == 27)	// Esc did not create "input" event
				{
					this.value = "";

					var event = null;
					if (document.createEvent)
						(event = document.createEvent("Event")).initEvent("input", true, false);
					else
						event = new Event('input');
					search_field.dispatchEvent(event);
				}
			});


			// insert new element and tell about it via event of parent
			tables_box.parentNode.insertBefore(search_field, tables_box);
			var event = null;
			if (document.createEvent)
				(event = document.createEvent("Event")).initEvent("change", true, false);
			else
				event = new Event('change');
			tables_box.dispatchEvent( event );


			if (window.sessionStorage && sessionStorage.tableFilter)
			{
				search_field.value = sessionStorage.tableFilter;

				var funcEmulateInputEvent = function()
				{
					var event = null;
					if (document.createEvent)
						(event = document.createEvent("Event")).initEvent("input", true, false);
					else
						event = new Event('input');
					search_field.dispatchEvent(event);
				};

				funcEmulateInputEvent();
				window.addEventListener("focus", funcEmulateInputEvent);
			}
		});
		</script>
<?php
	}

}
