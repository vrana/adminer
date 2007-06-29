<?php
session_start();
header("Content-Type: text/html; charset=utf-8");
error_reporting(E_ALL & ~E_NOTICE);
ob_start();

function lang($idf) {
	return $idf;
}

function idf_escape($idf) {
	return "`" . str_replace("`", "``", $idf) . "`";
}

function idf_unescape($idf) {
	return str_replace("``", "`", $idf);
}

function bracket_escape($idf, $back = false) {
	static $trans = array(':' => ':1', ']' => ':2');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

function optionlist($options, $selected = array(), $not_vals = false) {
	$return = "";
	foreach ($options as $key => $val) {
		$checked = in_array(($not_vals ? $val : $key), (array) $selected);
		$return .= '<option' . ($not_vals ? '' : ' value="' . htmlspecialchars($key) . '"') . ($checked ? ' selected="selected"' : '') . '>' . htmlspecialchars($val) . '</option>';
	}
	return $return;
}

function fields($table) {
	$return = array();
	$result = mysql_query("SHOW COLUMNS FROM " . idf_escape($table));
	while ($row = mysql_fetch_assoc($result)) {
		preg_match('~^(.*?)(?:\\((.+)\\))?$~', $row["Type"], $match);
		$return[$row["Field"]] = array(
			"type" => $match[1],
			"length" => $match[2],
			"default" => $row["Default"],
			"null" => ($row["Null"] != "NO"),
		);
	}
	mysql_free_result($result);
	return $return;
}

function indexes($table) {
	$return = array();
	$result = mysql_query("SHOW INDEX FROM " . idf_escape($table));
	while ($row = mysql_fetch_assoc($result)) {
		$type = ($row["Key_name"] == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? "INDEX" : "UNIQUE")));
		$return[$type][$row["Key_name"]][$row["Seq_in_index"]] = $row["Column_name"];
	}
	mysql_free_result($result);
	return $return;
}

function foreign_keys($table) {
	static $pattern = '~`((?:[^`]*|``)+)`~';
	$return = array();
	$create_table = mysql_result(mysql_query("SHOW CREATE TABLE " . idf_escape($table)), 0, 1);
	preg_match_all('~FOREIGN KEY \\((.*)\\) REFERENCES (.*) \\((.*)\\)~', $create_table, $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		preg_match_all($pattern, $match[1], $source);
		preg_match_all($pattern, $match[3], $target);
		foreach ($source[1] as $val) {
			$return[idf_unescape($val)][] = array(idf_unescape(substr($match[2], 1, -1)), array_map('idf_unescape', $source[1]), array_map('idf_unescape', $target[1]));
		}
	}
	return $return;
}

function unique_idf($row, $indexes) {
	foreach ($indexes as $type => $index) {
		if ($type == "PRIMARY" || $type == "UNIQUE") {
			foreach ($index as $columns) {
				$return = array();
				foreach ($columns as $key) {
					if (!isset($row[$key])) {
						continue 2;
					}
					$return[] = urlencode("where[$key]") . "=" . urlencode($row[$key]);
				}
				return $return;
			}
		}
	}
	$return = array();
	foreach ($row as $key => $val) {
		$return[] = (isset($val) ? urlencode("where[$key]") . "=" . urlencode($val) : "null%5B%5D=" . urlencode($key));
	}
	return $return;
}

if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

if (isset($_POST["server"])) {
	$_SESSION["username"] = $_POST["username"];
	$_SESSION["password"] = $_POST["password"];
	header("Location: " . ($_GET["server"] == $_POST["server"] ? $_SERVER["REQUEST_URI"] : preg_replace('~^[^?]*/([^?]*).*~', '\\1' . (strlen($_POST["server"]) ? '?server=' . urlencode($_POST["server"]) : ''), $_SERVER["REQUEST_URI"])));
	exit;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="cs">
<head>
<title><?php echo lang('phpMinAdmin'); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
BODY { color: Black; background-color: White; }
A { color: Blue; }
A:visited { color: Navy; }
.error { color: Red; }
.message { color: Green; }
</style>
</head>

<body>

<?php
if (!@mysql_connect($_GET["server"], $_SESSION["username"], $_SESSION["password"])) {
	?>
	<h1><?php echo lang('phpMinAdmin'); ?></h1>
	<?php
	if (isset($_GET["server"])) {
		echo "<p class='error'>" . lang('Invalid credentials.') . "</p>\n";
	}
?>

<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<tr><th><?php echo lang('Server'); ?>:</th><td><input name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" maxlength="60" /></td></tr>
<tr><th><?php echo lang('Login'); ?>:</th><td><input name="username" value="<?php echo htmlspecialchars($_SESSION["username"]); ?>" maxlength="16" /></td></tr>
<tr><th><?php echo lang('Password'); ?>:</th><td><input type="password" name="password" /></td></tr>
<tr><th><?php
foreach ((array) $_POST["fields"] as $key => $val) { // expired session
	echo '<input type="hidden" name="fields[' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($val) . '" />';
}
?>
</th><td><input type="submit" value="<?php echo lang('Login'); ?>" /></td></tr>
</table>
</form>
<?php
} else {
	$SELF = preg_replace('~^[^?]*/([^?]*).*~', '\\1?', $_SERVER["REQUEST_URI"]) . (strlen($_GET["server"]) ? 'server=' . urlencode($_GET["server"]) . '&' : '') . (isset($_GET["database"]) ? 'database=' . urlencode($_GET["database"]) . '&' : '');
	?>
	<div style="float: left; width: 15em;">
	<h1 style="font-size: 150%; margin: 0;"><?php echo lang('phpMinAdmin'); ?></h1>
	<p><a href="<?php echo htmlspecialchars($SELF); ?>sql="><?php echo lang('SQL command'); ?></a></p>
	
	<form action="" method="get">
	<p><select name="database" onchange="this.form.submit();"><option value="">(<?php echo lang('database'); ?>)</option>
	<?php
	//! logout
	$result = mysql_query("SHOW DATABASES");
	while ($row = mysql_fetch_row($result)) {
		echo "<option" . ($row[0] == $_GET["database"] ? " selected='selected'" : "") . ">" . htmlspecialchars($row[0]) . "</option>\n";
	}
	mysql_free_result($result);
	?>
	</select></p>
	<noscript><p><input type="submit" value="<?php echo lang('Use'); ?>" /></p></noscript>
	</form>
	<?php
	
	if (isset($_GET["database"]) && !mysql_select_db($_GET["database"])) {
		echo "<p class='error'>" . lang('Invalid database.') . "</p>\n";
	} else {
		mysql_query("SET CHARACTER SET utf8");
		
		if (isset($_GET["database"])) {
			$result = mysql_query("SHOW TABLES");
			if (!mysql_num_rows($result)) {
				echo "<p class='message'>" . lang('No tables.') . "</p>\n";
			} else {
				echo "<p>\n";
				while ($row = mysql_fetch_row($result)) {
					echo "<a href='" . htmlspecialchars($SELF) . "select=" . urlencode($row[0]) . "'>" . lang('select') . "</a> <a href='" . htmlspecialchars($SELF) . "table=" . urlencode($row[0]) . "'>" . htmlspecialchars($row[0]) . "</a><br />\n";
				}
				echo "</p>\n";
			}
			mysql_free_result($result);
		}
		?>
		</div>
		
		<div style="margin-left: 16em;">
		<?php
		
		if (isset($_GET["sql"])) {
			echo "<h2>" . lang('SQL command') . "</h2>\n";
			if ($_SESSION["message"]) {
				echo "<p class='message'>$_SESSION[message]</p>\n";
				$_SESSION["message"] = "";
			}
			if ($_POST) {
				$result = mysql_query($_POST["query"]);
				if (!$result) {
					echo "<p class='error'>" . lang('Error in query') . ": " . mysql_error() . "</p>\n";
				} elseif (mysql_num_rows($result)) {
					while ($row = mysql_fetch_assoc($result)) {
						//! select
					}
					mysql_free_result($result);
				} else {
					mysql_free_result($result);
					$_SESSION["message"] = sprintf(lang('Query executed OK, %d row(s) affected.'), mysql_affected_rows());
					header("Location: " . $SELF . "sql=");
					exit;
				}
			}
			?>
			<form action="" method="post">
			<p><textarea name="query" rows="20" cols="80"><?php echo htmlspecialchars($_POST["query"]); ?></textarea></p>
			<p><input type="submit" value="<?php echo lang('Execute'); ?>" /></p>
			</form>
			<?php
		
		} elseif (isset($_GET["table"])) {
			echo "<h2>" . lang('Table') . ": " . htmlspecialchars($_GET["table"]) . "</h2>\n";
			$result = mysql_query("SHOW FULL COLUMNS FROM " . idf_escape($_GET["table"]));
			echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
			while ($row = mysql_fetch_assoc($result)) {
				echo "<tr><th>" . htmlspecialchars($row["Field"]) . "</th><td>$row[Type]" . ($row["Null"] == "NO" ? " NOT NULL" : "") . "</td></tr>\n";
			}
			echo "</table>\n";
			mysql_free_result($result);
			
			$indexes = indexes($_GET["table"]);
			if ($indexes) {
				echo "<h3>" . lang('Indexes') . "</h3>\n";
				echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
				foreach ($indexes as $type => $index) {
					foreach ($index as $columns) {
						sort($columns);
						echo "<tr><td>$type</td><td><i>" . implode("</i>, <i>", $columns) . "</i></td></tr>\n";
					}
				}
				echo "</table>\n";
			}
		
		} elseif (isset($_GET["select"])) {
			ob_end_flush();
			echo "<h2>" . lang('Select') . ": " . htmlspecialchars($_GET["select"]) . "</h2>\n";
			if ($_SESSION["message"]) {
				echo "<p class='message'>$_SESSION[message]</p>\n";
				$_SESSION["message"] = "";
			}
			echo "<p><a href='" . htmlspecialchars($SELF) . "edit=" . urlencode($_GET["select"]) . "'>" . lang('New item') . "</a></p>\n";
			$limit = 30;
			
			echo "<form action=''><div>\n";
			if (strlen($_GET["server"])) {
				echo '<input type="hidden" name="server" value="' . htmlspecialchars($_GET["server"]) . '" />';
			}
			echo '<input type="hidden" name="database" value="' . htmlspecialchars($_GET["database"]) . '" />';
			echo '<input type="hidden" name="select" value="' . htmlspecialchars($_GET["select"]) . '" />';
			
			$where = array();
			$columns = array();
			foreach (fields($_GET["select"]) as $name => $field) {
				$columns[] = $name;
			}
			$operators = array("=", "<", ">", "<=", ">=", "!=", "IS NULL"); //! IS NULL - hide input
			$i = 0;
			foreach ((array) $_GET["where"] as $val) {
				if ($val["col"] && in_array($val["op"], $operators)) {
					$where[] = idf_escape($val["col"]) . " $val[op]" . ($val["op"] != "IS NULL" ? " '" . mysql_real_escape_string($val["val"]) . "'" : "");
					echo "<select name='where[$i][col]'><option></option>" . optionlist($columns, $val["col"], "not_vals") . "</select>";
					echo "<select name='where[$i][op]'>" . optionlist($operators, $val["op"], "not_vals") . "</select>";
					echo "<input name='where[$i][val]' value=\"" . htmlspecialchars($val["val"]) . "\" /><br />\n";
					$i++;
				}
			}
			echo "<select name='where[$i][col]'><option></option>" . optionlist($columns, array(), "not_vals") . "</select>";
			echo "<select name='where[$i][op]'>" . optionlist($operators, array(), "not_vals") . "</select>";
			echo "<input name='where[$i][val]' /><br />\n"; //! JavaScript for adding next
			
			//! sort, limit
			
			echo "<input type='submit' value='" . lang('Search') . "' />\n";
			echo "</div></form>\n";
			$result = mysql_query("SELECT SQL_CALC_FOUND_ROWS * FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "") . " LIMIT $limit OFFSET " . ($limit * $_GET["page"]));
			$found_rows = mysql_result(mysql_query(" SELECT FOUND_ROWS()"), 0);
			if (!mysql_num_rows($result)) {
				echo "<p class='message'>" . lang('No rows.') . "</p>\n";
			} else {
				$indexes = indexes($_GET["select"]);
				$foreign_keys = foreign_keys($_GET["select"]);
				
				echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
				$first = true;
				while ($row = mysql_fetch_assoc($result)) {
					if ($first) {
						echo "<thead><tr><th>" . implode("</th><th>", array_map('htmlspecialchars', array_keys($row))) . "</th><th>" . lang('Action') . "</th></tr></thead>\n";
						$first = false;
					}
					echo "<tr>";
					foreach ($row as $key => $val) {
						if (!isset($val)) {
							$val = "<i>NULL</i>";
						} else {
							$val = htmlspecialchars($val);
							if (count($foreign_keys[$key]) == 1) {
								$foreign_key = $foreign_keys[$key][0];
								$val = '">' . "$val</a>";
								foreach ($foreign_key[1] as $i => $source) {
									$val = "&amp;where[$i][col]=" . urlencode($foreign_key[2][$i]) . "&amp;where[$i][op]=%3D&amp;where[$i][val]=" . urlencode($row[$source]) . $val;
								}
								$val = '<a href="' . htmlspecialchars($SELF) . 'select=' . htmlspecialchars($foreign_key[0]) . $val; // InnoDB support non-UNIQUE keys //! other database
							}
						}
						echo "<td>$val</td>";
					}
					echo "<td><a href='" . htmlspecialchars($SELF) . "edit=" . urlencode($_GET["select"]) . "&amp;" . implode("&amp;", unique_idf($row, $indexes)) . "'>edit</a></td>"; //! links to referencing tables
					echo "</tr>\n";
				}
				echo "</table>\n";
				if ($found_rows > $limit) {
					echo "<p>" . lang('Page') . ":\n";
					for ($i=0; $i < $found_rows / $limit; $i++) {
						echo ($i == $_GET["page"] ? $i + 1 : "<a href='" . htmlspecialchars($SELF) . "select=" . urlencode($_GET["select"]) . ($i ? "&amp;page=$i" : "") . "'>" . ($i + 1) . "</a>") . "\n";
					}
					echo "</p>\n";
				}
			}
			mysql_free_result($result);
		
		} elseif (isset($_GET["edit"])) {
			echo "<h2>" . lang('Edit') . ": " . htmlspecialchars($_GET["edit"]) . "</h2>\n";
			$where = array();
			if (is_array($_GET["where"])) {
				foreach ($_GET["where"] as $key => $val) {
					$where[] = idf_escape($key) . " = BINARY '" . mysql_real_escape_string($val) . "'";
				}
			}
			if (is_array($_GET["null"])) {
				foreach ($_GET["null"] as $key) {
					$where[] = idf_escape($key) . " IS NULL";
				}
			}
			$fields = fields($_GET["edit"]);
			if ($_POST) {
				if (isset($_POST["delete"])) {
					$query = "DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
					$message = lang('Item has been deleted.');
				} else {
					$set = array();
					foreach ($fields as $key => $field) {
						if (preg_match('~char|text|set~', $field["type"]) ? $_POST["null"][$key] : !strlen($_POST["fields"][$key])) {
							$value = "NULL";
						} elseif ($field["type"] == "enum") {
							$value = intval($_POST["fields"][$key]);
						} elseif ($field["type"] == "set") {
							$value = array_sum((array) $_POST["fields"][$key]);
						} else {
							$value = "'" . mysql_real_escape_string($_POST["fields"][$key]) . "'";
						}
						$set[] = idf_escape(bracket_escape($key, "back")) . " = $value";
					}
					if ($where) {
						$query = "UPDATE " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
						$message = lang('Item has been updated.');
					} else {
						$query = "INSERT INTO " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set);
						$message = lang('Item has been inserted.');
					}
				}
				if (mysql_query($query)) {
					$_SESSION["message"] = $message;
					header("Location: " . $SELF . "select=" . urlencode($_GET["edit"]));
					exit;
				} else {
					echo "<p class='error'>" . lang('Error during saving') . ": " . htmlspecialchars(mysql_error()) . "</p>\n";
				}
			}
			if ($_POST) {
				$data = $_POST["fields"];
			} elseif ($where) {
				$select = array("*");
				foreach ($fields as $name => $field) {
					if ($field["type"] == "enum" || $field["type"] == "set") {
						$select[] = "1*" . idf_escape($name) . " AS " . idf_escape($name);
					}
				}
				$data = mysql_fetch_assoc(mysql_query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1"));
			} else {
				$data = array();
			}
			?>
			<form action="" method="post">
			<table border='1' cellspacing='0' cellpadding='2'>
			<?php
			foreach ($fields as $name => $field) {
				echo "<tr><th>" . htmlspecialchars($name) . "</th><td>";
				$value = ($data ? $data[$name] : $field["default"]);
				$name = htmlspecialchars(bracket_escape($name));
				if ($field["type"] == "enum") {
					echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value == "0" ? ' checked="checked"' : '') . ' />';
					preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
					foreach ($matches[1] as $i => $val) {
						$id = "field-$name-" . ($i+1);
						echo ' <input type="radio" name="fields[' . $name . ']" id="' . $id . '" value="' . ($i+1) . '"' . ($value == $i+1 ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars(str_replace("''", "'", $val)) . '</label>';
					}
					if ($field["null"]) {
						$id = "field-$name-";
						echo '<input type="radio" name="fields[' . $name . ']" id="' . $id . '" value=""' . (isset($value) ? '' : ' checked="checked"') . ' /><label for="' . $id . '">' . lang('NULL') . '</label> ';
					}
				} elseif ($field["type"] == "set") { //! 64 bits
					preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
					foreach ($matches[1] as $i => $val) {
						$id = "$name-" . ($i+1);
						echo ' <input type="checkbox" name="fields[' . $name . '][]" id="' . $id . '" value="' . pow(2, $i) . '"' . ($value & pow(2, $i) ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars(str_replace("''", "'", $val)) . '</label>';
					}
				} elseif (strpos($field["type"], "text") !== false) {
					echo '<textarea name="fields[' . $name . ']" cols="50" rows="12">' . htmlspecialchars($value) . '</textarea>';
				} else { //! numbers, date, binary
					echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . (strlen($field["length"]) ? " maxlength='$field[length]'" : '') . ' />';
				}
				if ($field["null"] && preg_match('~char|text|set~', $field["type"])) {
					echo '<input type="checkbox" name="null[' . $name . ']" value="1" id="null-' . $name . '"' . (isset($value) ? '' : ' checked="checked"') . ' /><label for="null-' . $name . '">' . lang('NULL') . '</label>';
				}
				echo "</td></tr>\n";
			}
			echo "<tr><th></th><td><input type='submit' value='" . lang('Save') . "' />" . ($where ? " <input type='submit' name='delete' value='" . lang('Delete') . "' />" : "") . "</td></tr>\n";
			?>
			</table>
			</form>
			<?php
		}
			
	}
	?>
	</div>
	<?php
}
?>

</body>
</html>
