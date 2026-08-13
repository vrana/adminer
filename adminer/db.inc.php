<?php
namespace Adminer;

if (!isset($_GET["select"]) && support("single_table")) { // there is nothing to choose from, take the user to the only table
	$tables = tables_list();
	if ($tables) {
		redirect(ME . (support("table") ? "table=" : "select=") . url_escape(key($tables)));
	}
}
$me = ME . (isset($_GET["select"]) ? "select=&" : ""); // an empty select= is this page in a driver with a single table, ME doesn't carry it

$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error && !$_POST["search"]) {
	$result = true;
	$message = "";
	if (JUSH == "sql" && $_POST["tables"] && count($_POST["tables"]) > 1 && ($_POST["drop"] || $_POST["truncate"] || $_POST["copy"])) {
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
	} elseif (JUSH == "sqlite" && $_POST["check"]) {
		foreach ((array) $_POST["tables"] as $table) {
			foreach (get_rows("PRAGMA integrity_check(" . q($table) . ")") as $row) {
				$message .= "<b>" . h($table) . "</b>: " . h($row["integrity_check"]) . "<br>";
			}
		}
	} elseif (JUSH != "sql") {
		$result = (JUSH == "sqlite"
			? queries("VACUUM")
			: apply_queries("VACUUM" . ($_POST["optimize"] ? " ANALYZE" : ""), (array) $_POST["tables"])
		);
		$message = lang('Tables have been optimized.');
	} elseif (!$_POST["tables"]) {
		$message = lang('No tables.');
	} elseif (
		$result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE")))
			. " TABLE " . implode(", ", array_map('Adminer\idf_escape', $_POST["tables"])))
	) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}

	queries_redirect(relative_uri(), $message, $result);
}

page_header(($_GET["ns"] == "" ? lang('Database') . ": " . h(DB) : lang('Schema') . ": " . h($_GET["ns"])), $error, true);

if (adminer()->homepage()) {
	if ($_GET["ns"] !== "") {
		$order = $_GET["order"];
		$full = ($order || support("fast_status")); // whether to print the full table_status() without a background request
		echo "<div>\n";
		echo "<h3 id='tables-views'>" . lang('Tables and views') . "</h3>\n";
		$tables_list = ($full ? table_status() : tables_list());
		if (!$tables_list) {
			echo "<p class='message'>" . lang('No tables.') . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			if (support("table")) {
				echo "<fieldset><legend>" . lang('Search data in tables') . " <span id='selected2'></span></legend><div>";
				echo html_select("op", adminer()->operators(), idx($_POST, "op", JUSH == "elastic" ? "should" : "LIKE %%"));
				echo " <input type='search' name='query' value='" . h($_POST["query"]) . "'" . on('keydown', 'submitKeydown', 'search') . ">";
				echo " <input type='submit' name='search' value='" . lang('Search') . "'>\n";
				echo "</div></fieldset>\n";
				if (!$error && $_POST["search"] && $_POST["query"] != "") {
					$_GET["where"][0]["op"] = $_POST["op"];
					search_tables();
				}
			}
			echo "<div class='scrollable'>\n";
			echo "<table class='nowrap checkable odds'" . on('click', 'tableClick') . on('dblclick', 'tableClick') . ">\n";
			echo '<thead><tr class="wrap">';
			echo '<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="' . lang('All') . '"' . on('click', 'formCheck', '^(tables|views)\[') . '>';
			// without $order, the tables are sorted by name, except in SQLite which puts the sqlite_ tables last
			echo '<th' . (!$order && JUSH != 'sqlite' ? " aria-sort='ascending'" : '') . '><a href="' . h(substr($me, 0, -1)) . '">' . lang('Table') . '</a>';
			$columns = array("Engine" => array(lang('Engine') . doc_link(array('sql' => 'storage-engines.html'))));
			if (collations()) {
				$columns["Collation"] = array(lang('Collation') . doc_link(array('sql' => 'charset-charsets.html', 'mariadb' => 'supported-character-sets-and-collations/')));
			}
			if (function_exists('Adminer\alter_table')) {
				$columns["Data_length"] = array(
					lang('Data Length') . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT', 'oracle' => 'REFRN20286')),
					"create",
					lang('Alter table'),
				);
			}
			if (support("indexes")) {
				$columns["Index_length"] = array(
					lang('Index Length') . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-admin.html#FUNCTIONS-ADMIN-DBOBJECT')),
					"indexes",
					lang('Alter indexes'),
				);
			}
			$columns["Data_free"] = array(lang('Data Free') . doc_link(array('sql' => 'show-table-status.html')), "edit", lang('New item'));
			if (function_exists('Adminer\alter_table')) {
				$columns["Auto_increment"] = array(
					lang('Auto Increment') . doc_link(array('sql' => 'example-auto-increment.html', 'mariadb' => 'auto_increment/')),
					"auto_increment=1&create",
					lang('Alter table'),
				);
			}
			$columns["Rows"] = array(
				lang('Rows') . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'catalog-pg-class.html#CATALOG-PG-CLASS', 'oracle' => 'REFRN20286')),
				"select",
				lang('Select data'),
			);
			if (support("comment")) {
				$columns["Comment"] = array(lang('Comment') . doc_link(array('sql' => 'show-table-status.html', 'pgsql' => 'functions-info.html#FUNCTIONS-INFO-COMMENT-TABLE')));
			}
			$asc_columns = array('Engine', 'Collation', 'Comment'); // the other columns are sorted descending
			foreach ($columns as $key => $column) {
				echo "<th" . ($order == $key ? " aria-sort='" . (in_array($key, $asc_columns) ? "ascending" : "descending") . "'" : "")
					. "><a href='" . h($me) . "order=$key'>$column[0]</a>"
				;
			}
			echo "<tbody>\n";

			if ($order) {
				uasort($tables_list, function ($a, $b) use ($order, $asc_columns) {
					$return = ($a[$order] < $b[$order] ? -1 : ($a[$order] > $b[$order] ? 1 : 0)); // <=> available since PHP 7.1
					return (in_array($order, $asc_columns) ? $return : -$return);
				});
			}

			$tables = 0;
			$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
			foreach ($tables_list as $name => $status) {
				$view = ($full ? is_view($status) : $status !== null && !preg_match('~table|sequence~i', $status));
				$status = ($full ? $status : array('Engine' => $status));
				$id = h("Table-" . $name);
				echo '<tr><td class="hover">' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array("$name", $tables_views, true), "", "", "", $id); // "$name" to check numeric table names
				echo '<th>' . (support("table") || support("indexes")
					? "<a href='" . h(ME) . "table=" . url_escape($name) . "' title='" . lang('Show structure') . "' id='$id'>" . h($name) . '</a>'
					: h($name)
				);
				if ($view && !preg_match('~materialized~i', $status['Engine'])) {
					$title = lang('View');
					echo '<td colspan="' . (count($columns) - (support("comment") ? 2 : 1)) . '">'
						. (support("view") ? "<a href='" . h(ME) . "view=" . url_escape($name) . "' title='" . lang('Alter view') . "'>$title</a>" : $title);
					echo "<td align='right'><a href='" . h(ME) . "select=" . url_escape($name) . "' title='" . lang('Select data') . "'>?</a>";
					if (support("comment")) {
						echo '<td>' . h($status['Comment']);
					}
				} else {
					if ($full) {
						foreach (array_keys($sums) as $key) {
							// ignore innodb_file_per_table because it is not active for tables created before it was enabled
							$sums[$key] += ($status["Engine"] != "InnoDB" || $key != "Data_free" ? idx($status, $key) : 0);
						}
					}
					foreach ($columns as $key => $column) {
						$id = " id='$key-" . h($name) . "'";
						echo ($column[1]
							? "<td align='right'><a href='" . h(ME . "$column[1]=") . url_escape($name) . "'$id title='$column[2]'>" . format_status($status, $key) . "</a>"
							: "<td$id>" . h(idx($status, $key, '?'))
						);
					}
					$tables++;
				}
				echo "\n";
			}

			echo "<tr><td class='hover'><th>" . lang('%d in total', count($tables_list));
			echo "<td>" . h(JUSH == "sql" ? get_val("SELECT @@default_storage_engine") : "");
			echo (collations() ? "<td>" . h(db_collation(DB, collations())) : '');
			if ($full && function_exists('Adminer\db_status')) {
				$sums = db_status();
			}
			foreach ($sums as $key => $sum) {
				echo ($columns[$key] ? "<td align='right' id='sum-$key'>" . ($full ? format_number($sum) : "") : "");
			}
			echo "\n";

			echo "</table>\n";
			echo ($full ? '' : script("ajaxSetHtml('" . js_escape(ME) . "script=db');"));
			echo "</div>\n";
			if (!information_schema(DB)) {
				$vacuum = "<input type='submit' value='" . lang('Vacuum') . "'" . on_help("VACUUM") . "> ";
				$optimize = "<input type='submit' name='optimize' value='" . lang('Optimize') . "'" . on_help(JUSH == "sql" ? "OPTIMIZE TABLE" : "VACUUM ANALYZE") . "> ";
				$print = (JUSH == "sqlite" ? $vacuum . "<input type='submit' name='check' value='" . lang('Check') . "'" . on_help("PRAGMA integrity_check") . "> "
				: (JUSH == "pgsql" ? $vacuum . $optimize
				: (JUSH == "sql" ? "<input type='submit' value='" . lang('Analyze') . "'" . on_help("ANALYZE TABLE") . "> "
					. $optimize
					. "<input type='submit' name='check' value='" . lang('Check') . "'" . on_help("CHECK TABLE") . "> "
					. "<input type='submit' name='repair' value='" . lang('Repair') . "'" . on_help("REPAIR TABLE") . "> "
				: "")))
				. (function_exists('Adminer\truncate_tables')
					? "<input type='submit' name='truncate' value='" . lang('Truncate') . "'" . confirm()
						. on_help(JUSH == "sqlite" ? "DELETE" : "TRUNCATE" . (JUSH == "pgsql" ? "" : " TABLE")) . "> "
					: "")
				. (function_exists('Adminer\drop_tables') ? "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . on_help("DROP TABLE") . ">" : "");
				echo ($print ? "<div class='footer'><div>\n<fieldset><legend>" . lang('Selected') . " <span id='selected'></span></legend><div>$print\n</div></fieldset>\n" : "");

				$databases = (support("scheme") ? adminer()->schemas() : adminer()->databases());
				if (count($databases) != 1 && function_exists('Adminer\move_tables')) {
					echo "<fieldset><legend>" . lang('Move to another database') . " <span id='selected3'></span></legend><div>";
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '" autocapitalize="off">');
					echo "</label> <input type='submit' name='move' value='" . lang('Move') . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . lang('Copy') . "'> " . checkbox("overwrite", 1, $_POST["overwrite"], lang('overwrite')) : "");
					echo "</div></fieldset>\n";
				}
				echo "<input type='hidden' name='all' value=''" . on('click', 'countTables', $tables) . ">\n"; // used by trCheck()
				echo input_token();
				echo "</div></div>\n";
			}
			echo "</form>\n";
			echo script("tableCheck();");
		}

		echo (function_exists('Adminer\alter_table') ? "<p class='links hover'><a href='" . h(ME) . "create='>" . lang('Create table') . "</a>\n" : '');
		echo (support("view") ? "<a href='" . h(ME) . "view='>" . lang('Create view') . "</a>\n" : "");
		echo "</div>\n";

		if (support("routine")) {
			echo "<div>\n";
			echo "<h3 id='routines'>" . lang('Routines') . "</h3>\n";
			$routines = routines();
			if ($routines) {
				echo "<table class='odds'>\n";
				echo '<thead><tr><th>' . lang('Name') . '<td>' . lang('Type') . '<td>' . lang('Return type') . "<td class='hover'><tbody>\n";
				foreach ($routines as $row) {
					$name = ($row["SPECIFIC_NAME"] == $row["ROUTINE_NAME"] ? "" : "&name=" . url_escape($row["ROUTINE_NAME"])); // not computed on the pages to be able to print the header first
					echo '<tr>';
					echo '<th><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'callf=' : 'call=') . url_escape($row["SPECIFIC_NAME"]) . $name) . '" title="' . lang('Call') . '">'
						. h($row["ROUTINE_NAME"]) . '</a>';
					echo '<td>' . h($row["ROUTINE_TYPE"]);
					echo '<td>' . h($row["DTD_IDENTIFIER"]);
					echo '<td class="hover"><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'function=' : 'procedure=') . url_escape($row["SPECIFIC_NAME"]) . $name) . '">'
						. lang('Alter') . "</a>";
				}
				echo "</table>\n";
			}
			echo '<p class="links hover">'
				. (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . lang('Create procedure') . '</a>' : '')
				. '<a href="' . h(ME) . 'function=">' . lang('Create function') . "</a>\n"
			;
			echo "</div>\n";
		}

		if (support("sequence")) {
			echo "<div>\n";
			echo "<h3 id='sequences'>" . lang('Sequences') . "</h3>\n";
			// not information_schema.sequences which omits the sequences of identity columns
			$sequences = get_vals("SELECT relname FROM pg_class WHERE relkind = 'S' AND relnamespace = " . driver()->nsOid . " ORDER BY relname");
			if ($sequences) {
				echo "<table class='odds'>\n";
				echo "<thead><tr><th>" . lang('Name') . "<tbody>\n";
				foreach ($sequences as $val) {
					echo "<tr><th><a href='" . h(ME) . "sequence=" . url_escape($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links hover'><a href='" . h(ME) . "sequence='>" . lang('Create sequence') . "</a>\n";
			echo "</div>\n";
		}

		if (support("type")) {
			echo "<div>\n";
			echo "<h3 id='user-types'>" . lang('User types') . "</h3>\n";
			$user_types = types();
			if ($user_types) {
				echo "<table class='odds'>\n";
				echo "<thead><tr><th>" . lang('Name') . "<tbody>\n";
				foreach ($user_types as $val) {
					echo "<tr><th><a href='" . h(ME) . "type=" . url_escape($val) . "'>" . h($val) . "</a>\n";
				}
				echo "</table>\n";
			}
			echo "<p class='links hover'><a href='" . h(ME) . "type='>" . lang('Create type') . "</a>\n";
			echo "</div>\n";
		}

		if (support("event")) {
			echo "<div>\n";
			echo "<h3 id='events'>" . lang('Events') . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table>\n";
				echo "<thead><tr><th>" . lang('Name') . "<td>" . lang('Schedule') . "<td>" . lang('Start') . "<td>" . lang('End') . "<td class='hover'><tbody>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo "<th>" . h($row["Name"]);
					echo "<td>" . ($row["Execute at"]
						? lang('At given time') . "<td>" . h($row["Execute at"])
						: lang('Every') . " " . h($row["Interval value"]) . " " . h($row["Interval field"]) . "<td>" . h($row["Starts"]));
					echo "<td>" . h($row["Ends"]);
					echo '<td class="hover"><a href="' . h(ME) . 'event=' . url_escape($row["Name"]) . '">' . lang('Alter') . '</a>';
				}
				echo "</table>\n";
				$event_scheduler = get_val("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p class="links hover"><a href="' . h(ME) . 'event=">' . lang('Create event') . "</a>\n";
			echo "</div>\n";
		}
	}
}
