<?php

/** Add fields list to SQL command page
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlCommandTableFields
{
	function head()
	{
		if (Adminer::database() === null)
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// add table fields to query textarea
			var current_location = document.location.href;
			var form = document.getElementById("form");
			if ((current_location.indexOf("&sql=") > 0) && form && form.getElementsByTagName("TEXTAREA").length && !form.getElementsByTagName("SELECT").length)	// ignore if it built-in
			{
				var GetStyleOfElement = function(el, css_name)
				{
					if (document.defaultView && document.defaultView.getComputedStyle)
						return document.defaultView.getComputedStyle(el, "").getPropertyValue(css_name);
					if (el.currentStyle)
						return el.currentStyle[ css_name.replace(/-(\w)/g, function(){ return arguments[1].toUpperCase(); }) ];
					return "";
				};

				var idf_quotes = ["`", "`"];
				var IdfEscape = function(idf)
				{
					return idf_quotes[0] + idf.replace(new RegExp(idf_quotes[1], "g"), idf_quotes[1]+idf_quotes[1]) + idf_quotes[1];	// mssql has [] and escape has to duplicate second bracket (based on  driver of adminer)
				}

				var default_table_name = "";
				var matches;
				if ((matches = current_location.match("&table=([^&]+)")) || (matches = current_location.match("&tbl=([^&]+)")))		// second case is temporary, while adminer will not support `&table=` on this page
					default_table_name = matches[1];

				var sqlarea = form.getElementsByTagName("TEXTAREA")[0];
				if (sqlarea.name != "query")
					return;
				var sqlarea_fields_box = document.createElement("P");

				// new controls
				var foo_element = document.createElement("TEXTAREA");
				var tables_list = document.createElement("SELECT");
				var fields_list = document.createElement("SELECT");
				var add_fields_button = document.createElement("INPUT");

				// control events (with possibility to access to local variables)
				tables_list.addEventListener("change", function(event)
				{
					fields_list.innerHTML = "";
					ajax(current_location.replace(/&sql=[^&]*/, "").replace(/&table=[^&]*/, "")+"&table="+encodeURIComponent(tables_list.options[tables_list.selectedIndex].text), function(request)
					{
						if (request.responseText && (request.responseText.indexOf("<"+"table") > 0))
						{
							var table_html = request.responseText.split(/<table[^<>]*\>/)[1].split(/<\/table>/)[0].replace(/[\r\n]/g, "");
							table_html = table_html.replace(/<thead[^<>]*>(.*)<\/thead>/, "");
							var table_head = RegExp.$1;
							table_html = table_html.replace(/<tfoot[^<>]*>.*<\/tfoot>/, "");
							var table_rows = table_html.split(/<tr[^>]*>/);
							var cols, field_name, field_type, field_comment, new_option;
							var i, cnt;

							// detect first column with field name (title length > 1)
							var field_name_col_idx = 0;
							cols = table_head.split(/<t[hd][^>]*>/);
							cnt = cols.length
							for (i=1; i<cnt; i++)	// first line is empty
								if (cols[i].length > 1)
								{
									field_name_col_idx = i-1;
									break;
								}

							// get fields list
							cnt = table_rows.length;
							for (i=1; i<cnt; i++)	// first line is empty
							{
								cols = table_rows[i].split(/<td[^>]*>/);
								field_name = cols[field_name_col_idx].match(/<th[^>]*>([^<]*)/)[1];
								field_type = cols[field_name_col_idx+1].match(/<span[^>]*>([^<]*)/)[1];

								foo_element.innerHTML = cols[field_name_col_idx+2];
								field_comment = foo_element.value.replace(/(^\s+)|(\s+$)/g, "");

								new_option = document.createElement("OPTION");
								new_option.value = IdfEscape(field_name);
								new_option.text = field_name;
								new_option.title = field_type + (field_comment !== "" ? "\n> "+field_comment : "");
								fields_list.appendChild( new_option );
							}
						}
					});
				});
				add_fields_button.addEventListener("click", function(event)
				{
					// TODO: check delimiter between table name and field name for different database types. Did they all use "."(dot)?
					var fields_prefix = (tables_list.options[tables_list.selectedIndex].text != default_table_name
											? tables_list.options[tables_list.selectedIndex].value+"."
											: "");
					var fieldsList = [];
					var tableColumns = fields_list;
					for (var i=0; i<tableColumns.options.length; i++)
						if (tableColumns.options[i].selected)
							fieldsList.push( fields_prefix + tableColumns.options[i].value );
					if (fieldsList.length)
					{
						var sqlarea = document.getElementsByTagName("TEXTAREA")[0];
						var strFieldsList = fieldsList.join(", ");
						if (jush && sqlarea.className.match(/\bjush-[^\s]+/))
						{
							while (sqlarea && sqlarea.className.split(/\s+/).indexOf("jush") < 0)
								sqlarea = sqlarea.previousSibling;
							if (sqlarea)
								sqlarea.onpaste( { preventDefault:function(){}, clipboardData:{ getData:function(type){ return strFieldsList; } } } );
						}
						else if (document.selection)
						{
							sqlarea.focus();
							document.selection.createRange().text = strFieldsList;
						}
						else if (sqlarea.selectionStart || sqlarea.selectionStart == "0")
						{
							var pos = sqlarea.selectionStart + strFieldsList.length;
							sqlarea.value = sqlarea.value.substring(0, sqlarea.selectionStart) + strFieldsList + sqlarea.value.substring(sqlarea.selectionEnd, sqlarea.value.length)

							sqlarea.selectionStart = pos;
							sqlarea.selectionEnd = pos;
						}
						else
							sqlarea.value += strFieldsList;

						sqlarea.focus();
					}
				});
				fields_list.addEventListener("dblclick", function(event)
				{
					add_fields_button.dispatchEvent(new Event("click"));
				});

				// setup new elements
				sqlarea.parentNode.style.width = "75%";

				sqlarea_fields_box.style.position = "absolute";
				sqlarea_fields_box.style.left = "75%";

				if (default_table_name === "")
					tables_list.appendChild( document.createElement("OPTION") );
				var tables_array = [];
				var tables_box = document.getElementById("tables");
				var tables_links = tables_box.getElementsByTagName("A");
				for (var i=1; i<tables_links.length; i+=2)
					tables_array.push( tables_links[i].innerText || tables_links[i].textContent );

				if (tables_array.length)
				{
					// detect quotes type
					// TODO: try to get quotes from `jush` config
					eval(("var myAjax = "+ajax).replace("function ajax(", "function(").replace(/([\'\"]X-Requested-With[\'\"]\s*,\s*[\'\"])XMLHttpRequest([\'\"])/, "$1$2"));
					// via modified function, because we need full page, not only result table
					myAjax(current_location.replace(/&sql=[^&]*/, "").replace(/&table=[^&]*/, "")+"&select="+encodeURIComponent(tables_array[0])+"&limit=1", function(request)
					{
						var quotes, code_re = new RegExp("<code[^>]*>[^<]+([^\s\w])"+tables_array[0]+"([^\s\w])[^<]+<");
						if (request.responseText && (request.responseText.indexOf("<"+"code") > 0) && (quotes = request.responseText.match(code_re)))
						{
							idf_quotes = [quotes[1], quotes[2]];

							var new_option;
							for (var i=0; i<tables_array.length; i++)
							{
								new_option = document.createElement("OPTION");
								new_option.value = IdfEscape(tables_array[i]);
								new_option.text = tables_array[i];
								new_option.selected = (new_option.text === default_table_name);
								tables_list.appendChild( new_option );
							}


							tables_list.style.width = "100%";
							tables_list.style.font = GetStyleOfElement(sqlarea, "font");

							if (default_table_name !== "")
							{
								var event = null;
								if (document.createEvent)
								{
									event = document.createEvent("Event");
									event.initEvent("change", true, false);
								}
								else
									event = new Event('change');
								tables_list.dispatchEvent(event);
							}
							fields_list.multiple = 1;
							fields_list.size = sqlarea.rows-2;
							fields_list.style.width = "100%";
							fields_list.style.font = tables_list.style.font;

							add_fields_button.type = "button";
							add_fields_button.value = "<<";
							add_fields_button.style.padding = "3px 12px";
							add_fields_button.style.marginTop = "2px";
							add_fields_button.style.width = "100%";

							// add new controls to document
							sqlarea_fields_box.appendChild(tables_list);
							sqlarea_fields_box.appendChild(fields_list);
							sqlarea_fields_box.appendChild(add_fields_button);
							sqlarea.parentNode.parentNode.insertBefore(sqlarea_fields_box, sqlarea.parentNode);

							// additional styles
							tables_list.style.fontWeight = "bold";
							fields_list.style.height = (parseInt(GetStyleOfElement(sqlarea, "height"))-tables_list.offsetHeight-add_fields_button.offsetHeight)+"px"

							if (sqlarea.value == "")
								sqlarea.value = "SELECT * FROM " + (default_table_name ? IdfEscape(default_table_name) : "") + " WHERE 1 LIMIT 50";
						}
					});
				}

				var event = null;
				if (document.createEvent)
				{
					event = document.createEvent("Event");
					event.initEvent("resize", true, false);
				}
				else
					event = new Event('resize');
				window.dispatchEvent(event);		// compatibility with some other plugins after resize of textareas (Submit buttons move to the right)
			}


			// add "SQL commmand" to table content
			var sql_command;
			var menu_box = document.getElementById("menu");
			var i, p_list = menu_box.getElementsByTagName("P");
			for (i=0; i<p_list.length; i++)
				if (p_list[i].className.split(/\s+/).indexOf("links") != -1)
				{
					sql_command = p_list[i].getElementsByTagName("A")[0];
					break;
				}
			if (sql_command)
			{
				var content_box = document.getElementById("content");
				p_list = content_box.getElementsByTagName("P");
				for (i=0; i<p_list.length; i++)
					if (p_list[i].className.split(/\s+/).indexOf("links") != -1)
					{
						var a_list = p_list[i].getElementsByTagName("A");
						if (a_list[0].href.indexOf("&select=") > 0 && a_list[a_list.length-1].href.indexOf("&sql=") < 0)
						{
							var current_table = a_list[0].href.match(/&select=([^&]+)/);
							if (current_table)
								sql_command.href += "&tbl=" + current_table[1];				// built-in link has correct parameter name. This is just temporary solution.
							p_list[i].appendChild( sql_command.cloneNode(true) );
						}

						break;
					}
			}
		});
		</script>
<?php
	}
}