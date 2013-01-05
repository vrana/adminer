<?php
$TABLE = $_GET["download"];
$fields = fields($TABLE);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . friendly_url("$TABLE-" . implode("_", $_GET["where"])) . "." . friendly_url($_GET["field"]));
echo $connection->result("SELECT" . limit(idf_escape($_GET["field"]) . " FROM " . table($TABLE), " WHERE " . where($_GET, $fields), 1));
exit; // don't output footer
