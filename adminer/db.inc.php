<?php
$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error && !$_POST["search"]) {
	$result = true;
	$message = "";
	if ($jush == "sql" && count($_POST["tables"]) > 1 && ($_POST["drop"] || $_POST["truncate"] || $_POST["copy"])) {
		queries("SET foreign_key_checks = 0"); // allows to truncate or drop several tables at once
	}
	if ($_POST["truncate"]) {
		if ($_POST["tables"]) {
			$result = truncate_tables($_POST["tables"]);
		}
		$message = lang('Tables have been truncated.');
	} elseif ($_POST["move"]) {
		$result = move_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = lang('Tables have been moved.');
	} elseif ($_POST["copy"]) {
		$result = copy_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = lang('Tables have been copied.');
	} elseif ($_POST["drop"]) {
		if ($_POST["views"]) {
			$result = drop_views($_POST["views"]);
		}
		if ($result && $_POST["tables"]) {
			$result = drop_tables($_POST["tables"]);
		}
		$message = lang('Tables have been dropped.');
	} elseif ($jush != "sql") {
		$result = ($jush == "sqlite"
			? queries("VACUUM")
			: apply_queries("VACUUM" . ($_POST["optimize"] ? "" : " ANALYZE"), $_POST["tables"])
		);
		$message = lang('Tables have been optimized.');
	} elseif ($_POST["tables"] && ($result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE"))) . " TABLE " . implode(", ", array_map('idf_escape', $_POST["tables"]))))) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}
	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(($_GET["ns"] == "" ? lang('Database') . ": " . h(DB) : lang('Schema') . ": " . h($_GET["ns"])), $error, true);

if ($adminer->homepage()) {
	if ($_GET["ns"] !== "") {
		echo "<h3>" . lang('Tables and views') . "</h3>\n";
		$tables_list = tables_list();
		if (!$tables_list) {
			echo "<p class='message'>" . lang('No tables.') . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			echo "<p>" . lang('Search data in tables') . ": <input name='query' value='" . h($_POST["query"]) . "'> <input type='submit' name='search' value='" . lang('Search') . "'>\n";
			if ($_POST["search"] && $_POST["query"] != "") {
				search_tables();
			}
			echo "<table cellspacing='0' class='nowrap checkable' onclick='tableClick(event);'>\n";
			echo '<thead><tr class="wrap"><td><input id="check-all" type="checkbox" onclick="formCheck(this, /^(tables|views)\[/);">';
			echo '<th>' . lang('Table');
			echo '<td>' . lang('Engine');
			echo '<td>' . lang('Collation');
			echo '<td>' . lang('Data Length');
			echo '<td>' . lang('Index Length');
			echo '<td>' . lang('Data Free');
			echo '<td>' . lang('Auto Increment');
			echo '<td>' . lang('Rows');
			echo (support("comment") ? '<td>' . lang('Comment') : '');
			echo "</thead>\n";
			foreach ($tables_list as $name => $type) {
				$view = ($type !== null && !eregi("table", $type));
				echo '<tr' . odd() . '><td>' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array($name, $tables_views, true), "", "formUncheck('check-all');");
				echo '<th><a href="' . h(ME) . 'table=' . urlencode($name) . '" title="' . lang('Show structure') . '">' . h($name) . '</a>';
				if ($view) {
					echo '<td colspan="6"><a href="' . h(ME) . "view=" . urlencode($name) . '" title="' . lang('Alter view') . '">' . lang('View') . '</a>';
					echo '<td align="right"><a href="' . h(ME) . "select=" . urlencode($name) . '" title="' . lang('Select data') . '">?</a>';
				} else {
					foreach (array(
						"Engine" => array(),
						"Collation" => array(),
						"Data_length" => array("create", lang('Alter table')),
						"Index_length" => array("indexes", lang('Alter indexes')),
						"Data_free" => array("edit", lang('New item')),
						"Auto_increment" => array("auto_increment=1&create", lang('Alter table')),
						"Rows" => array("select", lang('Select data')),
					) as $key => $link) {
						echo ($link ? "<td align='right'><a href='" . h(ME . "$link[0]=") . urlencode($name) . "' id='$key-" . h($name) . "' title='$link[1]'>?</a>" : "<td id='$key-" . h($name) . "'>&nbsp;");
					}
				}
				echo (support("comment") ? "<td id='Comment-" . h($name) . "'>&nbsp;" : "");
			}
			echo "<tr><td>&nbsp;<th>" . lang('%d in total', count($tables_list));
			echo "<td>" . nbsp($jush == "sql" ? $connection->result("SELECT @@storage_engine") : "");
			echo "<td>" . nbsp(db_collation(DB, collations()));
			foreach (array("Data_length", "Index_length", "Data_free") as $key) {
				echo "<td align='right' id='sum-$key'>&nbsp;";
			}
			echo "</table>\n";
			echo "<script type='text/javascript'>tableCheck();</script>\n";
			if (!information_schema(DB)) {
				echo "<p>" . (ereg('^(sql|sqlite|pgsql)$', $jush)
					? ($jush != "sqlite" ? "<input type='submit' value='" . lang('Analyze') . "'> " : "")
					. "<input type='submit' name='optimize' value='" . lang('Optimize') . "'> " : ""
				) . ($jush == "sql" ? "<input type='submit' name='check' value='" . lang('Check') . "'> <input type='submit' name='repair' value='" . lang('Repair') . "'> " : "") . "<input type='submit' name='truncate' value='" . lang('Truncate') . "'" . confirm("formChecked(this, /tables/)") . "> <input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm("formChecked(this, /tables|views/)") . ">\n";
				$databases = (support("scheme") ? schemas() : $adminer->databases());
				if (count($databases) != 1 && $jush != "sqlite") {
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo "<p>" . lang('Move to other database') . ": ";
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '">');
					echo " <input type='submit' name='move' value='" . lang('Move') . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . lang('Copy') . "'>" : "");
					echo "\n";
				}
				echo "<input type='hidden' name='token' value='$token'>\n";
			}
			echo "</form>\n";
		}
		
		echo '<p><a href="' . h(ME) . 'create=">' . lang('Create table') . "</a>\n";
		if (support("view")) {
			echo '<a href="' . h(ME) . 'view=">' . lang('Create view') . "</a>\n";
		}
	
		if (support("routine")) {
			echo "<h3>" . lang('Routines') . "</h3>\n";
			$routines = routines();
			if ($routines) {
				echo "<table cellspacing='0'>\n";
				echo '<thead><tr><th>' . lang('Name') . '<td>' . lang('Type') . '<td>' . lang('Return type') . "<td>&nbsp;</thead>\n";
				odd('');
				foreach ($routines as $row) {
					echo '<tr' . odd() . '>';
					echo '<th><a href="' . h(ME) . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'callf=' : 'call=') . urlencode($row["ROUTINE_NAME"]) . '">' . h($row["ROUTINE_NAME"]) . '</a>';
					echo '<td>' . h($row["ROUTINE_TYPE"]);
					echo '<td>' . h($row["DTD_IDENTIFIER"]);
					echo '<td><a href="' . h(ME) . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'function=' : 'procedure=') . urlencode($row["ROUTINE_NAME"]) . '">' . lang('Alter') . "</a>";
				}
				echo "</table>\n";
			}
			echo '<p>' . (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . lang('Create procedure') . '</a> ' : '') . '<a href="' . h(ME) . 'function=">' . lang('Create function') . "</a>\n";
		}
		
		if (support("sequence")) {
			echo "<h3>" . lang('Sequences') . "</h3>\n";
			$sequences = get_vals("SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema = current_schema()");
			if ($sequences) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Name') . "</thead>\n";
				odd('');
				foreach ($sequences as $val) {
					echo "<tr" . odd() . "><th><a href='" . h(ME) . "sequence=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p><a href='" . h(ME) . "sequence='>" . lang('Create sequence') . "</a>\n";
		}
		
		if (support("type")) {
			echo "<h3>" . lang('User types') . "</h3>\n";
			$types = types();
			if ($types) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Name') . "</thead>\n";
				odd('');
				foreach ($types as $val) {
					echo "<tr" . odd() . "><th><a href='" . h(ME) . "type=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p><a href='" . h(ME) . "type='>" . lang('Create type') . "</a>\n";
		}
		
		if (support("event")) {
			echo "<h3>" . lang('Events') . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Name') . "<td>" . lang('Schedule') . "<td>" . lang('Start') . "<td>" . lang('End') . "</thead>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo '<th><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . h($row["Name"]) . "</a>";
					echo "<td>" . ($row["Execute at"] ? lang('At given time') . "<td>" . $row["Execute at"] : lang('Every') . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
					echo "<td>$row[Ends]";
				}
				echo "</table>\n";
				$event_scheduler = $connection->result("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p><a href="' . h(ME) . 'event=">' . lang('Create event') . "</a>\n";
		}
		
		if ($tables_list) {
			echo "<script type='text/javascript'>ajaxSetHtml('" . js_escape(ME) . "script=db');</script>\n";
		}
	}
}
