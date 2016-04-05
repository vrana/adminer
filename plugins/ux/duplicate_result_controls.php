<?php

/** Duplicate result controls
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDuplicateResultControls
{
	private $TYPES_LIST = ["pages_list", "explain_query"];
	private $MINIMUM_TABLE_ROWS = 0;

	function __construct($types_list = [], $minimum_table_rows = 0)
	{
		if ($types_list)
		{
			if (!is_array($types_list))
				$types_list = array($types_list);
			$this->TYPES_LIST = $types_list;
		}
		$this->MINIMUM_TABLE_ROWS = $minimum_table_rows;
	}

	function head()
	{
		if (!$this->TYPES_LIST)
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
<?php
			if (in_array("pages_list", $this->TYPES_LIST))
			{
?>
				// Duplicate pages list
				var table_box = document.getElementById("table");
				if (table_box && ((table_box.rows.length-1) >= <?php print $this->MINIMUM_TABLE_ROWS;?>))
				{
					var pages_box = table_box;
					while (pages_box && (!pages_box.className || (pages_box.className.split(/\s+/).indexOf("pages") < 0)))
						pages_box = pages_box.nextSibling;
					if (pages_box)
					{
						pages_box.style.position = "static";

						var new_pages_box = pages_box.cloneNode(true)
						var a_list = new_pages_box.getElementsByTagName("A");
						if (a_list[a_list.length-1].className.split(/\s+/).indexOf("loadmore") != -1)
							new_pages_box.removeChild(a_list[a_list.length-1]);
						new_pages_box = table_box.parentNode.insertBefore( new_pages_box, table_box );

						// copy also rows number
						var rows_count_box = pages_box;
						while (rows_count_box && (!rows_count_box.className || (rows_count_box.className.split(/\s+/).indexOf("count") < 0)))
							rows_count_box = rows_count_box.nextSibling;
						if (rows_count_box)
						{
							var new_rows_count_box = document.createElement("SPAN");
							var i, rows_count_box_childs = rows_count_box.childNodes;
							for (i=0; i<rows_count_box_childs.length; i++)
								if (!rows_count_box_childs[i].tagName)
									new_rows_count_box.appendChild( rows_count_box_childs[i].cloneNode() );

							var new_rows_count_box = new_pages_box.appendChild( new_rows_count_box );		// at end of upper pages chooser
							new_rows_count_box.className = rows_count_box.className;
							new_rows_count_box.style.marginLeft = "1ex";
						}
					}
				}
<?php
			}

			if (in_array("explain_query", $this->TYPES_LIST))
			{
?>
				// Duplicate SQL result controls
				var table_box, content = document.getElementById("content");
				if (content && (table_box = content.getElementsByTagName("TABLE")).length && ((table_box[0].rows.length-1) >= <?php print $this->MINIMUM_TABLE_ROWS;?>))
				{
					var cloned_controls = [];

					var j, cnt = table_box.length;
					for (j=0; j<cnt; j++)
						if (table_box[j].parentNode === content)
						{
							var result_control_box = table_box[j];
							while (result_control_box && (result_control_box.tagName != "FORM"))
								result_control_box = result_control_box.nextSibling;
							if (result_control_box && result_control_box.getElementsByClassName("time").length)
							{
								var controls_clone = result_control_box.cloneNode(true);
								var i, els_list;

								// replace ID for duplicates
								els_list = controls_clone.getElementsByTagName("DIV");
								for (i=0; i<els_list.length; i++)
									if (els_list[i].id)
										els_list[i].id = els_list[i].id.replace(/^(explain)-(\d+)$/, "$1-$2-2");

								els_list = controls_clone.getElementsByTagName("SPAN");
								for (i=0; i<els_list.length; i++)
									if (els_list[i].id)
										els_list[i].id = els_list[i].id.replace(/^(export)-(\d+)$/, "$1-$2-2");

								els_list = controls_clone.getElementsByTagName("A");
								for (i=0; i<els_list.length; i++)
									if (els_list[i].href)
									{
										els_list[i].setAttribute("href", els_list[i].getAttribute("href").replace(/^#(explain|export)-(\d+)$/, "#$1-$2-2"));
										if (els_list[i].getAttribute("onclick"))
											els_list[i].setAttribute("onclick", els_list[i].getAttribute("onclick").replace(/'(explain|export)-(\d+)'/, "'$1-$2-2'"));
									}

								// collect new elements
								cloned_controls.push([ controls_clone, table_box[j] ]);
							}
						}

					// flush new elements
					for (j=0; j<cloned_controls.length; j++)
						content.insertBefore(cloned_controls[j][0], cloned_controls[j][1]);
				}
<?php
			}
?>
		});
		</script>
<?php
	}
}