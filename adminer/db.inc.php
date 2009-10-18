<?php
$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error) {
	$result = true;
	$message = "";
	if (count($_POST["tables"]) > 1 && ($_POST["drop"] || $_POST["truncate"])) {
		queries("SET foreign_key_checks = 0"); // allows to truncate or drop several tables at once
	}
	if (isset($_POST["truncate"])) {
		foreach ((array) $_POST["tables"] as $table) {
			if (!queries("TRUNCATE " . idf_escape($table))) {
				$result = false;
				break;
			}
		}
		$message = lang('Tables have been truncated.');
	} elseif (isset($_POST["move"])) {
		$rename = array();
		foreach ($tables_views as $table) {
			$rename[] = idf_escape($table) . " TO " . idf_escape($_POST["target"]) . "." . idf_escape($table);
		}
		$result = queries("RENAME TABLE " . implode(", ", $rename));
		//! move triggers
		$message = lang('Tables have been moved.');
	} elseif ((!isset($_POST["drop"]) || !$_POST["views"] || queries("DROP VIEW " . implode(", ", array_map('idf_escape', $_POST["views"]))))
	&& (!$_POST["tables"] || ($result = queries((isset($_POST["optimize"]) ? "OPTIMIZE" : (isset($_POST["check"]) ? "CHECK" : (isset($_POST["repair"]) ? "REPAIR" : (isset($_POST["drop"]) ? "DROP" : "ANALYZE")))) . " TABLE " . implode(", ", array_map('idf_escape', $_POST["tables"])))))
	) {
		if (isset($_POST["drop"])) {
			$message = lang('Tables have been dropped.');
		} else {
			while ($row = $result->fetch_assoc()) {
				$message .= h("$row[Table]: $row[Msg_text]") . "<br>";
			}
		}
	}
	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(lang('Database') . ": " . h(DB), $error, false);
echo '<p><a href="' . h(ME) . 'database=">' . lang('Alter database') . "</a>\n";
echo '<a href="' . h(ME) . 'schema=">' . lang('Database schema') . "</a>\n";

echo "<h3>" . lang('Tables and views') . "</h3>\n";
$table_status = table_status();
if (!$table_status) {
	echo "<p class='message'>" . lang('No tables.') . "\n";
} else {
	echo "<form action='' method='post'>\n";
	echo "<table cellspacing='0' class='nowrap' onclick='table_click(event);'>\n";
	echo '<thead><tr class="wrap"><td><input id="check-all" type="checkbox" onclick="form_check(this, /^(tables|views)\[/);"><th>' . lang('Table') . '<td>' . lang('Engine') . '<td>' . lang('Collation') . '<td>' . lang('Data Length') . '<td>' . lang('Index Length') . '<td>' . lang('Data Free') . '<td>' . lang('Auto Increment') . '<td>' . lang('Rows') . '<td>' . lang('Comment') . "</thead>\n";
	foreach ($table_status as $row) {
		$name = $row["Name"];
		echo '<tr' . odd() . '><td>' . checkbox((isset($row["Rows"]) ? "tables[]" : "views[]"), $name, in_array($name, $tables_views, true), "", "form_uncheck('check-all');");
		echo '<th><a href="' . h(ME) . 'table=' . urlencode($name) . '">' . h($name) . '</a>';
		if (isset($row["Rows"])) {
			echo "<td>$row[Engine]<td>$row[Collation]";
			foreach (array("Data_length" => "create", "Index_length" => "indexes", "Data_free" => "edit", "Auto_increment" => "create", "Rows" => "select") as $key => $link) {
				$val = number_format($row[$key], 0, '.', lang(','));
				echo '<td align="right">' . (strlen($row[$key]) ? '<a href="' . h(ME . "$link=") . urlencode($name) . '">' . str_replace(" ", "&nbsp;", ($key == "Rows" && $row["Engine"] == "InnoDB" && $val ? lang('~ %s', $val) : $val)) . '</a>' : '&nbsp;');
			}
			echo "<td>" . nbsp($row["Comment"]);
		} else {
			echo '<td colspan="6"><a href="' . h(ME) . "view=" . urlencode($name) . '">' . lang('View') . '</a>';
			echo '<td align="right"><a href="' . h(ME) . "select=" . urlencode($name) . '">?</a>';
			echo '<td>&nbsp;';
		}
	}
	echo "</table>\n";
	echo "<p><input type='hidden' name='token' value='$token'><input type='submit' value='" . lang('Analyze') . "'> <input type='submit' name='optimize' value='" . lang('Optimize') . "'> <input type='submit' name='check' value='" . lang('Check') . "'> <input type='submit' name='repair' value='" . lang('Repair') . "'> <input type='submit' name='truncate' value='" . lang('Truncate') . "' onclick=\"return confirm('" . lang('Are you sure?') . " (' + form_checked(this, /tables/) + ')');\"> <input type='submit' name='drop' value='" . lang('Drop') . "' onclick=\"return confirm('" . lang('Are you sure?') . " (' + form_checked(this, /tables|views/) + ')');\">\n";
	$dbs = get_databases();
	if (count($dbs) != 1) {
		$db = (isset($_POST["target"]) ? $_POST["target"] : DB);
		echo "<p>" . lang('Move to other database') . ($dbs ? ": " . html_select("target", $dbs, $db) : ': <input name="target" value="' . h($db) . '">') . " <input type='submit' name='move' value='" . lang('Move') . "'>\n";
	}
	echo "</form>\n";
}

if ($connection->server_info >= 5) {
	echo '<p><a href="' . h(ME) . 'view=">' . lang('Create view') . "</a>\n";
	echo "<h3>" . lang('Routines') . "</h3>\n";
	$result = $connection->query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $connection->quote(DB));
	if ($result->num_rows) {
		echo "<table cellspacing='0'>\n";
		while ($row = $result->fetch_assoc()) {
			echo "<tr>";
			echo "<td>" . h($row["ROUTINE_TYPE"]);
			echo '<th><a href="' . h(ME) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'callf=' : 'call=') . urlencode($row["ROUTINE_NAME"]) . '">' . h($row["ROUTINE_NAME"]) . '</a>';
			echo '<td><a href="' . h(ME) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'function=' : 'procedure=') . urlencode($row["ROUTINE_NAME"]) . '">' . lang('Alter') . "</a>";
		}
		echo "</table>\n";
	}
	echo '<p><a href="' . h(ME) . 'procedure=">' . lang('Create procedure') . '</a> <a href="' . h(ME) . 'function=">' . lang('Create function') . "</a>\n";
}

if ($connection->server_info >= 5.1 && ($result = $connection->query("SHOW EVENTS"))) {
	echo "<h3>" . lang('Events') . "</h3>\n";
	if ($result->num_rows) {
		echo "<table cellspacing='0'>\n";
		echo "<thead><tr><th>" . lang('Name') . "<td>" . lang('Schedule') . "<td>" . lang('Start') . "<td>" . lang('End') . "</thead>\n";
		while ($row = $result->fetch_assoc()) {
			echo "<tr>";
			echo '<th><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . h($row["Name"]) . "</a>";
			echo "<td>" . ($row["Execute at"] ? lang('At given time') . "<td>" . $row["Execute at"] : lang('Every') . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
			echo "<td>$row[Ends]";
		}
		echo "</table>\n";
	}
	echo '<p><a href="' . h(ME) . 'event=">' . lang('Create event') . "</a>\n";
}
