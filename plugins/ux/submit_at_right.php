<?php

/** Move some submit buttons to the right
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSubmitAtRight
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// some Submit buttons move to the right
			window.addEventListener("resize", function(event)
			{
				var forms_list = document.getElementsByTagName("FORM");
				var i, cnt = forms_list.length;
				for (i=0; i<cnt; i++)
				{
					var submits_list = [];
					var inputs_list = forms_list[i].getElementsByTagName("INPUT");
					var j, j_cnt = inputs_list.length;
					for (j=0; j<j_cnt; j++)
						if (inputs_list[j].type == "submit")
							submits_list.push( inputs_list[j] );
					if (submits_list.length)
					{
						// setup size for submit_box = size of input_box
						var submit_box, input_box;
						j_cnt = submits_list.length;
						for (j=0; j<j_cnt; j++)
						{
							submit_box = submits_list[j].parentNode;
							input_box = submit_box.previousSibling;
							while (input_box && !input_box.tagName)
								input_box = input_box.previousSibling;
							if (input_box)
							{
								if (input_box.tagName != "TABLE")
								{
									var ta_list = input_box.getElementsByTagName("TEXTAREA");
									if (ta_list.length)
										input_box = ta_list[0];
									else
									{
										input_box = null;
										continue;
									}
								}
								else
								{
									// if at bottom also has table => ignore
									var next_table = submit_box.nextSibling;
									while (next_table && !next_table.tagName)
										next_table = next_table.previousSibling;
									if (next_table && (next_table.tagName == "TABLE"))
									{
										input_box = null;
										continue;
									}
								}
								break;
							}
						}

						if (input_box)
						{
							var width = input_box.offsetWidth || input_box.clientWidth || 0;
							if (width)
							{
								submit_box.style.width = width + "px";

								var submit_box_buttons = [];
								var submit_box_inputs = submit_box.getElementsByTagName("INPUT");
								j_cnt = submit_box_inputs.length;
								for (j=0; j<j_cnt; j++)
									if (submit_box_inputs[j].type == "submit")
										submit_box_buttons.push( submit_box_inputs[j] );

//								submit_box.style.textAlign = "right";
								// better reverse buttons, because safest (Save) has to be at right
								for (j=0; j<submit_box_buttons.length; j++)
								{
									submit_box_buttons[j].style.cssFloat = "right";
									submit_box_buttons[j].style.marginLeft = "4px";		// emulate default white space between buttons
									if (!submit_box_buttons[j].parentNode.myHasSpacesFixer)
									{
										var spaces_fixer = submit_box_buttons[j].parentNode.appendChild( document.createElement("SPAN") );
										spaces_fixer.style.display = "block";
										spaces_fixer.style.clear = "right";
										submit_box_buttons[j].parentNode.myHasSpacesFixer = true;
									}
								}
							}
						}
					}
				}
			});

			var event = null;
			if (document.createEvent)
			{
				event = document.createEvent("Event");
				event.initEvent("resize", true, false);
			}
			else
				event = new Event('resize');
			window.dispatchEvent(event);
		});
		</script>
<?
	}
}