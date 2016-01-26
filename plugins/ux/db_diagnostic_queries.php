<?php

/** Add shortcuts to some queries
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDbDiagnosticQueries
{
	function head()
	{
		if (Adminer::database() === null)
			return;
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			// add shortcut for some queries
			var current_location = document.location.href;
			var db_name, matches = current_location.match("&db=([^&]+)")
			if (matches)
				db_name = matches[1];

			if (!db_name)
				return;


			var content_box = document.getElementById("content");
			if (content_box)
			{
				var parentBox, insertBeforeEl, formUrl;
				var funcAddShortcutToQuery = function(label, query)
				{
					var new_form = document.createElement("FORM");
					new_form.method = "POST";
					new_form.enctype = "multipart/form-data";
					new_form.style.paddingRight = "10px";
					new_form.style.paddingBottom = "10px";
					new_form.style.cssFloat = "left";
					new_form.action = formUrl;

					var query_el = document.createElement("INPUT");
					query_el.type = "hidden";
					query_el.name = "query";
					query_el.value = query.replace(/^\t+/gm, "");
					new_form.appendChild(query_el);

					var query_el = document.createElement("INPUT");
					query_el.type = "hidden";
					query_el.name = "token";
					query_el.value = document.getElementsByName("token")[0].value;
					new_form.appendChild(query_el);

					var new_btn = document.createElement("INPUT");
					new_btn.type = "submit";
					new_btn.value = label;
					new_form.appendChild(new_btn);

					parentBox.insertBefore(new_form, insertBeforeEl);
				};


				var sql_form = document.getElementById("form");
				var is_sql_page = (current_location.indexOf("&sql=") > 0) && form && form.getElementsByTagName("TEXTAREA").length;

				if (is_sql_page)
				{
					parentBox = sql_form.parentNode;
					insertBeforeEl = sql_form.nextSibling;

					var p = document.createElement("P");
					p.style.clear = "left";
					insertBeforeEl = parentBox.insertBefore(p, insertBeforeEl);

					formUrl = sql_form.action;
				}
				else
				{
					var childs_list = content_box.childNodes;
					var i, cnt = childs_list.length;
					for (i=0; i<cnt; i++)
						if (childs_list[i].tagName && childs_list[i].className.split(/\s+/).indexOf("links") >= 0)
						{
							var a_list = childs_list[i].getElementsByTagName("A");
							if (a_list.length && (a_list[0].href.indexOf("&create=") > 0))
							{
								parentBox = childs_list[i].parentNode;
								insertBeforeEl = childs_list[i];
								formUrl = a_list[0].href.replace("&create=", "&sql=");

								break;
							}
						}
				}


				if (parentBox)
				{
<?
					switch ($GLOBALS["drivers"][DRIVER])
					{
						case "MySQL":
?>
							// http://mysqlstepbystep.com/2015/07/15/useful-queries-on-mysql-information_schema/
							funcAddShortcutToQuery("Detect tables fragmentation", "SELECT ENGINE, TABLE_NAME, Round( DATA_LENGTH/1024/1024) AS data_length,\n\
																						round(INDEX_LENGTH/1024/1024) AS index_length, round(DATA_FREE/1024/1024) AS data_free,\n\
																						data_free/(data_length+index_length) AS 'fragmentation_ratio'\n\
																					FROM information_schema.tables\n\
																					WHERE TABLE_SCHEMA = '"+db_name+"' AND DATA_FREE > 0;");
							// http://code.openark.org/blog/mysql/useful-database-analysis-queries-with-information_schema
							funcAddShortcutToQuery("Detect dublicate indexes", "SELECT * FROM (\n\
																				  SELECT TABLE_SCHEMA, TABLE_NAME, INDEX_NAME,\n\
																				  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns\n\
																				  FROM `information_schema`.`STATISTICS`\n\
																				  WHERE TABLE_SCHEMA NOT IN ('mysql', 'INFORMATION_SCHEMA')\n\
																					AND TABLE_SCHEMA = '"+db_name+"'\n\
																					AND NON_UNIQUE = 1 AND INDEX_TYPE='BTREE'\n\
																				  GROUP BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME\n\
																				) AS i1 INNER JOIN (\n\
																				  SELECT TABLE_SCHEMA, TABLE_NAME, INDEX_NAME,\n\
																				  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns\n\
																				  FROM `information_schema`.`STATISTICS`\n\
																				  WHERE INDEX_TYPE='BTREE'\n\
																					AND TABLE_SCHEMA = '"+db_name+"'\n\
																				  GROUP BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME\n\
																				) AS i2\n\
																				USING (TABLE_SCHEMA, TABLE_NAME)\n\
																				WHERE i1.columns != i2.columns AND LOCATE(CONCAT(i1.columns, ','), i2.columns) = 1");
							funcAddShortcutToQuery("Detect lack of primary keys", "SELECT t.TABLE_SCHEMA, t.TABLE_NAME, ENGINE\n\
																					FROM information_schema.TABLES AS t\n\
																					INNER JOIN information_schema.COLUMNS AS c\n\
																						ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME\n\
																							AND t.TABLE_SCHEMA NOT IN ('performance_schema','information_schema','mysql')\n\
																							AND t.TABLE_SCHEMA = '"+db_name+"'\n\
																					GROUP BY t.TABLE_SCHEMA,t.TABLE_NAME\n\
																					HAVING sum(if(column_key in ('PRI','UNI'), 1,0))=0;");
							funcAddShortcutToQuery("Show foreign keys", "SELECT referenced_table_name AS parent, table_name child, constraint_name\n\
																			FROM information_schema.KEY_COLUMN_USAGE\n\
																			WHERE referenced_table_name IS NOT NULL AND TABLE_SCHEMA = '"+db_name+"'\n\
																			ORDER BY referenced_table_name;");
<?
						break;
					}
?>
				}
			}
		});
		</script>
<?
	}
}