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
<?
			if (in_array("pages_list", $this->TYPES_LIST))
			{
?>
				// Duplicate pages list
				var table_box = document.getElementById("table");
				if (table_box && ((table_box.rows.length-1) >= <?=$this->MINIMUM_TABLE_ROWS?>))
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
<?
			}

			if (in_array("explain_query", $this->TYPES_LIST))
			{
?>
				// Duplicate SQL result controls
				var table_box, content = document.getElementById("content");
				if (content && (table_box = content.getElementsByTagName("TABLE")).length && ((table_box[0].rows.length-1) >= <?=$this->MINIMUM_TABLE_ROWS?>))
				{
					var result_control_box = table_box[0];
					while (result_control_box && (result_control_box.tagName != "FORM"))
						result_control_box = result_control_box.nextSibling;
					if (result_control_box && result_control_box.getElementsByClassName("time").length)
					{
						table_box[0].parentNode.insertBefore(result_control_box.cloneNode(true), table_box[0]);
					}
				}
<?
			}
?>
		});
		</script>
<?
	}
}