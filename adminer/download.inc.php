<?php
namespace Adminer;

$TABLE = $_GET["download"];
$fields = fields($TABLE);
header("Content-Type: application/octet-stream");
$values = array_merge((array) $_GET["where"], (array) $_GET["val"]); // val[] holds the values of the conditions with a function
header("Content-Disposition: attachment; filename=" . friendly_url("$TABLE-" . implode("_", $values)) . "." . friendly_url($_GET["field"]));
$select = array(idf_escape($_GET["field"]));
$result = driver()->select($TABLE, $select, array(where($_GET, $fields)), $select);
$row = ($result ? $result->fetch_row() : array());
echo driver()->value($row[0], $fields[$_GET["field"]]);
exit; // don't output footer
