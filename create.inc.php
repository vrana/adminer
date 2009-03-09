<?php
$partition_by = array('HASH', 'LINEAR HASH', 'KEY', 'LINEAR KEY', 'RANGE', 'LIST');

if (strlen($_GET["create"])) {
	$orig_fields = fields($_GET["create"]);
}

if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	if ($_POST["drop"]) {
		query_redirect("DROP TABLE " . idf_escape($_GET["create"]), substr($SELF, 0, -1), lang('Table has been dropped.'));
	} else {
		$auto_increment_index = " PRIMARY KEY";
		if (strlen($_GET["create"]) && strlen($_POST["fields"][$_POST["auto_increment_col"]]["orig"])) {
			foreach (indexes($_GET["create"]) as $index) {
				foreach ($index["columns"] as $column) {
					if ($column === $_POST["fields"][$_POST["auto_increment_col"]]["orig"]) {
						$auto_increment_index = "";
						break 2;
					}
				}
				if ($index["type"] == "PRIMARY") {
					$auto_increment_index = " UNIQUE";
				}
			}
		}
		$fields = array();
		ksort($_POST["fields"]);
		$after = "FIRST";
		foreach ($_POST["fields"] as $key => $field) {
			if (strlen($field["field"]) && isset($types[$field["type"]])) {
				$fields[] = (!strlen($_GET["create"]) ? "" : (strlen($field["orig"]) ? "CHANGE " . idf_escape($field["orig"]) . " " : "ADD "))
					. idf_escape($field["field"]) . process_type($field)
					. ($field["null"] ? " NULL" : " NOT NULL") // NULL for timestamp
					. (strlen($_GET["create"]) && strlen($field["orig"]) && isset($orig_fields[$field["orig"]]["default"]) && $field["type"] != "timestamp" ? " DEFAULT '" . $mysql->escape_string($orig_fields[$field["orig"]]["default"]) . "'" : "") //! timestamp
					. ($key == $_POST["auto_increment_col"] ? " AUTO_INCREMENT$auto_increment_index" : "")
					. " COMMENT '" . $mysql->escape_string($field["comment"]) . "'"
					. (strlen($_GET["create"]) ? " $after" : "")
				;
				$after = "AFTER " . idf_escape($field["field"]);
			} elseif (strlen($field["orig"])) {
				$fields[] = "DROP " . idf_escape($field["orig"]);
			}
		}
		$status = ($_POST["Engine"] ? " ENGINE='" . $mysql->escape_string($_POST["Engine"]) . "'" : "")
			. ($_POST["Collation"] ? " COLLATE '" . $mysql->escape_string($_POST["Collation"]) . "'" : "")
			. (strlen($_POST["Auto_increment"]) ? " AUTO_INCREMENT=" . intval($_POST["Auto_increment"]) : "")
			. " COMMENT='" . $mysql->escape_string($_POST["Comment"]) . "'"
		;
		if (in_array($_POST["partition_by"], $partition_by)) {
			$partitions = array();
			if ($_POST["partition_by"] == 'RANGE' || $_POST["partition_by"] == 'LIST') {
				foreach (array_filter($_POST["partition_names"]) as $key => $val) {
					$value = $_POST["partition_values"][$key];
					$partitions[] = "PARTITION $val VALUES " . ($_POST["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . (strlen($value) ? " ($value)" : " MAXVALUE"); //! SQL injection
				}
			}
			$status .= " PARTITION BY $_POST[partition_by]($_POST[partition])" . ($partitions ? " (" . implode(", ", $partitions) . ")" : ($_POST["partitions"] ? " PARTITIONS " . intval($_POST["partitions"]) : ""));
		} elseif ($mysql->server_info >= 5.1 && strlen($_GET["create"])) {
			$status .= " REMOVE PARTITIONING";
		}
		$location = $SELF . "table=" . urlencode($_POST["name"]);
		if (strlen($_GET["create"])) {
			query_redirect("ALTER TABLE " . idf_escape($_GET["create"]) . " " . implode(", ", $fields) . ", RENAME TO " . idf_escape($_POST["name"]) . ", $status", $location, lang('Table has been altered.'));
		} else {
			query_redirect("CREATE TABLE " . idf_escape($_POST["name"]) . " (" . implode(", ", $fields) . ")$status", $location, lang('Table has been created.'));
		}
	}
}
page_header((strlen($_GET["create"]) ? lang('Alter table') : lang('Create table')), $error, array("table" => $_GET["create"]), $_GET["create"]);

$engines = array();
$result = $mysql->query("SHOW ENGINES");
while ($row = $result->fetch_assoc()) {
	if ($row["Support"] == "YES" || $row["Support"] == "DEFAULT") {
		$engines[] = $row["Engine"];
	}
}
$result->free();

if ($_POST) {
	$row = $_POST;
	if ($row["auto_increment_col"]) {
		$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
	}
	process_fields($row["fields"]);
} elseif (strlen($_GET["create"])) {
	$row = table_status($_GET["create"]);
	table_comment($row);
	$row["name"] = $_GET["create"];
	$row["fields"] = array_values($orig_fields);
	if ($mysql->server_info >= 5.1) {
		$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = '" . $mysql->escape_string($_GET["db"]) . "' AND TABLE_NAME = '" . $mysql->escape_string($_GET["create"]) . "'";
		$result = $mysql->query("SELECT PARTITION_METHOD, PARTITION_ORDINAL_POSITION, PARTITION_EXPRESSION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
		list($row["partition_by"], $row["partitions"], $row["partition"]) = $result->fetch_row();
		$result->free();
		$row["partition_names"] = array();
		$row["partition_values"] = array();
		$result = $mysql->query("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
		while ($row1 = $result->fetch_assoc()) {
			$row["partition_names"][] = $row1["PARTITION_NAME"];
			$row["partition_values"][] = $row1["PARTITION_DESCRIPTION"];
		}
		$result->free();
	}
} else {
	$row = array("fields" => array(array("field" => "")), "partition_names" => array());
}
$collations = collations();

$suhosin = floor(extension_loaded("suhosin") ? (min(ini_get("suhosin.request.max_vars"), ini_get("suhosin.post.max_vars")) - 13) / 8 : 0);
if ($suhosin && count($row["fields"]) > $suhosin) {
	echo "<p class='error'>" . htmlspecialchars(lang('Maximum number of allowed fields exceeded. Please increase %s and %s.', 'suhosin.post.max_vars', 'suhosin.request.max_vars')) . "</p>\n";
}
?>

<form action="" method="post" id="form">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo htmlspecialchars($row["name"]); ?>" />
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)</option><?php echo optionlist($engines, $row["Engine"]); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $row["Collation"]); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>" />
</p>
<table border="0" cellspacing="0" cellpadding="2">
<?php $column_comments = edit_fields($row["fields"], $collations); ?>
</table>
<?php echo type_change(count($row["fields"]), $suhosin); ?>
<p>
<?php echo lang('Auto Increment'); ?>: <input name="Auto_increment" size="4" value="<?php echo intval($row["Auto_increment"]); ?>" />
<?php echo lang('Comment'); ?>: <input name="Comment" value="<?php echo htmlspecialchars($row["Comment"]); ?>" maxlength="60" />
<script type="text/javascript">// <![CDATA[
document.write('<label><input type="checkbox"<?php if ($column_comments) { ?> checked="checked"<?php } ?> onclick="column_comments_click(this.checked);" /><?php echo lang('Show column comments'); ?></label>');
function column_comments_click(checked) {
	var trs = document.getElementsByTagName('tr');
	for (var i=0; i < trs.length; i++) {
		trs[i].getElementsByTagName('td')[5].style.display = (checked ? '' : 'none');
	}
}
<?php if (!$column_comments) { ?>column_comments_click(false);<?php } ?>
// ]]></script>
</p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["create"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?> /><?php } ?>
</p>
<?php if ($mysql->server_info >= 5.1) { ?>
<p>
<?php echo lang('Partition by'); ?>: <select name="partition_by"><option></option><?php echo optionlist($partition_by, $row["partition_by"]); ?></select>
(<input name="partition" value="<?php echo htmlspecialchars($row["partition"]); ?>" />)
<?php echo lang('Partitions'); ?>: <input name="partitions" size="2" value="<?php echo htmlspecialchars($row["partitions"]); ?>" />
</p>
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr><th><?php echo lang('Partition name'); ?></th><th><?php echo lang('Values'); ?></th></tr></thead>
<?php
foreach ($row["partition_names"] as $key => $val) {
	echo '<tr><td><input name="partition_names[' . intval($key) . ']" value="' . htmlspecialchars($val) . '" /></td><td><input name="partition_values[' . intval($key) . ']" value="' . htmlspecialchars($row["partition_values"][$key]) . "\" /></td></tr>\n";
}
//! JS for next row
?>
<tr><td><input name="partition_names[<?php echo $key+1; ?>]" value="" /></td><td><input name="partition_values[<?php echo $key+1; ?>]" value="" /></td></tr>
</table>
<?php } ?>
</form>
