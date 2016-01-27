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
		if (get_page_table() !== "")
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
					if (!label && !query)
					{
						var new_form = document.createElement("P");
						new_form.style.margin = "0";
						new_form.style.padding = "0";
						new_form.style.clear = "left";
						parentBox.insertBefore(new_form, insertBeforeEl);
						return;
					}

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
				else if (content_box.getElementsByTagName("TABLE").length > 0)	// Database page (with all tables)
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
					var fieldset = document.createElement("FIELDSET");
					fieldset.style.padding = "5px";
					fieldset.appendChild( document.createElement("LEGEND") ).innerHTML = "Database Diagnostic";
					parentBox.insertBefore(fieldset, insertBeforeEl);
					parentBox = fieldset;
					insertBeforeEl = null;

<?
					switch ($GLOBALS["drivers"][DRIVER])
					{
						case "MySQL":
?>
							// http://code.openark.org/blog/mysql/useful-database-analysis-queries-with-information_schema
							funcAddShortcutToQuery("Detect dublicate indexes", "## Detect duplicate and redundant indexes.\n\n\
																				SELECT * FROM (\n\
																				  SELECT TABLE_NAME, INDEX_NAME,\n\
																				    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns, NON_UNIQUE\n\
																				  FROM `information_schema`.`STATISTICS`\n\
																				  WHERE INDEX_TYPE='BTREE'\n\
																				    AND TABLE_SCHEMA = '"+db_name+"'\n\
																				  GROUP BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME\n\
																				) AS i1 INNER JOIN (\n\
																				  SELECT TABLE_NAME, INDEX_NAME,\n\
																				    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns, NON_UNIQUE\n\
																				  FROM `information_schema`.`STATISTICS`\n\
																				  WHERE INDEX_TYPE='BTREE'\n\
																				    AND TABLE_SCHEMA = '"+db_name+"'\n\
																				  GROUP BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME\n\
																				) AS i2\n\
																				USING (TABLE_NAME)\n\
																				WHERE (i1.columns != i2.columns AND LOCATE(CONCAT(i1.columns, ','), i2.columns) = 1)\n\
																				   OR (i1.columns = i2.columns AND i1.NON_UNIQUE = i2.NON_UNIQUE AND i1.INDEX_NAME < i2.INDEX_NAME)");

							funcAddShortcutToQuery("Detect lack of primary keys", "## Detect lack of primary keys.\n\n\
																					SELECT ENGINE\n\
																					FROM information_schema.TABLES AS t\n\
																					INNER JOIN information_schema.COLUMNS AS c\n\
																					  ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME\n\
																					     AND t.TABLE_SCHEMA = '"+db_name+"'\n\
																					GROUP BY t.TABLE_NAME\n\
																					HAVING sum(if(column_key in ('PRI','UNI'), 1,0))=0;");

							funcAddShortcutToQuery("Detect suspicious charsets", "## See those columns for which the character set or collation is different from the table's character set and collation.\n\n\
																				SELECT columns.TABLE_NAME, COLUMN_NAME,\n\
																				  CHARACTER_SET_NAME AS column_CHARSET,\n\
																				  COLLATION_NAME AS column_COLLATION,\n\
																				  table_CHARSET, TABLE_COLLATION\n\
																				FROM (\n\
																				  SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME\n\
																				  FROM information_schema.COLUMNS\n\
																				  WHERE TABLE_SCHEMA = '"+db_name+"'\n\
																				    AND CHARACTER_SET_NAME IS NOT NULL\n\
																				) AS columns INNER JOIN (\n\
																				  SELECT TABLE_NAME, CHARACTER_SET_NAME AS table_CHARSET, TABLE_COLLATION\n\
																				  FROM information_schema.TABLES\n\
																				  INNER JOIN INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY\n\
																				    ON (TABLES.TABLE_COLLATION = COLLATION_CHARACTER_SET_APPLICABILITY.COLLATION_NAME)\n\
																				  WHERE TABLE_SCHEMA = '"+db_name+"'\n\
																				) AS tables\n\
																				ON (columns.TABLE_NAME = tables.TABLE_NAME)\n\
																				WHERE (columns.CHARACTER_SET_NAME != table_CHARSET OR columns.COLLATION_NAME != TABLE_COLLATION)\n\
																				ORDER BY TABLE_NAME, COLUMN_NAME");
							funcAddShortcutToQuery();	// delimiter

							funcAddShortcutToQuery("Show foreign keys", "## Show foreign keys.\n\n\
																			SELECT referenced_table_name AS parent, table_name child, constraint_name\n\
																			FROM information_schema.KEY_COLUMN_USAGE\n\
																			WHERE referenced_table_name IS NOT NULL AND TABLE_SCHEMA = '"+db_name+"'\n\
																			ORDER BY referenced_table_name;");

							// http://mysqlstepbystep.com/2015/07/15/useful-queries-on-mysql-information_schema/
							funcAddShortcutToQuery("Show tables fragmentation", "## 'OPTIMIZE TABLE' is expensive operation. Use it only when it realy required! For example large fragmentation_ration or/and large data_free.\n\n\
																					SELECT ENGINE, TABLE_NAME, ROW_FORMAT,\n\
																					       round( DATA_LENGTH/1024/1024 ) AS `data_length_MB`,\n\
																					       round( INDEX_LENGTH/1024/1024 ) AS `index_length_MB`,\n\
																					       concat( round( DATA_FREE/1024/1024 ), if( DATA_FREE/1024/1024 >= 100, '*', '' ) ) AS `data_free_MB`,\n\
																					       data_free/(data_length+index_length) AS `fragmentation_ratio_1/100%`\n\
																					FROM information_schema.tables\n\
																					WHERE TABLE_SCHEMA = '"+db_name+"' AND DATA_FREE > 0;");

							funcAddShortcutToQuery("Show MyISAM tables", "## MyISAM is a non transactional SE and having a consistent backup where there are MyISAM tables requires locking all tables.\n\n\
																			SELECT TABLE_NAME, ENGINE, ROW_FORMAT, TABLE_ROWS, DATA_LENGTH/1024/1024 AS `data_length_MB`, INDEX_LENGTH/1024/1024 AS `index_length_MB`\n\
																			FROM information_schema.tables\n\
																			WHERE TABLE_SCHEMA = '"+db_name+"' AND ENGINE='MyISAM';");
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