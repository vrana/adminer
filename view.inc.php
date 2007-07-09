<?php
page_header(lang('View') . ": " . htmlspecialchars($_GET["view"]));
echo "<pre>" . htmlspecialchars(mysql_result(mysql_query("SHOW CREATE VIEW " . idf_escape($_GET["view"])), 0, 1)) . "</pre>\n";
