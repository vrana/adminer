<?php
page_header(lang('View') . ": " . htmlspecialchars($_GET["view"]));
echo "<h2>" . lang('View') . ": " . htmlspecialchars($_GET["view"]) . "</h2>\n";
echo htmlspecialchars(mysql_result(mysql_query("SHOW CREATE VIEW " . idf_escape($_GET["view"])), 0, 1));
