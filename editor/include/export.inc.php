<?php
function dump_table($table, $style, $is_view = false) {
	echo "\xef\xbb\xbf"; // UTF-8 byte order mark
	dump_csv(array_keys(fields($table)));
}

function dump_data($table, $style, $select = "") {
	global $dbh;
	$result = $dbh->query(($select ? $select : "SELECT * FROM " . idf_escape($table)));
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			dump_csv($row);
		}
		$result->free();
	}
}

function dump_headers($identifier, $multi_table = false) {
	$filename = (strlen($identifier) ? friendly_url($identifier) : "dump");
	$ext = "csv";
	header("Content-Type: text/csv; charset=utf-8");
	header("Content-Disposition: attachment; filename=$filename.$ext");
	return $ext;
}

$dump_output = "";
$dump_format = "CSV";
