<?php
function lang($idf = null, $number = null) {
	global $LANG;
	static $translations = array(
		'en' => array(
			'Query executed OK, %d row(s) affected.' => array('Query executed OK, %d row affected.', 'Query executed OK, %d rows affected.'),
			'%d byte(s)' => array('%d byte', '%d bytes'),
			'Routine has been called, %d row(s) affected.' => array('Routine has been called, %d row affected.', 'Routine has been called, %d rows affected.'),
		),
		'cs' => array(
			'Login' => 'Přihlásit se',
			'phpMinAdmin' => 'phpMinAdmin',
			'Logout successful.' => 'Odhlášení proběhlo v pořádku.',
			'Invalid credentials.' => 'Neplatné přihlašovací údaje.',
			'Server' => 'Server',
			'Username' => 'Uživatel',
			'Password' => 'Heslo',
			'Select database' => 'Vybrat databázi',
			'Invalid database.' => 'Nesprávná databáze.',
			'Create new database' => 'Vytvořit novou databázi',
			'Table has been dropped.' => 'Tabulka byla odstraněna.',
			'Table has been altered.' => 'Tabulka byla změněna.',
			'Table has been created.' => 'Tabulka byla vytvořena.',
			'Alter table' => 'Změnit tabulku',
			'Create table' => 'Vytvořit tabulku',
			'Unable to operate table' => 'Nepodařilo se zpracovat tabulku',
			'Table name' => 'Název tabulky',
			'engine' => 'typ tabulky',
			'collation' => 'porovnávání',
			'Name' => 'Název',
			'Type' => 'Typ',
			'Length' => 'Délka',
			'NULL' => 'NULL',
			'Auto Increment' => 'Auto Increment',
			'Options' => 'Volby',
			'Add row' => 'Přidat řádek',
			'Save' => 'Uložit',
			'Drop' => 'Odstranit',
			'Database has been dropped.' => 'Databáze byla odstraněna.',
			'Database has been created.' => 'Databáze byla vytvořena.',
			'Database has been renamed.' => 'Databáze byla přejmenována.',
			'Database has been altered.' => 'Databáze byla změněna.',
			'Alter database' => 'Změnit databázi',
			'Create database' => 'Vytvořit databázi',
			'Unable to operate database' => 'Nepodařilo se zpracovat databázi',
			'SQL command' => 'SQL příkaz',
			'Dump' => 'Export',
			'Logout' => 'Odhlásit',
			'database' => 'databáze',
			'Use' => 'Vybrat',
			'No tables.' => 'Žádné tabulky.',
			'select' => 'vypsat',
			'Create new table' => 'Vytvořit novou tabulku',
			'Item has been deleted.' => 'Položka byla smazána.',
			'Item has been updated.' => 'Položka byla aktualizována.',
			'Item has been inserted.' => 'Položka byla vložena.',
			'Edit' => 'Upravit',
			'Insert' => 'Vložit',
			'Error during saving' => 'Chyba při ukládání',
			'Save and insert' => 'Uložit a vložit',
			'Delete' => 'Smazat',
			'Database' => 'Databáze',
			'Routines' => 'Procedury',
			'Indexes has been altered.' => 'Indexy byly změněny.',
			'Indexes' => 'Indexy',
			'Unable to operate indexes' => 'Nepodařilo se zpracovat indexy',
			'Alter indexes' => 'Změnit indexy',
			'Add next' => 'Přidat další',
			'Language' => 'Jazyk',
			'Select' => 'Vypsat',
			'New item' => 'Nová položka',
			'Search' => 'Vyhledat',
			'Sort' => 'Setřídit',
			'DESC' => 'sestupně',
			'Limit' => 'Limit',
			'No rows.' => 'Žádné řádky.',
			'Action' => 'Akce',
			'edit' => 'upravit',
			'Page' => 'Stránka',
			'Query executed OK, %d row(s) affected.' => array('Příkaz proběhl v pořádku, byl změněn %d záznam.', 'Příkaz proběhl v pořádku, byly změněny %d záznamy.', 'Příkaz proběhl v pořádku, bylo změněno %d záznamů.'),
			'Error in query' => 'Chyba v dotazu',
			'Execute' => 'Provést',
			'Table' => 'Tabulka',
			'Foreign keys' => 'Cizí klíče',
			'Triggers' => 'Spouště',
			'View' => 'Pohled',
			'Unable to select the table' => 'Nepodařilo se vypsat tabulku',
			'Unable to show the table definition' => 'Nepodařilo se získat strukturu tabulky',
			'Invalid CSRF token. Send the form again.' => 'Neplatný token CSRF. Odešlete formulář znovu.',
			'Comment' => 'Komentář',
			'Default values has been set.' => 'Výchozí hodnoty byly nastaveny.',
			'Default values' => 'Výchozí hodnoty',
			'BOOL' => 'BOOL',
			'Show column comments' => 'Zobrazit komentáře sloupců',
			'%d byte(s)' => array('%d bajt', '%d bajty', '%d bajtů'),
			'No commands to execute.' => 'Žádné příkazy k vykonání.',
			'Unable to upload a file.' => 'Nepodařilo se nahrát soubor.',
			'File upload' => 'Nahrání souboru',
			'File uploads are disabled.' => 'Nahrávání souborů není povoleno.',
			'Routine has been called, %d row(s) affected.' => array('Procedura byla zavolána, byl změněn %d záznam.', 'Procedura byla zavolána, byly změněny %d záznamy.', 'Procedura byla zavolána, bylo změněno %d záznamů.'),
			'Call' => 'Zavolat',
			'Error during calling' => 'Chyba při volání',
			'No MySQL extension' => 'Žádná MySQL extenze',
			'None of supported PHP extensions (%s) are available.' => 'Není dostupná žádná z podporovaných PHP extenzí (%s).',
			'Sessions must be enabled.' => 'Session proměnné musí být povolené.',
			'Session expired, please login again.' => 'Session vypršela, přihlašte se prosím znovu.',
			'Text length' => 'Délka textů',
			'Syntax highlighting' => 'Zvýrazňování syntaxe',
		),
	);
	if (!isset($idf)) {
		return array_keys($translations);
	}
	$translation = $translations[$LANG][$idf];
	if ($number === false) { // used in _compile.php
		return ($translation ? $translation : $idf);
	}
	$args = func_get_args();
	if (is_array($translation) && $translation) {
		switch ($LANG) {
			case 'cs': $pos = ($number == 1 ? 0 : (!$number || $number >= 5 ? 2 : 1)); break;
			default: $pos = ($number == 1 ? 0 : 1);
		}
		$translation = $translation[$pos];
	}
	if ($translation) {
		$args[0] = $translation;
	}
	return call_user_func_array('sprintf', $args);
}

function switch_lang() {
	echo "<p>" . lang('Language') . ":";
	$base = preg_replace('~(\\?)lang=[^&]*&|[&?]lang=[^&]*~', '\\1', $_SERVER["REQUEST_URI"]);
	foreach (lang() as $lang) {
		echo ' <a href="' . htmlspecialchars($base . (strpos($base, "?") !== false ? "&" : "?")) . "lang=$lang\">$lang</a>";
	}
	echo "</p>\n";
}

if (isset($_GET["lang"])) {
	$_SESSION["lang"] = $_GET["lang"];
}

if (strlen($_SESSION["lang"])) {
	$LANG = $_SESSION["lang"];
} else {
	$langs = lang();
	$LANG = preg_replace('~[,;].*~', '', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
	if (!in_array($LANG, $langs)) { //! try next languages
		$LANG = preg_replace('~-.*~', '', $LANG);
		if (!in_array($LANG, $langs)) {
			$LANG = "en";
		}
	}
}
