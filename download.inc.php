<?php
header("Content-Type: application/octet-stream");
echo mysql_result(mysql_query("SELECT " . idf_escape($_GET["field"]) . " FROM " . idf_escape($_GET["download"]) . " WHERE " . implode(" AND ", where()) . " LIMIT 1"), 0);
