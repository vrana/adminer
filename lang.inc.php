<?php
function lang($idf = null) {
	static $translations = array(
		'en' => array(),
		'cs' => array(
			'Usage: php _compile.php [lang]' => 'Použití: php _compile.php [jazyk]',
			'Purpose: Compile phpMinAdmin[-lang].php from index.php.' => 'Účel: Zkompilovat phpMinAdmin[-jazyk].php z index.php.',
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
			'Auto-increment' => 'Auto-increment',
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
			'Language' => 'Jazyk',
			'Select' => 'Vypsat',
			'New item' => 'Nová položka',
			'Search' => 'Vyhledat',
			'No rows.' => 'Žádné řádky.',
			'Action' => 'Akce',
			'edit' => 'upravit',
			'Page' => 'Stránka',
			'Query executed OK, %d row(s) affected.' => 'Příkaz proběhl v pořádku, bylo změněno %d záznam(ů).',
			'Error in query' => 'Chyba v dotazu',
			'Execute' => 'Provést',
			'Table' => 'Tabulka',
			'Foreign keys' => 'Cizí klíče',
			'Triggers' => 'Spouště',
			'View' => 'Pohled',
		),
	);
	if (!isset($idf)) {
		return array_keys($translations);
	}
	if (strlen($_SESSION["lang"])) {
		$lang = $_SESSION["lang"];
	} else {
		$lang = preg_replace('~[,;].*~', '', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
		if (!isset($translations[$lang])) { //! try next languages
			$lang = preg_replace('~-.*~', '', $lang);
			if (!isset($translations[$lang])) {
				$lang = "en";
			}
		}
	}
	return (strlen($translations[$lang][$idf]) ? $translations[$lang][$idf] : $idf);
}

function switch_lang() {
	echo "<p>" . lang('Language') . ":";
	$base = preg_replace('~(\\?)lang=[^&]*&|[&?]lang=[^&]*~', '', $_SERVER["REQUEST_URI"]);
	foreach (lang() as $lang) {
		echo ' <a href="' . htmlspecialchars($base . (strpos($base, "?") !== false ? "&" : "?")) . "lang=$lang\">$lang</a>";
	}
	echo "</p>\n";
}

if (isset($_GET["lang"])) {
	$_SESSION["lang"] = $_GET["lang"];
}
