<?php
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . friendly_url("$_GET[download]-" . implode("_", $_GET["where"])) . "." . friendly_url($_GET["field"]));
echo $dbh->result($dbh->query("SELECT " . idf_escape($_GET["field"]) . " FROM " . idf_escape($_GET["download"]) . " WHERE " . implode(" AND ", where($_GET)) . " LIMIT 1"));
