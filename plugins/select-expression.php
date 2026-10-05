<?php

/** Type the columns in select with suggestions, also expressions like a+b
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSelectExpression extends Adminer\Plugin {
	protected $functions;
	protected $lists = array(); // datalist index => columns
	protected $searching = false;

	/**
	* @param list<string> $functions lowercase names of functions allowed in expressions besides the ones offered by Adminer, without side effects (links can contain them)
	*/
	function __construct(array $functions = array(
		'abs', 'ceil', 'ceiling', 'floor', 'round', 'sign', 'sqrt', 'power', 'mod',
		'length', 'char_length', 'lower', 'upper', 'trim', 'ltrim', 'rtrim', 'substr', 'substring', 'concat',
		'coalesce', 'nullif', 'greatest', 'least',
		'date', 'year', 'month', 'day',
	)) {
		$this->functions = $functions;
	}

	function selectColumnInput($attrs, $columns, $value = "", $placeholder = "") {
		$id = array_search($columns, $this->lists, true); // Select, Search and Sort share the list if they have the same privileges
		if ($id === false) {
			$id = count($this->lists);
			$this->lists[] = $columns;
		}
		return "<input$attrs list='select-expression-$id' size='15' value='" . Adminer\h($value) . "' placeholder='$placeholder'>";
	}

	function selectActionPrint($indexes) {
		// not in the rows of the boxes which selectAddRow() clones
		foreach ($this->lists as $id => $columns) {
			echo "<datalist id='select-expression-$id'>" . Adminer\optionlist($columns, null, true) . "</datalist>\n";
		}
	}

	function selectColumnsProcess($columns, $indexes) {
		if (!$this->enabled()) {
			return null;
		}
		$grouping = Adminer\driver()->grouping;
		$select = array();
		$group = array();
		foreach ((array) $_GET["columns"] as $key => $val) {
			$fun = $val["fun"];
			$col = $val["col"];
			if ($fun == "count" || ($col != "" && (!$fun || in_array($fun, Adminer\driver()->functions) || in_array($fun, $grouping)))) {
				$expression = ($col == "" || isset($columns[$col]) ? null : $this->expression($col, $columns));
				$sql = Adminer\apply_sql_function($fun, ($col == "" ? "*" : ($expression ?: Adminer\idf_escape($col))));
				// the typed text is the name of the result column, a function is aliased by Adminer in PostgreSQL and MS SQL
				$select[$key] = $sql . ($expression && !$fun ? " AS " . Adminer\idf_escape($col) : "");
				if (!in_array($fun, $grouping)) {
					$group[] = $sql;
				}
			}
		}
		return array($select, $group);
	}

	function selectSearchProcess($fields, $indexes, $tableStatus = null) {
		if ($this->searching || !$this->enabled()) {
			return null;
		}
		$columns = array();
		foreach ($fields as $name => $field) {
			if (isset($field["privileges"]["where"])) {
				$columns[$name] = $name;
			}
		}
		$operators = Adminer\adminer()->operators($tableStatus);
		$where = (array) $_GET["where"];
		$conds = array();
		foreach ($where as $key => $val) {
			$col = $val["col"];
			$expression = ($col == "" || isset($fields[$col]) ? null : $this->expression($col, $columns));
			if ($expression) {
				unset($_GET["where"][$key]); // Adminer processes the other conditions
				$val += array("op" => Adminer\first($operators), "val" => "");
				$where[$key] = $val;
				if (in_array($val["op"], $operators)) {
					if ($val["op"] == "SQL" && (!$_POST || !Adminer\verify_token())) {
						Adminer\SqlDb::$untrusted = true; // the same as in Adminer
					}
					$conds[] = Adminer\adminer()->selectSearchCondition($expression, $val["op"], $val["val"]);
				}
			}
		}
		if (count($where) == count((array) $_GET["where"])) {
			return null;
		}
		$this->searching = true;
		$return = Adminer\adminer()->selectSearchProcess($fields, $indexes, $tableStatus);
		$this->searching = false;
		foreach ($_GET["where"] as $key => $val) {
			$where[$key] = $val; // Adminer fills the defaults
		}
		$_GET["where"] = $where; // in the original order, used also by selectSearchPrint() and by the CSRF check of the SQL operator
		return array_merge($return, $conds);
	}

	function selectOrderProcess($fields, $indexes) {
		if (!$this->enabled()) {
			return null;
		}
		$columns = array();
		foreach ($fields as $name => $field) {
			if (isset($field["privileges"]["order"])) {
				$columns[$name] = $name;
			}
		}
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				$function = preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~', $val); // the same as in Adminer
				$expression = (isset($fields[$val]) || $function ? null : $this->expression($val, $columns));
				$return[] = ($expression ?: ($function ? $val : Adminer\idf_escape($val)))
					. (isset($_GET["desc"][$key]) ? " DESC" . (Adminer\JUSH == 'pgsql' && Adminer\idx($fields[$val], "null") ? " NULLS LAST" : "") : "")
				;
			}
		}
		return $return;
	}

	/** Check whether the driver understands SQL expressions */
	protected function enabled() {
		return preg_match('~^(sql|pgsql|sqlite|mssql|oracle)$~', Adminer\JUSH);
	}

	/** Build SQL from an expression containing only columns, literals, operators and allowed functions
	* @param array<string, string> $columns allowed columns in keys
	* @return ?string null if the text contains anything else
	*/
	protected function expression($text, array $columns) {
		$quoted = (Adminer\JUSH == "sql" ? '`(?:[^`]|``)*`' : '"(?:[^"]|"")*"' . (Adminer\JUSH == "mssql" ? '|\[(?:[^]]|]])*]' : ''));
		// 1 - string, 2 - quoted identifier, 3 - number, 4 - word, 5 - operator
		$pattern = '~\G\s*+(?:(\'(?:[^\']|\'\')*\')|(' . $quoted . ')|([0-9]+(?:\.[0-9]+)?(?:[eE][-+]?[0-9]+)?)|([^\W\d]\w*)|(<=|>=|<>|!=|\|\||[-+*/%=<>(),]))~u';
		if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
			return null;
		}
		$length = 0;
		foreach ($matches as $match) {
			$length += strlen($match[0]);
		}
		if ($length != strlen(rtrim($text))) {
			return null; // an unknown character, e.g. ; # @ $ : . ?
		}
		$keywords = array('and', 'or', 'not', 'is', 'null', 'true', 'false', 'like', 'in', 'between', 'case', 'when', 'then', 'else', 'end', 'distinct');
		$functions = array_merge($this->functions, Adminer\driver()->functions, Adminer\driver()->grouping);
		$return = "";
		$depth = 0;
		$glue = false; // whether the previous token is followed by a space
		foreach ($matches as $i => $match) {
			$type = count($match) - 1; // the last matched group
			$token = $match[$type];
			$sql = $token;
			$space = true;
			if ($type == 1) {
				$sql = Adminer\q(str_replace("''", "'", substr($token, 1, -1)));
			} elseif ($type == 2) {
				$name = Adminer\idf_unescape($token);
				if (!isset($columns[$name])) {
					return null;
				}
				$sql = Adminer\idf_escape($name);
			} elseif ($type == 4) {
				$lower = strtolower($token);
				if (in_array($lower, $keywords)) {
					$sql = strtoupper($token);
				} elseif (isset($matches[$i + 1][5]) && $matches[$i + 1][5] == "(") {
					if (!in_array($lower, $functions)) {
						return null;
					}
					$sql = strtoupper($token);
					$space = false; // MySQL doesn't accept a space before the parenthesis of a function
				} elseif (isset($columns[$token])) {
					$sql = Adminer\idf_escape($token);
				} else {
					return null;
				}
			} elseif ($token == "(") {
				$depth++;
				$space = false;
			} elseif ($token == ")") {
				$depth--;
				if ($depth < 0) {
					return null; // the expression must not close the parentheses around it
				}
			}
			// two operators are always separated by a space so that they can't form a comment like -- or /*
			$return .= ($glue && $token != ")" && $token != "," ? " " : "") . $sql;
			$glue = $space;
		}
		return ($depth ? null : "($return)");
	}

	protected $translations = array(
		'cs' => array('' => 'Umožní psát sloupce ve výpisu s výběrem, také výrazy jako a+b'),
		'de' => array('' => 'Ermöglicht das Eintippen der Spalten im Select mit Vorschlägen, auch Ausdrücke wie a+b'), // Claude Opus 5.5
		'ja' => array('' => '一覧で列を候補付きで入力可能にし、a+b のような式も使用可能'), // Claude Opus 5.5
		'pl' => array('' => 'Umożliwia wpisywanie kolumn w wyniku z podpowiedziami, także wyrażeń jak a+b'), // Claude Opus 5.5
		'ro' => array('' => 'Permite tastarea coloanelor în select cu sugestii, inclusiv expresii precum a+b'), // Claude Opus 5.5
		'sk' => array('' => 'Umožní písať stĺpce vo výpise s výberom, aj výrazy ako a+b'), // Claude Opus 5.5
		'zh' => array('' => '在选择数据时输入列并提供建议，也支持 a+b 这样的表达式'), // Claude Opus 5.5
	);
}
