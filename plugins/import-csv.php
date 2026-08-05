<?php

/** Create a table from an imported CSV file
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerImportCsv extends Adminer\Plugin {

	function importPrint() {
		if (Adminer\DB == "" || !preg_match('~^(sql|pgsql|sqlite)$~', Adminer\JUSH)) { // the other drivers would need their type names in _type()
			return;
		}
		$gz = (extension_loaded("zlib") ? "[.gz]" : "");
		echo "<fieldset><legend>CSV</legend><div>";
		echo "CSV$gz, TAR$gz: " . Adminer\file_input(" name='csv_file[]' multiple", " " . $this->lang('If the table exists') . ": "
			. Adminer\html_select("csv_exists", array("insert" => $this->lang('Insert'), "truncate" => $this->lang('Truncate'), "drop" => $this->lang('Drop')), $_POST["csv_exists"])
			. " <input type='submit' name='csv' value='" . $this->lang('Import') . "'>")
		;
		echo "</div></fieldset>\n";
	}

	function importProcess() {
		if (!$_POST["csv"]) {
			return null; // import the sent files as SQL commands
		}
		$files = Adminer\get_files("csv_file", true);
		if (!is_array($files)) {
			echo "<p class='error'>" . Adminer\upload_error($files) . "\n";
			return true;
		}
		foreach ($files as $file) {
			foreach ($this->_untar($file[0], $file[1]) as $csv) {
				if (!$this->_import($csv[0], $csv[1], $_POST["csv_exists"]) && $_POST["error_stops"]) {
					break 2;
				}
			}
		}
		return true;
	}

	/** Get files stored in a TAR archive, e.g. an export of several tables to CSV
	* @param string $name name of the file
	* @param string $tar contents of the file
	* @return list<array{string, string}> the file itself if it is not an archive
	*/
	protected function _untar($name, $tar) {
		if (!preg_match('~\.tar(\.gz)?$~i', $name)) {
			return array(array($name, $tar));
		}
		$return = array();
		$offset = 0;
		while ($offset + 512 <= strlen($tar)) { // the header of each file is 512 bytes, see tar_file()
			$filename = rtrim(substr($tar, $offset, 100), "\0");
			if ($filename == "") {
				break; // the archive ends with zero blocks
			}
			$size = octdec(trim(substr($tar, $offset + 124, 12), "\0 "));
			$offset += 512;
			if (substr($filename, -1) != "/") { // directories have no data
				$return[] = array($filename, substr($tar, $offset, $size));
			}
			$offset += (int) ceil($size / 512) * 512; // the data is padded to whole blocks
		}
		return $return;
	}

	/** Create a table named after the file and import the data to it, print the result
	* @param string $name name of the file
	* @param string $csv contents of the file
	* @param string $exists what to do if the table exists: insert, truncate or drop
	* @return bool
	*/
	protected function _import($name, $csv, $exists) {
		$error_prefix = "<p class='error'>" . Adminer\h($name) . ": ";
		if (substr($csv, 0, 3) == "\xEF\xBB\xBF") {
			$csv = substr($csv, 3); // UTF-8 BOM, get_files() removes it only from the uploaded file, not from the files in an archive
		}
		if (!Adminer\is_utf8($csv)) {
			echo $error_prefix . $this->lang('File must be in UTF-8 encoding.') . "\n";
			return false;
		}
		if (($memory_limit = Adminer\ini_bytes("memory_limit")) != "-1") {
			Adminer\ini_set("memory_limit", max($memory_limit, strval(10 * strlen($csv) + memory_get_usage() + 8e6))); // 10 - the parsed data take more memory than the file
		}
		$rows = Adminer\parse_csv($csv, $this->_separator($csv));
		$header = array_map('Adminer\csv_value', (array) array_shift($rows));
		while ($header && end($header) === "") {
			array_pop($header); // the first row ends with the separator
		}
		$message_prefix = "<p class='message'>" . Adminer\h($name) . ": ";
		if (!$header) {
			echo $message_prefix . $this->lang('%d row(s) have been imported.', 0) . "\n";
			return true;
		}

		$table = preg_replace('~\.csv(\.gz)?$~i', '', basename($name));
		$tables = Adminer\tables_list();
		$fields = array();
		if (isset($tables[$table])) {
			if ($exists == "drop") {
				if (!Adminer\drop_tables(array($table))) {
					echo $error_prefix . Adminer\error() . "\n";
					return false;
				}
			} else {
				$fields = Adminer\fields($table);
				foreach ($header as $column) {
					if (!$fields[$column]) {
						echo $error_prefix . $this->lang('Column %s does not exist.', "<code>" . Adminer\h($column) . "</code>") . "\n";
						return false;
					}
				}
				if ($exists == "truncate" && !Adminer\truncate_tables(array($table))) {
					echo $error_prefix . Adminer\error() . "\n";
					return false;
				}
			}
		}

		$created = !$fields;
		if ($created) {
			$fields = $this->_fields($header, $rows);
			$create = array();
			$primary = array();
			foreach ($fields as $field) {
				$create[] = array("", Adminer\process_field($field, $field), "");
				if ($field["primary"]) {
					$primary[] = "  PRIMARY KEY (" . Adminer\idf_escape($field["field"]) . ")"; // the same as in recreate_table() in the SQLite driver
				}
			}
			$start = count(Adminer\Queries::$queries);
			if (!Adminer\alter_table("", $table, $create, $primary, null, "", "", "", array())) {
				echo $error_prefix . Adminer\error() . "\n";
				return false;
			}
			if (!$_POST["only_errors"]) {
				echo "<pre><code class='jush-" . Adminer\JUSH . "'>" . Adminer\adminer()->sqlCommandQuery(implode("\n", array_slice(Adminer\Queries::$queries, $start))) . "</code></pre>\n";
			}
		}

		$columns = array();
		foreach ($header as $column) {
			$columns[] = Adminer\idf_escape($column);
		}
		$insert = "INSERT INTO " . Adminer\table($table) . " (" . implode(", ", $columns) . ") VALUES\n";
		Adminer\driver()->begin();
		$affected = 0;
		$failed = false;
		$values = array();
		$length = 0;
		foreach ($rows as $row) {
			$set = array();
			foreach ($header as $key => $column) {
				$val = (string) $row[$key]; // a shorter row leaves the remaining values empty
				$set[] = ($val == "" && $fields[$column]["null"] ? "NULL" : Adminer\q(Adminer\csv_value($val)));
			}
			$value = "(" . implode(", ", $set) . ")";
			if ($values && strlen($insert) + $length + strlen($value) > 1e6) { // 1e6 - default max_allowed_packet
				$failed = !Adminer\connection()->query($insert . implode(",\n", $values));
				if ($failed) {
					break;
				}
				$values = array();
				$length = 0;
			}
			$values[] = $value;
			$length += strlen($value) + 2;
			$affected++;
		}
		if (!$failed && $values) {
			$failed = !Adminer\connection()->query($insert . implode(",\n", $values));
		}
		if ($failed) {
			echo $error_prefix . Adminer\error() . "\n";
			Adminer\driver()->rollback();
			return false;
		}
		Adminer\driver()->commit();
		echo $message_prefix . ($created ? $this->lang('Table has been created.') . " " : "") . $this->lang('%d row(s) have been imported.', $affected) . "\n";
		return true;
	}

	/** Autodetect separator of CSV data by its first row
	* @param string $csv
	* @return string
	*/
	protected function _separator($csv) {
		preg_match('~(?>"[^"]*"|[^"\r\n]+)+~', $csv, $match); // the first row, the same expression as in parse_csv()
		$return = ",";
		$max = 0;
		foreach (array(",", ";", "\t") as $separator) {
			$rows = Adminer\parse_csv((string) $match[0], $separator);
			$count = count((array) $rows[0]);
			if ($count > $max) { // the separator splitting the row to most values wins
				$max = $count;
				$return = $separator;
			}
		}
		return $return;
	}

	/** Get fields of a table created from CSV data
	* @param list<string> $header column names
	* @param list<list<string>> $rows values as they are in the file
	* @return Adminer\Field[] [$name => Field]
	*/
	protected function _fields($header, $rows) {
		$return = array();
		foreach ($header as $key => $name) {
			$type = "";
			$chars = 0; // maximal number of characters
			$integers = 0; // maximal number of digits before the decimal point
			$decimals = 0; // maximal number of digits after the decimal point
			$big = false;
			$null = false;
			$unique = array(); // values of the first column
			foreach ($rows as $row) {
				$val = (string) $row[$key];
				if ($val == "") { // an empty value is NULL, an empty string is "" in the file
					$null = true;
					continue;
				}
				$val = Adminer\csv_value($val);
				if (!$key) {
					$unique[$val] = true;
				}
				$val_type = $this->_valueType($val);
				$type = $this->_commonType($type, $val_type);
				$chars = max($chars, $this->_strlen($val));
				if ($val_type == "int") {
					$big = $big || $val > 2147483647 || $val < -2147483648;
					$integers = max($integers, strlen(ltrim($val, "-")));
				} elseif ($val_type == "decimal") {
					preg_match('~^-?([0-9]+)\.([0-9]+)$~', $val, $match);
					$integers = max($integers, strlen($match[1]));
					$decimals = max($decimals, strlen($match[2]));
				}
			}
			$length = "";
			if ($type == "") { // no data
				$type = "varchar";
				$chars = 200;
			}
			if ($type == "int" && $big) {
				$type = "bigint";
			}
			if ($type == "decimal") {
				if ($integers + $decimals > 65) {
					$type = "float"; // decimal in MySQL is limited to 65 digits
				} else {
					$length = ($integers + $decimals) . ",$decimals";
				}
			}
			if ($type == "varchar") {
				foreach (array(20, 50, 100, 200) as $length) {
					if ($chars <= $length) {
						break;
					}
				}
			}
			$return[$name] = array(
				"field" => $name,
				"type" => $this->_type($type),
				"length" => (Adminer\JUSH == "sqlite" ? "" : strval($length)), // SQLite types have no length
				"unsigned" => "",
				"null" => $null && !preg_match('~varchar|text~', $type), // an empty string is a valid value of a string column
				"auto_increment" => false,
				"collation" => "",
				"privileges" => array(),
				"comment" => "",
				// the first column identifies the rows if it has no empty and no repeated value; text is not usable as a key in MySQL
				"primary" => !$key && $rows && count($unique) == count($rows) && $type != "text",
				"generated" => "",
				"on_update" => "",
				"full_type" => "",
			);
		}
		return $return;
	}

	/** Get type of a single value in CSV data
	* @param string $val
	* @return string int, decimal, float, date, time, datetime, varchar or text
	*/
	protected function _valueType($val) {
		$number = '-?([1-9][0-9]*|0)'; // leading zeros are significant, e.g. in ZIP codes
		if (preg_match("~^$number\$~", $val)) {
			return "int";
		}
		if (preg_match("~^$number\\.[0-9]{1,3}\$~", $val)) {
			return "decimal";
		}
		if (preg_match("~^$number(\\.[0-9]+)?([eE][-+]?[0-9]+)?\$~", $val)) { // more than three decimal places or exponent
			return "float";
		}
		if (preg_match('~^[0-9]{4}-[0-9]{2}-[0-9]{2}$~', $val)) {
			return "date";
		}
		if (preg_match('~^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$~', $val)) {
			return "time";
		}
		if (preg_match('~^[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}(:[0-9]{2})?(\.[0-9]+)?$~', $val)) { // time zone is not accepted, it would be lost
			return "datetime";
		}
		return (strpos($val, "\n") !== false || $this->_strlen($val) > 200 ? "text" : "varchar");
	}

	/** Get type usable for values of two types
	* @param string $type1
	* @param string $type2
	* @return string
	*/
	protected function _commonType($type1, $type2) {
		if ($type1 == "" || $type1 == $type2) {
			return $type2;
		}
		$numbers = array("int" => 1, "decimal" => 2, "float" => 3);
		if ($numbers[$type1] && $numbers[$type2]) {
			return ($numbers[$type1] > $numbers[$type2] ? $type1 : $type2);
		}
		return ($type1 == "text" || $type2 == "text" ? "text" : "varchar"); // e.g. a date among numbers
	}

	/** Get name of a type detected in CSV data in the current driver
	* @param string $type
	* @return string
	*/
	protected function _type($type) {
		if (Adminer\JUSH == "sqlite") {
			if (preg_match('~int~', $type)) {
				return "integer";
			}
			return ($type == "float" ? "real" : ($type == "decimal" ? "numeric" : "text"));
		}
		if (Adminer\JUSH == "pgsql") {
			$types = array("int" => "integer", "decimal" => "numeric", "float" => "double precision", "datetime" => "timestamp", "varchar" => "character varying");
			return ($types[$type] ?: $type);
		}
		$types = array("float" => "double", "text" => "mediumtext"); // MySQL
		return ($types[$type] ?: $type);
	}

	/** Get length of a string in characters, the string must be in UTF-8
	* @param string $val
	* @return int
	*/
	protected function _strlen($val) {
		return strlen(preg_replace('~[\x80-\xBF]~', '', $val)); // continuation bytes don't start a character
	}

	protected $translations = array(
		'en' => array(
			'%d row(s) have been imported.' => array('%d row has been imported.', '%d rows have been imported.'),
		),
		'cs' => array(
			'' => 'Vytvoření tabulky z nahraného CSV souboru',
			'If the table exists' => 'Pokud tabulka existuje',
			'Insert' => 'Vložit',
			'Truncate' => 'Vyprázdnit',
			'Drop' => 'Odstranit',
			'Import' => 'Import',
			'File must be in UTF-8 encoding.' => 'Soubor musí být v kódování UTF-8.',
			'Column %s does not exist.' => 'Sloupec %s neexistuje.',
			'Table has been created.' => 'Tabulka byla vytvořena.',
			'%d row(s) have been imported.' => array('Byl importován %d záznam.', 'Byly importovány %d záznamy.', 'Bylo importováno %d záznamů.'),
		),
	);
}
