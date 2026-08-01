<?php
namespace Adminer;

if (isset($_GET["status"])) {
	$_GET["variables"] = $_GET["status"];
}
if (isset($_GET["import"])) {
	$_GET["sql"] = $_GET["import"];
}

if (DB == "" && isset($_GET["ns"])) {	// menu form for changing DB preserves ns which leads to this combination
	redirect(remove_from_uri('ns'));
}

if (
	!(DB != ""
		? connection()->select_db(DB)
		: isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"])
			|| $_GET["script"] == "connect" || $_GET["script"] == "kill"
	)
) {
	if (DB != "" || $_GET["refresh"]) {
		restart_session();
		set_session("dbs", null);
	}
	if (DB != "") {
		header("HTTP/1.1 404 Not Found");
		page_header(lang('Database') . ": " . h(DB), lang('Invalid database.'), true);
	} else {
		if ($_POST["db"] && !$error) {
			queries_redirect(substr(ME, 0, -1), lang('Databases have been dropped.'), drop_databases($_POST["db"]));
		}

		page_header(lang('Select database'), $error, false);
		echo "<p class='links'>\n";
		foreach (
			array(
				'database' => lang('Create database'),
				'privileges' => lang('Privileges'),
				'processlist' => lang('Process list'),
				'variables' => lang('Variables'),
				'status' => lang('Status'),
			) as $key => $val
		) {
			if (support($key)) {
				echo "<a href='" . h(ME) . "$key='>$val</a>\n";
			}
		}
		echo "<p>" . lang('%s version: %s through PHP extension %s', get_driver(DRIVER), "<b>" . h(connection()->server_info) . "</b>", "<b>" . connection()->extension . "</b>") . "\n";
		echo "<p>" . lang('Logged as: %s', "<b>" . h(logged_user()) . "</b>") . "\n";

		$databases = adminer()->databases();
		if ($databases) {
			$scheme = support("scheme");
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table class='checkable odds'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: event => tableClick(event, true)});");
			echo "<thead><tr>"
				. (support("database") ? "<td class='hover'>" : "")
				// the databases are sorted by name, except in MS SQL which doesn't order them at all
				. "<th" . (JUSH != 'mssql' ? " aria-sort='ascending'" : "") . ">" . lang('Database')
				. (get_session("dbs") !== null ? " - <a href='" . h(ME) . "refresh=1'>" . lang('Refresh') . "</a>" : "")
				. "<td>" . lang('Collation')
				. "<td>" . lang('Tables')
				. "<td>" . lang('Size') . " - <a href='" . h(ME) . "dbsize=1'" . on('click', 'ajaxSetHtml', ME . "script=connect") . ">" . lang('Compute') . "</a>"
				. "<tbody>\n"
			;

			$databases = ($_GET["dbsize"] ? count_tables($databases) : array_flip($databases));
			foreach ($databases as $db => $tables) {
				$root = h(ME) . "db=" . urlencode($db);
				$id = h("Db-" . $db);
				echo "<tr>" . (support("database") ? "<td class='hover'>" . checkbox("db[]", $db, in_array($db, (array) $_POST["db"]), "", "", "", $id) : "");
				echo "<th><a href='$root' id='$id'>" . h($db) . "</a>";
				$collation = h(db_collation($db, $collations));
				echo "<td>" . (support("database") ? "<a href='$root" . ($scheme ? "&amp;ns=" : "") . "&amp;database=' title='" . lang('Alter database') . "'>$collation</a>" : $collation);
				echo "<td align='right'><a href='$root&amp;schema=' id='tables-" . h($db) . "' title='" . lang('Database schema') . "'>" . ($_GET["dbsize"] ? $tables : "?") . "</a>";
				echo "<td align='right' id='size-" . h($db) . "'>" . ($_GET["dbsize"] ? db_size($db) : "?");
				echo "\n";
			}

			echo "</table>\n";
			echo (support("database")
				? "<div class='footer'><div>\n"
					. "<fieldset><legend>" . lang('Selected') . " <span id='selected'></span></legend><div>\n"
					. "<input type='hidden' name='all' value=''" . on('click', 'countDbs') . ">\n" // used by trCheck()
					. "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n"
					. "</div></fieldset>\n"
					. "</div></div>\n"
				: ""
			);
			echo input_token();
			echo "</form>\n";
			echo script("tableCheck();");
		}

		$adminer = adminer();
		if ($adminer instanceof Plugins && ($adminer->plugins || $adminer->drivers)) {
			$checksums = $adminer->checksums();
			$official = Plugins::officialChecksums();

			$plugin_version = function (string $file) use ($checksums, $official): string {
				return ($checksums[$file] && $official[$file] && $checksums[$file] !== $official[$file] // unknown plugins are never reported; !== because e.g. '1e5' == '100000'
					? " (<a href='https://www.adminer.org/plugins/?version=" . VERSION . "'" . target_blank() . " class='update'>" . VERSION . "</a>)"
					: ""
				);
			};

			echo "<div class='plugins'>\n";
			echo "<h3>" . lang('Loaded plugins') . "</h3>\n<ul>\n";
			foreach ($adminer->plugins as $plugin) {
				$reflection = new \ReflectionObject($plugin);
				$description = (method_exists($plugin, 'description') ? $plugin->description() : "");
				if (!$description) {
					if (preg_match('~^/[\s*]+(.+)~', $reflection->getDocComment(), $match)) {
						$description = $match[1];
					}
				}
				$screenshot = (method_exists($plugin, 'screenshot') ? $plugin->screenshot() : "");
				echo "<li><b>" . get_class($plugin) . "</b>"
					. h($description ? ": $description" : "")
					. ($screenshot ? " (<a href='" . h($screenshot) . "'" . target_blank() . ">" . lang('screenshot') . "</a>)" : "")
					. $plugin_version(basename((string) $reflection->getFileName(), '.php'))
					. "\n"
				;
			}
			foreach ($adminer->drivers as $id => $name) {
				echo "<li><b>" . h($id) . "</b>: " . h($name) . $plugin_version(basename((string) $adminer->driverFiles[$id], '.php')) . "\n";
			}
			echo "</ul>\n";
			adminer()->pluginsLinks();
			echo "</div>\n";
		}
	}

	page_footer("db");
	exit;
}

if (support("scheme")) {
	if (DB != "" && $_GET["ns"] !== "") {
		if (!isset($_GET["ns"])) { // when the user goes to a database, take him to the default schema
			redirect(preg_replace('~&db=[^&]+~', '\0&ns=' . urlencode(get_schema()), relative_uri()));
		}
		if (!set_schema($_GET["ns"])) {
			header("HTTP/1.1 404 Not Found");
			page_header(lang('Schema') . ": " . h($_GET["ns"]), lang('Invalid schema.'), true);
			page_footer("ns");
			exit;
		}
	}
}
