<?php

/** Display field comments on record edit page
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableRecordFieldDetails
{
	function head()
	{
		if (Adminer::database() === null)
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// Output field comments in record editor
			var tables_list = document.getElementsByTagName("TABLE");
			if (!tables_list.length)
				return;

			var field_cells = {};
			var spans_list, tbl_rows = tables_list[0].rows;
			var i, cnt = tbl_rows.length;
			for (i=0; i<cnt; i++)
				if (tbl_rows[i].cells.length)
				{
					if (tbl_rows[i].cells[0].getElementsByTagName("SMALL").length)		// check on built-in solution
						return;

					spans_list = tbl_rows[i].cells[0].getElementsByTagName("SPAN");
					if (spans_list.length)
						field_cells[ spans_list[0].innerHTML ] = tbl_rows[i].cells[0];
					else		// no field name => wrong table?
						return;
				}
				else			// has empty rows => wrong table?
					return;

			var current_location = document.location.href;
			var table_structure_location = current_location.replace("&edit=", "&table=");
			ajax(table_structure_location, function(request)
			{
				if (request.responseText && (request.responseText.indexOf("<"+"table") > 0))
				{
					var table_html = request.responseText.split(/<table[^<>]*\>/)[1].split(/<\/table>/)[0].replace(/[\r\n]/g, "");
					table_html = table_html.replace(/<thead[^<>]*>.*<\/thead>/m, "");
					table_html = table_html.replace(/<tfoot[^<>]*>.*<\/tfoot>/m, "");

					var small_el;
					var row_cols, table_rows = table_html.split(/<tr[^>]*>/);
					var i, rows_cnt = table_rows.length;
					for (i=0; i<rows_cnt; i++)
					{
						row_cols = table_rows[i].split(/<t[hd][^>]*>/);
						if (field_cells[ row_cols[1] ] && (row_cols[3].replace("&nbsp;", "") !== ""))	// has cell with this field name + has comment
						{
							field_cells[ row_cols[1] ].appendChild( document.createElement("BR") );
							small_el = document.createElement("SMALL");
							small_el.innerHTML = row_cols[3];
							field_cells[ row_cols[1] ].appendChild( small_el );
						}
					}

				}
			});
		});
		</script>
<?php
	}
}