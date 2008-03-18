<?php
$privileges = array();
$result = $mysql->query("SHOW PRIVILEGES");
while ($row = $result->fetch_assoc()) {
	foreach (explode(",", $row["Context"]) as $context) {
		$privileges[$context][$row["Privilege"]] = $row["Comment"];
	}
}
$result->free();
$privileges["Server Admin"] += $privileges["File access on server"];
$privileges["Databases"]["Create routine"] = $privileges["Procedures"]["Create routine"];
$privileges["Columns"] = array();
foreach (array("Select", "Insert", "Update", "References") as $val) {
	$privileges["Columns"][$val] = $privileges["Tables"][$val];
}
unset($privileges["Server Admin"]["Usage"]);
unset($privileges["Procedures"]["Create routine"]);
foreach ($privileges["Tables"] as $key => $val) {
	unset($privileges["Databases"][$key]);
}

function all_privileges(&$grants, $privileges) {
	foreach ($privileges as $privilege => $val) {
		if ($privilege != "Grant option") {
			$grants[strtoupper($privilege)] = true;
		}
	}
}

if ($_POST) {
	$new_grants = array();
	foreach ($_POST["objects"] as $key => $val) {
		$new_grants[$val] = ((array) $new_grants[$val]) + ((array) $_POST["grants"][$key]);
	}
}
$grants = array();
$old_pass = "";
if (isset($_GET["host"]) && ($result = $mysql->query("SHOW GRANTS FOR '" . $mysql->escape_string($_GET["user"]) . "'@'" . $mysql->escape_string($_GET["host"]) . "'"))) { //! Use information_schema for MySQL 5 - column names in column privileges are not escaped
	while ($row = $result->fetch_row()) {
		if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match)) { //! escape the part between ON and TO
			if ($match[1] == "ALL PRIVILEGES") {
				if ($match[2] == "*.*") {
					all_privileges($grants[$match[2]], $privileges["Server Admin"]);
				}
				if (substr($match[2], -1) == "*") {
					all_privileges($grants[$match[2]], $privileges["Databases"]);
					all_privileges($grants[$match[2]], (array) $privileges["Procedures"]);
				}
				all_privileges($grants[$match[2]], $privileges["Tables"]);
			} elseif (preg_match_all('~ *([^(,]*[^ ,(])( *\\([^)]+\\))?~', $match[1], $matches, PREG_SET_ORDER)) {
				foreach ($matches as $val) {
					$grants["$match[2]$val[2]"][$val[1]] = true;
				}
			}
		}
		if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
			$grants[$match[2]]["GRANT OPTION"] = true;
		}
		if (preg_match("~ IDENTIFIED BY PASSWORD '([^']+)~", $row[0], $match)) {
			$old_pass = $match[1];
		}
	}
	$result->free();
}

if ($_POST && !$error) {
	$old_user = (isset($_GET["host"]) ? $mysql->escape_string($_GET["user"]) . "'@'" . $mysql->escape_string($_GET["host"]) : "");
	$new_user = $mysql->escape_string($_POST["user"]) . "'@'" . $mysql->escape_string($_POST["host"]);
	$pass = $mysql->escape_string($_POST["pass"]);
	if ($_POST["drop"]) {
		if ($mysql->query("DROP USER '$old_user'")) {
			redirect($SELF . "privileges=", lang('User has been dropped.'));
		}
	} elseif ($old_user == $new_user || $mysql->query(($mysql->server_info < 5 ? "GRANT USAGE ON *.* TO" : "CREATE USER") . " '$new_user' IDENTIFIED BY" . ($_POST["hashed"] ? " PASSWORD" : "") . " '$pass'")) {
		if ($old_user == $new_user) {
			$mysql->query("SET PASSWORD FOR '$new_user' = " . ($_POST["hashed"] ? "'$pass'" : "PASSWORD('$pass')"));
		}
		$revoke = array();
		foreach ($new_grants as $object => $grant) {
			if (isset($_GET["grant"])) {
				$grant = array_filter($grant);
			}
			$grant = array_keys($grant);
			if (isset($_GET["grant"])) {
				$revoke = array_diff(array_keys(array_filter($new_grants[$object], 'strlen')), $grant);
			} elseif ($old_user == $new_user) {
				$old_grant = array_keys((array) $grants[$object]);
				$revoke = array_diff($old_grant, $grant);
				$grant = array_diff($grant, $old_grant);
				unset($grants[$object]);
			}
			if (preg_match('~^(.+)(\\(.*\\))?$~U', $object, $match) && (
			($grant && !$mysql->query("GRANT " . implode("$match[2], ", $grant) . "$match[2] ON $match[1] TO '$new_user'")) //! SQL injection
			|| ($revoke && !$mysql->query("REVOKE " . implode("$match[2], ", $revoke) . "$match[2] ON $match[1] FROM '$new_user'"))
			)) {
				$error = $mysql->error;
				if ($old_user != $new_user) {
					$mysql->query("DROP USER '$new_user'");
				}
				break;
			}
		}
		if (!$error) {
			if (isset($_GET["host"]) && $old_user != $new_user) {
				$mysql->query("DROP USER '$old_user'");
			} elseif (!isset($_GET["grant"])) {
				foreach ($grants as $object => $revoke) {
					if (preg_match('~^(.+)(\\(.*\\))?$~U', $object, $match)) {
						$mysql->query("REVOKE " . implode("$match[2], ", array_keys($revoke)) . "$match[2] ON $match[1] FROM '$new_user'");
					}
				}
			}
			redirect($SELF . "privileges=", (isset($_GET["host"]) ? lang('User has been altered.') : lang('User has been created.')));
		}
	}
	if (!$error) {
		$error = $mysql->error;
	}
}
page_header((isset($_GET["host"]) ? lang('Username') . ": " . htmlspecialchars("$_GET[user]@$_GET[host]") : lang('Create user')), array("privileges" => lang('Privileges')));

if ($error) {
	echo "<p class='error'>" . lang('Unable to operate user') . ": " . htmlspecialchars($error) . "</p>\n";
}
if ($_POST) {
	$row = $_POST;
	$grants = $new_grants;
} else {
	$row = $_GET + array("host" => "localhost");
	$row["pass"] = $old_pass;
	if (strlen($old_pass)) {
		$row["hashed"] = true;
	}
	$grants[""] = true;
}

?>
<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<tr><th><?php echo lang('Username'); ?></th><td><input name="user" maxlength="16" value="<?php echo htmlspecialchars($row["user"]); ?>" /></td></tr>
<tr><th><?php echo lang('Server'); ?></th><td><input name="host" maxlength="60" value="<?php echo htmlspecialchars($row["host"]); ?>" /></td></tr>
<tr><th><?php echo lang('Password'); ?></th><td><input name="pass" value="<?php echo htmlspecialchars($row["pass"]); ?>" /> <label for="hashed"><input type="checkbox" name="hashed" id="hashed" value="1"<?php if ($row["hashed"]) { ?> checked="checked"<?php } ?> /><?php echo lang('Hashed'); ?></label></td></tr>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
echo "<thead><tr><th colspan='2'>" . lang('Privileges') . "</th>";
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*" ? '<input name="objects[' . $i . ']" value="' . htmlspecialchars($object) . '" size="10" />' : '<input type="hidden" name="objects[' . $i . ']" value="*.*" size="10" />*.*') . '</th>'; //! separate db, table, columns, PROCEDURE|FUNCTION, routine
	//! JS checkbox for all
	$i++;
}
echo "</tr></thead>\n";
foreach (array(
	"Server Admin" => lang('Server'),
	"Databases" => lang('Database'),
	"Tables" => lang('Table'),
	"Columns" => lang('Column'),
	"Procedures" => lang('Routine'),
) as $context => $desc) {
	foreach ((array) $privileges[$context] as $privilege => $comment) {
		echo '<tr><td>' . $desc . '</td><td title="' . htmlspecialchars($comment) . '"><i>' . htmlspecialchars($privilege) . '</i></td>';
		$i = 0;
		foreach ($grants as $object => $grant) {
			$name = '"grants[' . $i . '][' . htmlspecialchars(strtoupper($privilege)) . ']"';
			$value = $grant[strtoupper($privilege)];
			if ($context == "Server Admin" && $object != (isset($grants["*.*"]) ? "*.*" : "")) {
				echo "<td>&nbsp;</td>";
			} elseif (isset($_GET["grant"])) {
				echo "<td><select name=$name><option></option><option value='1'" . ($value ? " selected='selected'" : "") . ">" . lang('Grant') . "</option><option value='0'" . ($value == "0" ? " selected='selected'" : "") . ">" . lang('Revoke') . "</option></select></td>";
			} else {
				echo "<td align='center'><input type='checkbox' name=$name value='1'" . ($value ? " checked='checked'" : "") . " /></td>";
			}
			$i++;
		}
		echo "</tr>\n";
	}
}
echo "</table>\n";
?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (isset($_GET["host"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
