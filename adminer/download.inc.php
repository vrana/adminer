<?php
$TABLE = $_GET["download"];
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . friendly_url("$TABLE-" . implode("_", $_GET["where"])) . "." . friendly_url($_GET["field"]));
echo $connection->result("SELECT" . limit(idf_escape($_GET["field"]) . " FROM " . table($TABLE), " WHERE " . where($_GET), 1));
exit; // don't output footer
