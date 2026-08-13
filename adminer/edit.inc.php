<?php
namespace Adminer;

$TABLE = $_GET["edit"];
$fields = fields($TABLE);
// index.php routes here also from Select which prints the same form for the checked rows, so it serves four modes told apart by two independent conditions:
// $update - modify existing rows (Edit) instead of inserting a new one (Insert, Clone); isset($_GET["select"]) - the form can affect more than one row (Clone, Edit from Select)
$where = (isset($_GET["select"])
	? ($_POST["check"] && count($_POST["check"]) == 1 ? where_check($_POST["check"][0], $fields) : "")
	: where($_GET, $fields)
);
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
foreach ($fields as $name => $field) {
	if ((!$update && !isset($field["privileges"]["insert"])) || adminer()->fieldName($field) == "") {
		unset($fields[$name]);
	}
}

if ($_POST && !$error && !isset($_GET["select"])) {
	$location = relative_uri((string) $_POST["referer"]); // the field is sent by the browser so it can point anywhere
	if ($_POST["insert"]) { // continue edit or insert
		//! it should redirect to Select if the values in ?where changed (handled only in JS)
		$location = ($update ? null : relative_uri());
	} elseif (!preg_match('~^.+&select=.+$~', $location)) {
		$location = ME . "select=" . url_escape($TABLE);
	}

	$indexes = indexes($TABLE);
	$unique_array = unique_array($_GET["where"], $indexes);
	$query_where = "\nWHERE $where";

	if (isset($_POST["delete"])) {
		queries_redirect(
			$location,
			lang('Item has been deleted.'),
			driver()->delete($TABLE, $query_where, $unique_array ? 0 : 1)
		);

	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($field);
			if ($val !== false && $val !== null) {
				$set[idf_escape($name)] = $val;
			}
		}

		if ($update) {
			if (!$set) {
				redirect($location);
			}
			queries_redirect(
				$location,
				lang('Item has been updated.'),
				driver()->update($TABLE, $set, $query_where, $unique_array ? 0 : 1)
			);
			if (is_ajax()) {
				page_headers();
				page_messages($error);
				exit;
			}
		} else {
			$result = driver()->insert($TABLE, $set);
			$last_id = ($result ? last_id($result) : 0);
			queries_redirect($location, lang('Item%s has been inserted.', ($last_id ? " $last_id" : "")), $result); //! link
		}
	}
}

$row = null;
$query = "";
$time = "";
if ($where) {
	$select = array();
	$select_print = array("*"); // the columns are listed only because of the privileges, printing * is shorter
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"])) {
			$as = ($_POST["clone"] && $field["auto_increment"] ? "''" : convert_field($field));
			$column = ($as ? "$as AS " : "") . idf_escape($name);
			$select[] = $column;
			if ($as) {
				$select_print[] = $column; // * doesn't cover the conversions
			}
		}
	}
	$row = array();
	if (!support("table")) {
		$select = array("*");
		$select_print = $select;
	}
	if ($select) {
		$start = microtime(true);
		$result = driver()->select($TABLE, $select, array($where), $select, array(), (isset($_GET["select"]) ? 2 : 1));
		// only the column list is replaced, a single column is contained also in the condition
		$query = str_replace("SELECT " . implode(", ", $select), "SELECT " . implode(", ", $select_print), driver()->query);
		$time = format_time($start);
		if (!$result) {
			$error = adminer()->error();
		} else {
			$row = $result->fetch_assoc();
			if (!$row) { // MySQLi returns null
				$row = false;
			}
		}
		if (isset($_GET["select"]) && (!$row || $result->fetch_assoc())) { // $result->num_rows != 1 isn't available in all drivers
			$row = null;
		}
	}
}

if (!$fields && driver()->primary != "") {
	if (!$where) { // insert
		$result = driver()->select($TABLE, array("*"), array(), array("*"));
		$row = ($result ? $result->fetch_assoc() : false);
		if (!$row) {
			$row = array(driver()->primary => "");
		}
	}
	if ($row) {
		foreach ($row as $key => $val) {
			if (!$where) {
				$row[$key] = null;
			}
			$fields[$key] = array("field" => $key, "null" => ($key != driver()->primary), "auto_increment" => ($key == driver()->primary));
		}
	}
}

if ($_POST["save"]) {
	$post_fields = array();
	foreach ((array) $_POST["fields"] as $key => $val) {
		$post_fields[bracket_escape($key, true)] = $val;
	}
	$row = $post_fields + ($row ? $row : array());
}

edit_form($TABLE, $fields, $row, $update, $error, $query, $time);
