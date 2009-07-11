<?php
$privileges = array("" => array("All privileges" => ""));
$result = $dbh->query("SHOW PRIVILEGES");
while ($row = $result->fetch_assoc()) {
	if ($row["Privilege"] == "Grant option") {
		$privileges[""]["Grant option"] = $row["Comment"];
	} else {
		foreach (explode(",", $row["Context"]) as $context) {
			$privileges[$context][$row["Privilege"]] = $row["Comment"];
		}
	}
}
$result->free();
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

function grant($grant, $columns) {
	return preg_replace('~(GRANT OPTION)\\([^)]*\\)~', '\\1', implode("$columns, ", $grant) . $columns);
}

$new_grants = array();
if ($_POST) {
	foreach ($_POST["objects"] as $key => $val) {
		$new_grants[$val] = ((array) $new_grants[$val]) + ((array) $_POST["grants"][$key]);
	}
}
$grants = array();
$old_pass = "";
if (isset($_GET["host"]) && ($result = $dbh->query("SHOW GRANTS FOR " . $dbh->quote($_GET["user"]) . "@" . $dbh->quote($_GET["host"])))) { //! use information_schema for MySQL 5 - column names in column privileges are not escaped
	while ($row = $result->fetch_row()) {
		if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match) && preg_match_all('~ *([^(,]*[^ ,(])( *\\([^)]+\\))?~', $match[1], $matches, PREG_SET_ORDER)) { //! escape the part between ON and TO
			foreach ($matches as $val) {
				$grants["$match[2]$val[2]"][$val[1]] = true;
				if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
					$grants["$match[2]$val[2]"]["GRANT OPTION"] = true;
				}
			}
		}
		if (preg_match("~ IDENTIFIED BY PASSWORD '([^']+)~", $row[0], $match)) {
			$old_pass = $match[1];
		}
	}
	$result->free();
}

if ($_POST && !$error) {
	$old_user = (isset($_GET["host"]) ? $dbh->quote($_GET["user"]) . "@" . $dbh->quote($_GET["host"]) : "''");
	$new_user = $dbh->quote($_POST["user"]) . "@" . $dbh->quote($_POST["host"]); // if $_GET["host"] is not set then $new_user is always different
	$pass = $dbh->quote($_POST["pass"]);
	if ($_POST["drop"]) {
		query_redirect("DROP USER $old_user", $SELF . "privileges=", lang('User has been dropped.'));
	} else {
		if ($old_user == $new_user) {
			queries("SET PASSWORD FOR $new_user = " . ($_POST["hashed"] ? $pass : "PASSWORD($pass)"));
		} else {
			$error = !queries(($dbh->server_info < 5 ? "GRANT USAGE ON *.* TO" : "CREATE USER") . " $new_user IDENTIFIED BY" . ($_POST["hashed"] ? " PASSWORD" : "") . " $pass");
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
				($grant && !queries("GRANT " . grant($grant, $match[2]) . " ON $match[1] TO $new_user")) //! SQL injection
				|| ($revoke && !queries("REVOKE " . grant($revoke, $match[2]) . " ON $match[1] FROM $new_user"))
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
						queries("REVOKE " . grant(array_keys($revoke), $match[2]) . " ON $match[1] FROM $new_user");
					}
				}
			}
		}
		query_redirect(queries(), $SELF . "privileges=", (isset($_GET["host"]) ? lang('User has been altered.') : lang('User has been created.')), !$error, false, $error);
		if ($old_user != $new_user) {
			// delete new user in case of an error
			$dbh->query("DROP USER $new_user");
		}
	}
}
page_header((isset($_GET["host"]) ? lang('Username') . ": " . htmlspecialchars("$_GET[user]@$_GET[host]") : lang('Create user')), $error, array("privileges" => array('', lang('Privileges'))));

if ($_POST) {
	$row = $_POST;
	$grants = $new_grants;
} else {
	$row = $_GET + array("host" => $dbh->result($dbh->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)"))); // create user on the same domain by default
	$row["pass"] = $old_pass;
	if (strlen($old_pass)) {
		$row["hashed"] = true;
	}
	$grants[""] = true;
}

?>
<form action="" method="post">
<table cellspacing="0">
<tr><th><?php echo lang('Username'); ?><td><input name="user" maxlength="16" value="<?php echo htmlspecialchars($row["user"]); ?>">
<tr><th><?php echo lang('Server'); ?><td><input name="host" maxlength="60" value="<?php echo htmlspecialchars($row["host"]); ?>">
<tr><th><?php echo lang('Password'); ?><td><input id="pass" name="pass" value="<?php echo htmlspecialchars($row["pass"]); ?>"><?php if (!$row["hashed"]) { ?><script type="text/javascript">document.getElementById('pass').type = 'password';</script><?php } ?> <label><input type="checkbox" name="hashed" value="1"<?php if ($row["hashed"]) { ?> checked="checked"<?php } ?> onclick="this.form['pass'].type = (this.checked ? 'text' : 'password');"><?php echo lang('Hashed'); ?></label>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th colspan='2'>" . lang('Privileges');
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*" ? '<input name="objects[' . $i . ']" value="' . htmlspecialchars($object) . '" size="10">' : '<input type="hidden" name="objects[' . $i . ']" value="*.*" size="10">*.*'); //! separate db, table, columns, PROCEDURE|FUNCTION, routine
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
		echo "<tr" . odd() . "><td" . ($desc ? ">$desc<td" : " colspan='2'") . ' title="' . htmlspecialchars($comment) . '"><i>' . htmlspecialchars($privilege) . "</i>";
		$i = 0;
		foreach ($grants as $object => $grant) {
			$name = '"grants[' . $i . '][' . htmlspecialchars(strtoupper($privilege)) . ']"';
			$value = $grant[strtoupper($privilege)];
			if ($context == "Server Admin" && $object != (isset($grants["*.*"]) ? "*.*" : "")) {
				echo "<td>&nbsp;";
			} elseif (isset($_GET["grant"])) {
				echo "<td><select name=$name><option><option value='1'" . ($value ? " selected='selected'" : "") . ">" . lang('Grant') . "<option value='0'" . ($value == "0" ? " selected='selected'" : "") . ">" . lang('Revoke') . "</select>";
			} else {
				echo "<td align='center'><input type='checkbox' name=$name value='1'" . ($value ? " checked='checked'" : "") . ">";
			}
			$i++;
		}
	}
}
echo "</table>\n";
?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if (isset($_GET["host"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?>><?php } ?>
</form>
