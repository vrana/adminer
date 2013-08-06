<?php
$USER = $_GET["user"];
$privileges = array("" => array("All privileges" => ""));
foreach (get_rows("SHOW PRIVILEGES") as $row) {
	foreach (explode(",", ($row["Privilege"] == "Grant option" ? "" : $row["Context"])) as $context) {
		$privileges[$context][$row["Privilege"]] = $row["Comment"];
	}
}
$privileges["Server Admin"] += $privileges["File access on server"];
$privileges["Databases"]["Create routine"] = $privileges["Procedures"]["Create routine"]; // MySQL bug #30305
unset($privileges["Procedures"]["Create routine"]);
$privileges["Columns"] = array();
foreach (array("Select", "Insert", "Update", "References") as $val) {
	$privileges["Columns"][$val] = $privileges["Tables"][$val];
}
unset($privileges["Server Admin"]["Usage"]);
foreach ($privileges["Tables"] as $key => $val) {
	unset($privileges["Databases"][$key]);
}

$new_grants = array();
if ($_POST) {
	foreach ($_POST["objects"] as $key => $val) {
		$new_grants[$val] = (array) $new_grants[$val] + (array) $_POST["grants"][$key];
	}
}
$grants = array();
$old_pass = "";

if (isset($_GET["host"]) && ($result = $connection->query("SHOW GRANTS FOR " . q($USER) . "@" . q($_GET["host"])))) { //! use information_schema for MySQL 5 - column names in column privileges are not escaped
	while ($row = $result->fetch_row()) {
		if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match) && preg_match_all('~ *([^(,]*[^ ,(])( *\\([^)]+\\))?~', $match[1], $matches, PREG_SET_ORDER)) { //! escape the part between ON and TO
			foreach ($matches as $val) {
				if ($val[1] != "USAGE") {
					$grants["$match[2]$val[2]"][$val[1]] = true;
				}
				if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
					$grants["$match[2]$val[2]"]["GRANT OPTION"] = true;
				}
			}
		}
		if (preg_match("~ IDENTIFIED BY PASSWORD '([^']+)~", $row[0], $match)) {
			$old_pass = $match[1];
		}
	}
}

if ($_POST && !$error) {
	$old_user = (isset($_GET["host"]) ? q($USER) . "@" . q($_GET["host"]) : "''");
	if ($_POST["drop"]) {
		query_redirect("DROP USER $old_user", ME . "privileges=", lang('User has been dropped.'));
	} else {
		$new_user = q($_POST["user"]) . "@" . q($_POST["host"]); // if $_GET["host"] is not set then $new_user is always different
		$pass = $_POST["pass"];
		if ($pass != '' && !$_POST["hashed"]) {
			// compute hash in a separate query so that plain text password is not saved to history
			$pass = $connection->result("SELECT PASSWORD(" . q($pass) . ")");
			$error = !$pass;
		}

		$created = false;
		if (!$error) {
			if ($old_user != $new_user) {
				$created = queries(($connection->server_info < 5 ? "GRANT USAGE ON *.* TO" : "CREATE USER") . " $new_user IDENTIFIED BY PASSWORD " . q($pass));
				$error = !$created;
			} elseif ($pass != $old_pass) {
				queries("SET PASSWORD FOR $new_user = " . q($pass));
			}
		}

		if (!$error) {
			$revoke = array();
			foreach ($new_grants as $object => $grant) {
				if (isset($_GET["grant"])) {
					$grant = array_filter($grant);
				}
				$grant = array_keys($grant);
				if (isset($_GET["grant"])) {
					// no rights to mysql.user table
					$revoke = array_diff(array_keys(array_filter($new_grants[$object], 'strlen')), $grant);
				} elseif ($old_user == $new_user) {
					$old_grant = array_keys((array) $grants[$object]);
					$revoke = array_diff($old_grant, $grant);
					$grant = array_diff($grant, $old_grant);
					unset($grants[$object]);
				}
				if (preg_match('~^(.+)\\s*(\\(.*\\))?$~U', $object, $match) && (
					!grant("REVOKE", $revoke, $match[2], " ON $match[1] FROM $new_user") //! SQL injection
					|| !grant("GRANT", $grant, $match[2], " ON $match[1] TO $new_user")
				)) {
					$error = true;
					break;
				}
			}
		}

		if (!$error && isset($_GET["host"])) {
			if ($old_user != $new_user) {
				queries("DROP USER $old_user");
			} elseif (!isset($_GET["grant"])) {
				foreach ($grants as $object => $revoke) {
					if (preg_match('~^(.+)(\\(.*\\))?$~U', $object, $match)) {
						grant("REVOKE", array_keys($revoke), $match[2], " ON $match[1] FROM $new_user");
					}
				}
			}
		}

		queries_redirect(ME . "privileges=", (isset($_GET["host"]) ? lang('User has been altered.') : lang('User has been created.')), !$error);

		if ($created) {
			// delete new user in case of an error
			$connection->query("DROP USER $new_user");
		}
	}
}

page_header((isset($_GET["host"]) ? lang('Username') . ": " . h("$USER@$_GET[host]") : lang('Create user')), $error, array("privileges" => array('', lang('Privileges'))));

if ($_POST) {
	$row = $_POST;
	$grants = $new_grants;
} else {
	$row = $_GET + array("host" => $connection->result("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")); // create user on the same domain by default
	$row["pass"] = $old_pass;
	if ($old_pass != "") {
		$row["hashed"] = true;
	}
	$grants[(DB == "" || $grants ? "" : idf_escape(addcslashes(DB, "%_\\"))) . ".*"] = array();
}

?>
<form action="" method="post">
<table cellspacing="0">
<tr><th><?php echo lang('Server'); ?><td><input name="host" maxlength="60" value="<?php echo h($row["host"]); ?>" autocapitalize="off">
<tr><th><?php echo lang('Username'); ?><td><input name="user" maxlength="16" value="<?php echo h($row["user"]); ?>" autocapitalize="off">
<tr><th><?php echo lang('Password'); ?><td><input name="pass" id="pass" value="<?php echo h($row["pass"]); ?>">
<?php if (!$row["hashed"]) { ?><script type="text/javascript">typePassword(document.getElementById('pass'));</script><?php } ?>
<?php echo checkbox("hashed", 1, $row["hashed"], lang('Hashed'), "typePassword(this.form['pass'], this.checked);"); ?>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th colspan='2'>" . lang('Privileges') . doc_link(array('sql' => "grant.html#priv_level"));
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*" ? "<input name='objects[$i]' value='" . h($object) . "' size='10' autocapitalize='off'>" : "<input type='hidden' name='objects[$i]' value='*.*' size='10'>*.*"); //! separate db, table, columns, PROCEDURE|FUNCTION, routine
	$i++;
}
echo "</thead>\n";

foreach (array(
	"" => "",
	"Server Admin" => lang('Server'),
	"Databases" => lang('Database'),
	"Tables" => lang('Table'),
	"Columns" => lang('Column'),
	"Procedures" => lang('Routine'),
) as $context => $desc) {
	foreach ((array) $privileges[$context] as $privilege => $comment) {
		echo "<tr" . odd() . "><td" . ($desc ? ">$desc<td" : " colspan='2'") . ' lang="en" title="' . h($comment) . '">' . h($privilege);
		$i = 0;
		foreach ($grants as $object => $grant) {
			$name = "'grants[$i][" . h(strtoupper($privilege)) . "]'";
			$value = $grant[strtoupper($privilege)];
			if ($context == "Server Admin" && $object != (isset($grants["*.*"]) ? "*.*" : ".*")) {
				echo "<td>&nbsp;";
			} elseif (isset($_GET["grant"])) {
				echo "<td><select name=$name><option><option value='1'" . ($value ? " selected" : "") . ">" . lang('Grant') . "<option value='0'" . ($value == "0" ? " selected" : "") . ">" . lang('Revoke') . "</select>";
			} else {
				echo "<td align='center'><label class='block'><input type='checkbox' name=$name value='1'" . ($value ? " checked" : "") . ($privilege == "All privileges" ? " id='grants-$i-all'" : ($privilege == "Grant option" ? "" : " onclick=\"if (this.checked) formUncheck('grants-$i-all');\"")) . "></label>"; //! uncheck all except grant if all is checked
			}
			$i++;
		}
	}
}

echo "</table>\n";
?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if (isset($_GET["host"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
