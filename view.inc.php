<?php
page_header(lang('View') . ": " . htmlspecialchars($_GET["view"]));
echo "<pre>" . htmlspecialchars(preg_replace('~^.* AS ~U', '', mysql_result(mysql_query("SHOW CREATE VIEW " . idf_escape($_GET["view"])), 0, 1))) . "</pre>\n";
