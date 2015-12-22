<?php

/** <code> text wrap + possibility to show full query + possibility to always show executed query
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerExecutedQueryOutputModifier
{
	private $TYPES_LIST = ["text_wrap", "link2show_full", "show_message_queries"];

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
			if (in_array("show_message_queries", $this->TYPES_LIST))
			{
?>
				// show message executed queries
				var childs, j;
				var messages = document.getElementsByClassName("message");
				var i, cnt = messages.length;
				for (i=0; i<cnt; i++)
				{
					childs = messages[i].childNodes;
					for (j=0; j<childs.length; j++)
						if (childs[j].tagName && (childs[j].className.split(/\s+/).indexOf("hidden") != -1))
							childs[j].className = childs[j].className.replace(/\bhidden\b/, "");
				}
<?
			}

			if (in_array("text_wrap", $this->TYPES_LIST))
			{
?>
				// text wrap in <code> blocks
				var style = document.createElement('style');
				style.type = 'text/css';
				style.innerHTML = 'pre code { white-space: pre-wrap; }';
				document.getElementsByTagName('head')[0].appendChild(style);
<?
			}

			if (in_array("link2show_full", $this->TYPES_LIST))
			{
?>
				// show full queries link
				var funcShowFullQuery = function(evt)
				{
					var code = evt.srcElement.parentNode;
					var span = document.getElementById(code.parentNode.id.replace("sql-", "export-"));
					if (!span)
						return;

					var inputs_list = span.getElementsByTagName("INPUT");
					var i, cnt = inputs_list.length;
					for (i=0; i<cnt; i++)
						if (inputs_list[i].name == "query")
						{
							code.innerHTML = inputs_list[i].value;
							return;
						}
				};
				var pre_list = document.getElementsByTagName("PRE");
				var i, cnt = pre_list.length;
				for (i=0; i<cnt; i++)
				{
					var code_list = pre_list[i].getElementsByTagName("CODE");
					if (!code_list.length)
						continue;

					var italic_list = code_list[0].getElementsByTagName("I");
					if (!italic_list.length)
						continue;

					var link = document.createElement("A");
					link.addEventListener("click", funcShowFullQuery);
					link.style.cursor = "pointer";
					link.innerHTML = italic_list[0].innerHTML;
					code_list[0].insertBefore(link, italic_list[0]);
					code_list[0].removeChild(italic_list[0]);
				}
<?
			}
?>
		});
		</script>
<?
	}
}