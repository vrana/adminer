<?php
namespace Adminer;

/** Get values of AS ENUM (...)
* @return ?list<string> SQL literals, null if the definition is not an enum
*/
function enum_values(string $definition): ?array {
	$value = "'(?:[^']|'')*'"; // a value can't contain an unescaped apostrophe so it's safe to use it in a query
	if (!preg_match('~^AS\s+ENUM\s*\(\s*(' . $value . '(?:\s*,\s*' . $value . ')*)\s*\)$~i', $definition, $match)) {
		return null;
	}
	preg_match_all('~' . $value . '~', $match[1], $matches);
	return $matches[0];
}

/** Get queries adding values to an enum type
* @return ?list<string> null if the definitions differ by anything else than added values
*/
function add_enum_values(string $type, string $old, string $new): ?array {
	$old_values = enum_values($old);
	$new_values = enum_values($new);
	if ($old_values === null || $new_values === null) {
		return null;
	}
	$return = array();
	$i = 0; // number of original values matched so far
	foreach ($new_values as $value) {
		if ($value === idx($old_values, $i)) {
			$i++;
		} else {
			// ALTER TYPE ADD VALUE can't run inside a transaction before PostgreSQL 12, queries() doesn't start one
			$return[] = "ALTER TYPE " . idf_escape($type) . " ADD VALUE $value"
				. ($i < count($old_values) ? " BEFORE " . $old_values[$i] : "");
		}
	}
	return ($i == count($old_values) ? $return : null); // a value was renamed, removed or reordered
}

$TYPE = $_GET["type"];
$row = $_POST;
// types(true) is the list used for the links to this page in table structure
$type = ($TYPE != "" ? type_definition(+array_search($TYPE, types(true))) : array());
$object = ($type["kind"] == 'd' ? "DOMAIN" : "TYPE"); // domains are created, altered and dropped as DOMAIN

if ($_POST && !$error) {
	$link = substr(ME, 0, -1);
	$name = trim($row["name"]);
	$as = trim(str_replace("\r", "", $row["as"])); // the browser sends CRLF in <textarea>, type_definition() returns LF
	// CREATE TYPE accepts AS ENUM (...), AS RANGE (...), AS (...) and (INPUT = ...), anything else after AS is a base type of a domain
	$new_object = (preg_match('~^AS\s+(?!ENUM\b|RANGE\b|\()~i', $as) ? "DOMAIN" : "TYPE");
	$message = lang('Type has been altered.');
	// renaming and adding enum values are the only changes not requiring to re-create the type
	$alter = (!$_POST["drop"] && $TYPE != "" && $new_object == $object
		? ($as == $type["definition"] ? array() : add_enum_values($TYPE, $type["definition"], $as))
		: null
	);
	if ($alter !== null) {
		if ($TYPE != $name) {
			$alter[] = "ALTER $object " . idf_escape($TYPE) . " RENAME TO " . idf_escape($name); // after ADD VALUE which uses the original name
		}
		if (!$alter) {
			redirect($link);
		}
		$failed = false;
		foreach ($alter as $query) {
			if (!queries($query)) {
				$failed = true;
				break;
			}
		}
		queries_redirect($link, $message, !$failed);
	} else {
		// there's no CREATE OR REPLACE TYPE so the type is dropped and created again
		drop_create(
			"DROP $object " . idf_escape($TYPE),
			"CREATE $new_object " . idf_escape($name) . " $as",
			"", // only PostgreSQL supports types and its DDL is transactional
			"",
			"",
			$link,
			lang('Type has been dropped.'),
			$message,
			lang('Type has been created.'),
			$TYPE,
			$name
		);
	}
}

page_header($TYPE != "" ? lang('Alter type') . ": " . h($TYPE) : lang('Create type'), $error);

if (!$row) {
	$row["name"] = $TYPE;
	$row["as"] = ($TYPE != "" ? $type["definition"] : "AS ");
}
?>

<form action="" method="post">
<p>
<?php
echo lang('Name') . ": <input name='name' value='" . h($row['name']) . "' autocapitalize='off'>\n";
echo doc_link(array(
	'pgsql' => "sql-createtype.html",
), "?");
textarea("as", $row["as"]);
echo "<p><input type='submit' value='" . lang('Save') . "'>\n";
if ($TYPE != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm(lang('Drop %s?', $TYPE)) . ">\n";
}
echo input_token();
?>
</form>
