<?php
if (isset($_GET["name"])) {
	page_header((strlen($_GET["privileges"]) ? lang('Username') . ": " . htmlspecialchars("$_GET[name]@$_GET[privileges]") : lang('Create user')), array("privileges" => lang('Privileges')));
	$privileges = array();
	$result = $mysql->query("SHOW PRIVILEGES");
	while ($row = $result->fetch_assoc()) {
		foreach (explode(",", $row["Context"]) as $context) {
			$privileges[$context][$row["Privilege"]] = $row["Comment"]; //! translation
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
	unset($privileges["Functions"]["Create routine"]);
	$grants = array();
	if (strlen($_GET["privileges"]) && ($result = $mysql->query("SHOW GRANTS FOR '" . $mysql->escape_string($_GET["name"]) . "'@'" . $mysql->escape_string($_GET["privileges"]) . "'"))) { //! Use information_schema for MySQL 5 - column names in column privileges are not escaped
		while ($row = $result->fetch_row()) {
			if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match)) { //! escape part between ON and TO
				preg_match_all('~ *([^(,]*[^ ,(])( *\\([^)]+\\))?~', $match[1], $matches, PREG_SET_ORDER);
				foreach ($matches as $val) {
					$grants["$match[2]$val[2]"][$val[1]] = true;
				}
			}
			if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
				$grants[$match[2]]["GRANT OPTION"] = true;
			}
		}
		$result->free();
	}
	$grants[""] = true;
	
	foreach (array(
		"Server Admin" => lang('Server'),
		"Databases" => lang('Database'),
		"Tables" => lang('Table'),
		"Columns" => lang('Column'),
		"Procedures" => lang('Procedure'),
		"Functions" => lang('Function'),
	) as $key => $val) {
		if ($privileges[$key]) {
			echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
			echo "<thead><tr>";
			if ($key != "Server Admin") {
				echo "<th>$val</th>";
			}
			foreach ($privileges[$key] as $privilege => $comment) {
				echo '<td title="' . htmlspecialchars($comment) . '">' . htmlspecialchars($privilege) . '</td>';
			}
			echo "</tr></thead>\n";
			foreach ($grants as $object => $grant) {
				if ($key == "Server Admin" ? $object == (isset($grants["*.*"]) ? "*.*" : "")
				: !$object || (substr($object, -1) == ")" || $key == "Columns" ? substr($object, -1) == ")" xor $key != "Columns"
				: (preg_match('~PROCEDURE ~', $object) ? $key == "Procedures"
				: (preg_match('~FUNCTION ~', $object) ? $key == "Functions"
				: (substr($object, -1) == "*" || $key == "Tables"
				))))) {
					echo "<tr align='center'>";
					if ($key != "Server Admin") {
						echo '<th><input name="" value="' . htmlspecialchars($object) . "\" size='10' /></th>";
					}
					foreach ($privileges[$key] as $privilege => $comment) {
						echo "<td><input type='checkbox' name='grant' value='1'" . ($grant[strtoupper($privilege)] || ($privilege != "Grant option" && $grant["ALL PRIVILEGES"]) ? " checked='checked'" : "") . " /></td>";
					}
					echo "</tr>\n";
				}
			}
			echo "</table>\n";
		}
	}
	//! DROP USER, name, server, password
} else {
	page_header(lang('Privileges'));
	echo '<p><a href="' . htmlspecialchars($SELF) . 'privileges=&amp;name=">' . lang('Create user') . "</a></p>\n";
	//! use mysql database if possible (GRANTEE not properly escaped) or CURRENT_USER in MySQL 4 in case of insufficient privileges
	$result = $mysql->query("SELECT DISTINCT GRANTEE FROM information_schema.USER_PRIVILEGES");
	echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
	echo "<thead><tr><th>&nbsp;</th><th>" . lang('Username') . "</th><th>" . lang('Server') . "</th></tr></thead>\n";
	while ($row = $result->fetch_row()) {
		preg_match("~'((?:[^']+|'')*)'@'((?:[^']+|'')+)'~", $row[0], $match);
		echo '<tr><td><a href="' . htmlspecialchars($SELF) . 'privileges=' . urlencode($match[2]) . '&amp;name=' . urlencode($match[1]) . '">' . lang('edit') . '</a></td><td>' . htmlspecialchars(str_replace("''", "'", $match[1])) . "</td><td>" . htmlspecialchars(str_replace("''", "'", $match[2])) . "</td></tr>\n";
	}
	echo "</table>\n";
	$result->free();
}
