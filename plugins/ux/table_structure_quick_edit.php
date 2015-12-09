<?php

/** Add possibility to quick edit table structure
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableStructureQuickEdit
{
	function head()
	{
?>
		<script>
		function myConfirmRemoveRow(sender, key_name)
		{
			if (!confirm("Are you sure?"))
				return true;

			if (editingRemoveRow(sender, key_name))
			{
				var form = sender.form;
				sender.parentNode.removeChild(sender);		// `drop_col[]` has not to be send. In original it never submit form
															// possible have to send in case with move up/down table fields
				form.submit();
			}
		}

		function myOpenAddRow(sender, next_to_index)
		{
		}


		document.addEventListener("DOMContentLoaded", function(event)
		{
			// add table fields to query textarea
			var current_location = document.location.href;
			var content = document.getElementById("content");
			var tables = content.getElementsByTagName("TABLE");
			if (!((current_location.indexOf("&sql=") < 0) && (current_location.indexOf("&table=") > 0) && tables.length))
				return;


			// modify table with fields
			if (tables.length > 0)
			{
				var alter_location = current_location.replace(/&table=([^&]*)/, "&create=$1");
				// get controls from Alter-page (+, v, ^, x)
				ajax(alter_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"table") > 0) && (request.responseText.indexOf("fields[1][field]") > 0))
					{
						var fields_table = tables[0];
						var new_form = document.createElement("FORM");
						new_form.method = "post";
						new_form.action = alter_location;
						fields_table.parentNode.insertBefore(new_form, fields_table);
						new_form.appendChild(fields_table);

						var new_token = document.createElement("INPUT");
						new_token.type = "hidden";
						new_token.name = "token";
						new_token.value = request.responseText.match(/<input\s[^>]*name=[\'\"]token[\'\"]\s[^>]*value=[\'\"]([^\'\"]+)[\'\"]/)[1];
						new_form.appendChild(new_token);

						var fields_rows = fields_table.tBodies[0].rows;
						var cell, new_cell, new_el;
						var j;

						var table_html = request.responseText.split(/<table[^<>]*\>/)[1].split(/<\/table>/)[0].replace(/[\r\n]/g, "");
						table_html = table_html.replace(/<thead[^<>]*>(.*)<\/thead>/m, "");
						var table_head = RegExp.$1;
						table_html = table_html.replace(/<tfoot[^<>]*>.*<\/tfoot>/, "");
						var table_rows = table_html.split(/<tr[^>]*>/);
						var cols, field_name, field_controls;
						var i, cnt;

						var head_cols = table_head.split(/<td[^>]*>/);
						new_cell = document.createElement("TD");
						new_cell.innerHTML = head_cols[8];
						fields_table.tHead.rows[0].appendChild(new_cell);

						cnt = table_rows.length;
						for (i=1; i<cnt; i++)	// first element is empty
						{
							cols = table_rows[i].split(/<td[^>]*>/);
							field_name = cols[0].match(/\svalue\=[\'\"]([^\'\"]*)[\'\"]/)[1];
							field_controls = cols[8];

							for (j=0; j<fields_rows.length; j++)
							{
								cell = fields_rows[j].cells[0];
								if ((cell.innerText || cell.textContent) == field_name)
								{
									new_cell = document.createElement("TD");
									new_cell.innerHTML = field_controls.replace("editingRemoveRow", "myConfirmRemoveRow").replace("editingAddRow", "myOpenAddRow");
									fields_rows[j].appendChild(new_cell);
									break;
								}
							}
						}
					}
				});

				// get controls from Select-page (Edit)
				ajax(current_location.replace(/&table=([^&]*)/, "&select=$1")+"&limit=1", function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"table") > 0) && (request.responseText.indexOf("<"+"code") > 0))
					{
						// take "edit" word from <code> post link
						// take edit icon, if possible
					}
				});
			}

			// modify table with indexes
			if (tables.length > 1)
			{
				var indexes_location = current_location.replace(/&table=([^&]*)/, "&indexes=$1");
				ajax(indexes_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"table") > 0) && (request.responseText.indexOf("indexes[1][type]") > 0))
					{
						var indexes_table = tables[1];
						var new_form = document.createElement("FORM");
						new_form.method = "post";
						new_form.action = indexes_location;
						indexes_table.parentNode.insertBefore(new_form, indexes_table);
						new_form.appendChild(indexes_table);

						var new_token = document.createElement("INPUT");
						new_token.type = "hidden";
						new_token.name = "token";
						new_token.value = request.responseText.match(/<input\s[^>]*name=[\'\"]token[\'\"]\s[^>]*value=[\'\"]([^\'\"]+)[\'\"]/)[1];
						new_form.appendChild(new_token);

						var indexes_rows = indexes_table.tBodies[0].rows;
						var cell, new_cell, new_el;
						var j;

						var table_html = request.responseText.split(/<table[^<>]*\>/)[1].split(/<\/table>/)[0].replace(/[\r\n]/g, "");
						table_html = table_html.replace(/<thead[^<>]*>(.*)<\/thead>/m, "");
						var table_head = RegExp.$1;
						table_html = table_html.replace(/<tfoot[^<>]*>.*<\/tfoot>/, "");
						var table_rows = table_html.split(/<tr[^>]*>/);
						var cols, index_type_field_name, index_type_field_value, index_name, index_controls;
						var i, cnt;

						cnt = table_rows.length;
						for (i=1; i<cnt; i++)	// first element is empty
						{
							cols = table_rows[i].split(/<td[^>]*>/);
							if (cols[1].indexOf(" selected>") < 0)				// not new line
								continue;

							index_type_field_name = cols[1].match(/\sname\=[\'\"]([^\'\"]*)[\'\"]/)[1];
							index_type_field_value = cols[1].match(/\<option\s+selected\>([^<]+)/)[1];
							index_name_field_name = cols[3].match(/\sname\=[\'\"]([^\'\"]*)[\'\"]/)[1];
							index_name = cols[3].match(/\svalue\=[\'\"]([^\'\"]*)[\'\"]/)[1];
							index_controls = cols[4];

							for (j=0; j<indexes_rows.length; j++)
							{
								cell = indexes_rows[j].cells[0];
								if (indexes_rows[j].title == index_name)
								{
									new_el = document.createElement("INPUT");
									new_el.type = "hidden";
									new_el.name = index_type_field_name;
									new_el.value = index_type_field_value
									indexes_rows[j].cells[0].appendChild(new_el);

									new_el = document.createElement("INPUT");
									new_el.type = "hidden";
									new_el.name = index_name_field_name;
									new_el.value = index_name
									indexes_rows[j].cells[0].appendChild(new_el);

									new_cell = document.createElement("TD");
									new_cell.innerHTML = index_controls.replace("editingRemoveRow", "myConfirmRemoveRow");
									indexes_rows[j].appendChild(new_cell);
									break;
								}
							}
						}
					}
				});
			}
		});
		</script>
<?
	}
}