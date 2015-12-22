<?php

/** Display status and controls on table structure page
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableStructureAdvanced
{
	private $TYPES_LIST = ["status", "controls", "export", "quick_edit"];

	function __construct($types_list = [])
	{
		if ($types_list)
		{
			if (!is_array($types_list))
				$types_list = array($types_list);
			$this->TYPES_LIST = $types_list;
		}
	}

	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
<?
			if (in_array("status", $this->TYPES_LIST))
			{
?>
				// Display status on table structure page
				var current_location = document.location.href;
				var content = document.getElementById("content");
				var tables = content.getElementsByTagName("TABLE");
				if (!((current_location.indexOf("&sql=") < 0) && (current_location.indexOf("&table=") > 0) && tables.length))
					return;

				var status_anchor = document.createTextNode("");
				content.appendChild(status_anchor);

				var h3 = document.createElement("H3");
				var status_table = document.createElement("TABLE");
				status_table.cellSpacing = "0";

				// get title of new block (on correct language)
				var new_db_location = current_location.replace(/&table=([^&]*)/, "").replace(/&db=([^&]*)/, "");
				ajax(new_db_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("&amp;"+"status=") > 0))
					{
						var matches = request.responseText.match(/<a href=[\'\"][^\'\"]+&amp;status=[\'\"]>([^<]+)<\/a>/);
						h3.innerHTML = matches[1];
					}
				});

				// get table status labels and ids
				var all_dbs_location = current_location.replace(/&table=([^&]*)/, "");
				ajax(all_dbs_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"table") > 0) && (request.responseText.indexOf("tables"+"\[\]") > 0))
					{
						var table_html = request.responseText.split(/<table[^<>]*\>/)[1].split(/<\/table>/)[0].replace(/[\r\n]/g, "");
						table_html = table_html.replace(/<thead[^<>]*>(.*)<\/thead>/m, "");
						var table_head = RegExp.$1;
						table_html = table_html.replace(/<tfoot[^<>]*>.*<\/tfoot>/, "");
						var table_rows = table_html.split(/<tr[^>]*>/);
						var i, cnt;

						var table_data = [];
						var head_cols = table_head.split(/<td[^>]*>/);
						for (i=2; i<head_cols.length; i++)					// skip empty first item and "check-all" checkbox
							table_data.push( { label: head_cols[i] } );

						if (table_data.length)
						{
							// htmlspecialchars
							var foo_p = document.createElement("P");
							foo_p.appendChild( document.createTextNode( "?"+current_location.split("?")[1] ) );
							var current_params = foo_p.innerHTML;

							// find current table row
							cnt = table_rows.length;
							for (i=1; i<cnt; i++)	// first element is empty
								if (table_rows[i].indexOf(current_params) > 0)
								{
									var db_table_name = current_location.match("&table=([^&]+)")[1];
									var table_data_fields_regexp = new RegExp("\\sid\\=['\"]([^'\"]+\\-"+db_table_name+")['\"]", "mg");
									var match;
									var j = 0;
									while ((match = table_data_fields_regexp.exec( table_rows[i] )) !== null)
										table_data[j++].id = match[1];

									var possible_urls_prefix = current_params.replace(/&amp;table=.*/, "");
									var cols = table_rows[i].split(/<td[^>]*>/);
									for (j=2; j<cols.length; j++)
										if (cols[j].indexOf(possible_urls_prefix) > 0)
											table_data[j-2].tpl = cols[j];

									if (table_data[0].id)
									{
										// get table status data
										var dbs_data_location = current_location.replace(/&table=([^&]*)/, "")+"&script=db";
										ajax(dbs_data_location, function(request)
										{
											if (request.responseText)
											{
												var row, cell;
												var j, cnt = table_data.length;
												for (j=0; j<cnt; j++)
												{
													row = status_table.insertRow(-1);
													if (j % 2)
														row.className = "odd";

													cell = row.appendChild( document.createElement("TH") );
													cell.innerHTML = table_data[j].label;

													cell = row.appendChild( document.createElement("TD") );
													if (match = request.responseText.match( (new RegExp("['\"]"+table_data[j].id+"['\"]:\\s*['\"]([^'\"]+)['\"]", "m")) ))
													{
														if (table_data[j].tpl)
														{
															cell.innerHTML = table_data[j].tpl.replace(">?<", ">"+match[1]+"<");
														}
														else
															cell.innerHTML = match[1];
													}
												}

												if (status_table.rows.length)
												{
													content.insertBefore(h3, status_anchor);
													content.insertBefore(status_table, status_anchor);
												}
											}
										});
									}

									break;
								}
						}
					}
				});
<?
			}

			if (in_array("controls", $this->TYPES_LIST))
			{
?>
				// Display actions on table structure page
				var current_location = document.location.href;
				var content = document.getElementById("content");
				var tables = content.getElementsByTagName("TABLE");
				if (!((current_location.indexOf("&sql=") < 0) && (current_location.indexOf("&table=") > 0) && tables.length))
					return;

				var fieldset_anchor = document.createTextNode("");
				content.appendChild(fieldset_anchor);

				// get table status labels and ids
				var all_dbs_location = current_location.replace(/&table=([^&]*)/, "");
				var current_table_name = RegExp.$1;
				ajax(all_dbs_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"fieldset") > 0) && (request.responseText.indexOf("tables"+"\[\]") > 0))
					{
						var fieldset_arr = request.responseText.split(/(<fieldset[^<>]*\>)/);
						var fieldset_html = fieldset_arr[4].split(/<\/fieldset>/)[0].replace(/[\r\n]/g, "");
						fieldset_html = fieldset_html.replace(/^<legend[^<>]*\>.+<\/legend\>/, "");

						var form = document.createElement("FORM");
						form.action = all_dbs_location;
						form.method = "POST";

						var table_hidden_field = document.createElement("INPUT");
						table_hidden_field.type = "hidden";
						table_hidden_field.name = "redirect";
						table_hidden_field.value = document.location.search;	//document.location.href;
						form.appendChild(table_hidden_field);

						var table_hidden_field = document.createElement("INPUT");
						table_hidden_field.type = "hidden";
						table_hidden_field.name = "tables[]";
						table_hidden_field.value = current_table_name;
						form.appendChild(table_hidden_field);

						var fieldset = document.createElement("fieldset");	// fieldset_arr[3]
						fieldset.innerHTML = fieldset_html;
						form.appendChild(fieldset);

						content.insertBefore(form, fieldset_anchor);
					}
				});
<?
			}

			if (in_array("export", $this->TYPES_LIST))
			{
?>
				var form_anchor = document.createTextNode("");
				content.appendChild(form_anchor);

				var current_location = document.location.href;
				var export_db_location = current_location.replace(/&table=([^&]*)/, "") + "&dump=";
				var current_table_name = RegExp.$1;
				ajax(export_db_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"form") > 0) && (request.responseText.indexOf("tables"+"\[\]") > 0))
					{
						var forms_arr = request.responseText.split(/<form[^<>]*\>/);
						if ((forms_arr.length < 2) || (forms_arr[1].indexOf("tables"+"\[\]") < 0))
							return;

						var tables_arr = forms_arr[1].split(/(<table[^<>]*\>)/);
						if (tables_arr.length < 5)
							return;

						var hidden_input;
						var export_form = document.createElement("FORM");
						export_form.action = export_db_location;
						export_form.method = "POST";
						export_form.innerHTML = tables_arr[1] + tables_arr[2];

						hidden_input = document.createElement("INPUT");
						hidden_input.type = "hidden";
						hidden_input.name = "tables"+"[]";
						hidden_input.value = current_table_name;
						export_form.appendChild(hidden_input);

						hidden_input = document.createElement("INPUT");
						hidden_input.type = "hidden";
						hidden_input.name = "data"+"[]";
						hidden_input.value = current_table_name;
						export_form.appendChild(hidden_input);

						var h3 = document.createElement("H3");
						h3.innerHTML = document.getElementById("dump").innerHTML;

						content.insertBefore(h3, form_anchor);
						content.insertBefore(export_form, form_anchor);
					}
				});
<?
			}

			if (in_array("quick_edit", $this->TYPES_LIST))
			{
?>
				// Add possibility to quick edit table structure
				window.myConfirmRemoveRow = function(sender, key_name)
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
				};

				window.myOpenAddRow = function(sender, next_to_index)
				{
				};


				// add table fields to query textarea
				var current_location = document.location.href;
				var content = document.getElementById("content");
				var tables = content.getElementsByTagName("TABLE");
				if (!((current_location.indexOf("&sql=") < 0) && (current_location.indexOf("&table=") > 0) && tables.length))
					return;


				// modify table with fields
				var alter_location = current_location.replace(/&table=([^&]*)/, "&create=$1");
				// get controls from Alter-page (+, v, ^, x)
				ajax(alter_location, function(request)
				{
					if (request.responseText && (request.responseText.indexOf("<"+"table") > 0) && (request.responseText.indexOf("fields"+"[1][field]") > 0))
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
<?
			}
?>
		});
		</script>
<?
	}
}