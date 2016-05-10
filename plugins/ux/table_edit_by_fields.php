<?php

/** Table editor reduce submitted fields
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableEditByFields
{
	function head()
	{
		if (Adminer::database() === null)
			return;

		if (!function_exists("get_page_table"))		// not modified adminer sources did not support this plugin
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			var fieldsTable = document.getElementById("edit-fields");
			if (!fieldsTable)
				return false;

			// get text words (edit / modify)
			// TODO: use JS side dictionary, when it will be ready
			var current_location = document.location.href;
			eval(("var myAjax = "+ajax).replace("function ajax(", "function(").replace(/([\'\"]X-Requested-With[\'\"]\s*,\s*[\'\"])XMLHttpRequest([\'\"])/, "$1$2"));
			// via modified function, because we need full page, not only result table
			myAjax(current_location.replace(/&create=([^&]*)/, "&select=$1")+"&limit=1", function(request)
			{
				var edit_word, code_re = new RegExp("<code[^>]*>[^<]+</code>\\s*<span[^>]*>[^<]+</span>\\s*<a[^>]*>([^<]+)</a>");
				if (request.responseText && (request.responseText.indexOf("<"+"code") > 0) && (edit_word = request.responseText.match(code_re)))
				{
					// take "edit" word from <code> post link
					edit_word = edit_word[1].toLowerCase();
					var funcEditTableField = function(evt)
					{
						if (evt.target && evt.target.type && (evt.target.type == "image"))
							return false;
						if (evt.keyCode && (evt.keyCode == 9))
							return false;

						var row = evt;
						if (evt.target)
							row = this;

						while (row.tagName != "TR")
							row = row.parentNode;

						var inputs, j, inputs_cnt;
						inputs = row.getElementsByTagName("INPUT");
						inputs_cnt = inputs.length;
						for (j=0; j<inputs_cnt; j++)
							if ((inputs[j].type != "image") && (inputs[j].type != "hidden"))
								inputs[j].disabled = false;

						inputs = row.getElementsByTagName("SELECT");
						inputs_cnt = inputs.length;
						for (j=0; j<inputs_cnt; j++)
							inputs[j].disabled = false;

						row.cells[0].innerHTML = "";

						if (evt.target)
							evt.target.focus();
					}

					uxEditableFieldBeforeAction = function(sender)
					{
						funcEditTableField(sender);
						return true;
					}

					var i, headers = [""];			// first column - "edit"
					if (fieldsTable.rows.length)
					{
						var cells_cnt = fieldsTable.rows[0].cells.length;
						for (i=0; i<cells_cnt; i++)
							headers.push( fieldsTable.rows[0].cells[i].innerText.replace(/(^\s+|\s+$)/g, "") );
					}

					// add new column with "edit" link
					var edit_link = document.createElement("A");
					edit_link.href = "javascript:;";
					edit_link.innerText = edit_word;

					var new_cell, inputs, j, inputs_cnt, cell;
					var rows_cnt = fieldsTable.rows.length;
					for (i=0; i<rows_cnt; i++)
					{
						new_cell = fieldsTable.rows[i].insertCell(0);
						if (new_cell.parentNode.parentNode.tagName == "TBODY")
						{
							new_cell.appendChild( edit_link.cloneNode(true) );//.addEventListener("click", funcEditTableField);
							inputs = new_cell.parentNode.getElementsByTagName("INPUT");
							inputs_cnt = inputs.length;
							for (j=0; j<inputs_cnt; j++)
								if (inputs[j].type == "image")
								{
									if (inputs[j].name.indexOf("add[") === 0)
										inputs[j].setAttribute("onclick", inputs[j].getAttribute("onclick").replace(/return /, "return uxEditableFieldBeforeAction(this) && "));
								}
								else if (inputs[j].type != "hidden")
								{
									if (inputs[j].name.indexOf("][field]") < 0)
										inputs[j].disabled = true;
									else if (inputs[j].value == "")
										inputs[j].focus();

									if (inputs[j].title === "")
									{
										cell = inputs[j];
										while (cell && !cell.cellIndex)
											cell = cell.parentNode;
										if (cell)
											inputs[j].title = headers[ cell.cellIndex ];
									}
								}

							inputs = new_cell.parentNode.getElementsByTagName("SELECT");
							inputs_cnt = inputs.length;
							for (j=0; j<inputs_cnt; j++)
								inputs[j].disabled = true;
						}
						fieldsTable.rows[i].addEventListener("keyup", funcEditTableField);
						fieldsTable.rows[i].addEventListener("mouseup", funcEditTableField);
					}

					// fix "Default" and "Comment" checkbox handlers
					var inp_defaults = document.getElementsByName("defaults");
					for (i=0; i<inp_defaults.length; i++)
						if (inp_defaults[i].form === fieldsTable.parentNode)
						{
//							inp_defaults[i].parentNode.innerHTML = inp_defaults[i].parentNode.innerHTML.replace("columnShow(this.checked, 5)", "columnShow(this.checked, 6)");
							inp_defaults[i].setAttribute("onclick", inp_defaults[i].getAttribute("onclick").replace("columnShow(this.checked, 5)", "columnShow(this.checked, 6)"));
							break;
						}
					var inp_comments = document.getElementsByName("comments");
					for (i=0; i<inp_comments.length; i++)
						if (inp_comments[i].form === fieldsTable.parentNode)
						{
//							inp_comments[i].parentNode.innerHTML = inp_comments[i].parentNode.innerHTML.replace("columnShow(this.checked, 6)", "columnShow(this.checked, 7)");
							inp_comments[i].setAttribute("onclick", inp_comments[i].getAttribute("onclick").replace("columnShow(this.checked, 6)", "columnShow(this.checked, 7)"));
							break;
						}
				}
			});
		});
		</script>
<?php
	}
}