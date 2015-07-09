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
	} elseif (!$_POST["tables"]) {
		$message = lang('No tables.');
	} elseif ($result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE"))) . " TABLE " . implode(", ", array_map('idf_escape', $_POST["tables"])))) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}

	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(($_GET["ns"] == "" ? lang('Database') . ": " . h(DB) : lang('Schema') . ": " . h($_GET["ns"])), $error, true);

if ($adminer->homepage()) {
	if ($_GET["ns"] !== "") {
		echo "<h3 id='tables-views'>" . lang('Tables and views') . "</h3>\n";
		$tables_list = tables_list();
		if (!$tables_list) {
			echo "<p class='message'>" . lang('No tables.') . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			if (support("table")) {
				echo "<fieldset><legend>" . lang('Search data in tables') . " <span id='selected2'></span></legend><div>";
				echo "<input type='search' name='query' value='" . h($_POST["query"]) . "'> <input type='submit' name='search' value='" . lang('Search') . "'>\n";
				echo "</div></fieldset>\n";
				if ($_POST["search"] && $_POST["query"] != "") {
					search_tables();
				}
			}
			echo "<table cellspacing='0' class='nowrap checkable' onclick='tableClick(event);' ondblclick='tableClick(event, true);'>\n";

			echo '<thead><tr class="wrap"><td><input id="check-all" type="checkbox" onclick="formCheck(this, /^(tables|views)\[/);">';
			$doc_link = doc_link(array('sql' => 'show-table-status.html'));
			echo '<th>' . lang('Table');
			echo '<td>' . lang('Engine') . doc_link(array('sql' => 'storage-engines.html'));
			echo '<td>' . lang('Collation') . doc_link(array('sql' => 'charset-mysql.html'));
			echo '<td>' . lang('Data Length') . $doc_link;
			echo '<td>' . lang('Index Length') . $doc_link;
			echo '<td>' . lang('Data Free') . $doc_link;
			echo '<td>' . lang('Auto Increment') . doc_link(array('sql' => 'example-auto-increment.html'));
			echo '<td>' . lang('Rows') . $doc_link;
			echo (support("comment") ? '<td>' . lang('Comment') . $doc_link : '');
			echo "</thead>\n";

			$tables = 0;
			foreach ($tables_list as $name => $type) {
				$view = ($type !== null && !preg_match('~table~i', $type));
				echo '<tr' . odd() . '><td>' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array($name, $tables_views, true), "", "formUncheck('check-all');");
				echo '<th>' . (support("table") || support("indexes") ? '<a href="' . h(ME) . 'table=' . urlencode($name) . '" title="' . lang('Show structure') . '">' . h($name) . '</a>' : h($name));
				if ($view) {
					echo '<td colspan="6"><a href="' . h(ME) . "view=" . urlencode($name) . '" title="' . lang('Alter view') . '">' . (preg_match('~materialized~i', $type) ? lang('Materialized View') : lang('View')) . '</a>';
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
						$id = " id='$key-" . h($name) . "'";
						echo ($link ? "<td align='right'>" . (support("table") || $key == "Rows" || (support("indexes") && $key != "Data_length")
							? "<a href='" . h(ME . "$link[0]=") . urlencode($name) . "'$id title='$link[1]'>?</a>"
							: "<span$id>?</span>"
						) : "<td id='$key-" . h($name) . "'>&nbsp;");
					}
					$tables++;
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
			if (!information_schema(DB)) {
				$vacuum = "<input type='submit' value='" . lang('Vacuum') . "'" . on_help("'VACUUM'") . "> ";
				$optimize = "<input type='submit' name='optimize' value='" . lang('Optimize') . "'" . on_help($jush == "sql" ? "'OPTIMIZE TABLE'" : "'VACUUM OPTIMIZE'") . "> ";
				echo "<fieldset><legend>" . lang('Selected') . " <span id='selected'></span></legend><div>"
				. ($jush == "sqlite" ? $vacuum
				: ($jush == "pgsql" ? $vacuum . $optimize
				: ($jush == "sql" ? "<input type='submit' value='" . lang('Analyze') . "'" . on_help("'ANALYZE TABLE'") . "> " . $optimize
					. "<input type='submit' name='check' value='" . lang('Check') . "'" . on_help("'CHECK TABLE'") . "> "
					. "<input type='submit' name='repair' value='" . lang('Repair') . "'" . on_help("'REPAIR TABLE'") . "> "
				: "")))
				. "<input type='submit' name='truncate' value='" . lang('Truncate') . "'" . confirm() . on_help($jush == "sqlite" ? "'DELETE'" : "'TRUNCATE" . ($jush == "pgsql" ? "'" : " TABLE'")) . "> "
				. "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . on_help("'DROP TABLE'") . ">\n";
				$databases = (support("scheme") ? $adminer->schemas() : $adminer->databases());
				if (count($databases) != 1 && $jush != "sqlite") {
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo "<p>" . lang('Move to other database') . ": ";
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '" autocapitalize="off">');
					echo " <input type='submit' name='move' value='" . lang('Move') . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . lang('Copy') . "'>" : "");
					echo "\n";
				}
				echo "<input type='hidden' name='all' value='' onclick=\"selectCount('selected', formChecked(this, /^(tables|views)\[/));" . (support("table") ? " selectCount('selected2', formChecked(this, /^tables\[/) || $tables);" : "") . "\">\n"; // used by trCheck()
				echo "<input type='hidden' name='token' value='$token'>\n";
				echo "</div></fieldset>\n";
			}
			echo "</form>\n";
			echo "<script type='text/javascript'>tableCheck();</script>\n";
		}

		echo '<p class="links"><a href="' . h(ME) . 'create=">' . lang('Create table') . "</a>\n";
		echo (support("view") ? '<a href="' . h(ME) . 'view=">' . lang('Create view') . "</a>\n" : "");
		echo (support("materializedview") ? '<a href="' . h(ME) . 'view=&amp;materialized=1">' . lang('Create materialized view') . "</a>\n" : "");

		if (support("routine")) {
			echo "<h3 id='routines'>" . lang('Routines') . "</h3>\n";
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
			echo '<p class="links">'
				. (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . lang('Create procedure') . '</a>' : '')
				. '<a href="' . h(ME) . 'function=">' . lang('Create function') . "</a>\n"
			;
		}

		if (support("sequence")) {
			echo "<h3 id='sequences'>" . lang('Sequences') . "</h3>\n";
			$sequences = get_vals("SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema = current_schema() ORDER BY sequence_name");
			if ($sequences) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Name') . "</thead>\n";
				odd('');
				foreach ($sequences as $val) {
					echo "<tr" . odd() . "><th><a href='" . h(ME) . "sequence=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links'><a href='" . h(ME) . "sequence='>" . lang('Create sequence') . "</a>\n";
		}

		if (support("type")) {
			echo "<h3 id='user-types'>" . lang('User types') . "</h3>\n";
			$user_types = types();
			if ($user_types) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Name') . "</thead>\n";
				odd('');
				foreach ($user_types as $val) {
					echo "<tr" . odd() . "><th><a href='" . h(ME) . "type=" . urlencode($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links'><a href='" . h(ME) . "type='>" . lang('Create type') . "</a>\n";
		}

		if (support("event")) {
			echo "<h3 id='events'>" . lang('Events') . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang('Name') . "<td>" . lang('Schedule') . "<td>" . lang('Start') . "<td>" . lang('End') . "<td></thead>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo "<th>" . h($row["Name"]);
					echo "<td>" . ($row["Execute at"] ? lang('At given time') . "<td>" . $row["Execute at"] : lang('Every') . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
					echo "<td>$row[Ends]";
					echo '<td><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . lang('Alter') . '</a>';
				}
				echo "</table>\n";
				$event_scheduler = $connection->result("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p class="links"><a href="' . h(ME) . 'event=">' . lang('Create event') . "</a>\n";
		}

		if ($tables_list) {
			echo "<script type='text/javascript'>ajaxSetHtml('" . js_escape(ME) . "script=db');</script>\n";
		}
	}
}
