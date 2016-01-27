<?php

/** Move desc-sort arrow before column title
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableSortDescBeforeTitle
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			var tbl = document.getElementById("table");
			if (!tbl || !tbl.tHead || !tbl.tHead.rows.length)
				return;

			var j, spans, sub_links, desc_span;
			var cells = tbl.tHead.rows[0].cells;
			var i, cnt = cells.length;
			for (i=0; i<cnt; i++)
				if (cells[i].tagName == "TH")
				{
					// move desc arrow
					spans = cells[i].getElementsByTagName("SPAN");
					for (j=spans.length-1; j>=0; j--)
						if (spans[j].className.split(/\s+/).indexOf("column") >= 0)
						{
							sub_links = spans[j].getElementsByTagName("A");
							if (!sub_links.length || (sub_links[0].href.indexOf("&desc") < 0))
								break;

							desc_span = spans[j].cloneNode(false);
							desc_span.appendChild( spans[j].removeChild(sub_links[0]) );
							desc_span.style.marginLeft = "-21px";
							cells[i].insertBefore(desc_span, cells[i].childNodes[0]);
							break;
						}
				}

		});
		</script>
<?
	}
}