<?php
namespace Adminer;

Lang::$translations = array(
	'System' => 'Sistem', // label for database system selection (MySQL, SQLite, ...)
	'Server' => 'Strežnik',
	'Username' => 'Uporabniško ime',
	'Password' => 'Geslo',
	'Permanent login' => 'Trajna prijava',
	'Login' => 'Prijavi se',
	'Logout' => 'Odjavi se',
	'Logged as: %s' => 'Prijavljen kot: %s',
	'Logout successful.' => 'Prijava uspešna.',
	'Invalid credentials.' => 'Neveljavne pravice.',
	'Language' => 'Jezik',
	'Invalid CSRF token. Send the form again.' => 'Neveljaven token CSRF. Pošljite formular še enkrat.',
	'No extension' => 'Brez dodatkov',
	'None of the supported PHP extensions (%s) are available.' => 'Noben od podprtih dodatkov za PHP (%s) ni na voljo.',
	'Session support must be enabled.' => 'Podpora za seje mora biti omogočena.',
	'Session expired, please login again.' => 'Seja je potekla. Prosimo, ponovno se prijavite.',
	'%s version: %s through PHP extension %s' => 'Verzija %s: %s preko dodatka za PHP %s',
	'Refresh' => 'Osveži',

	'ltr' => 'ltr', // text direction

	'Privileges' => 'Pravice',
	'Create user' => 'Ustvari uporabnika',
	'User has been dropped.' => 'Uporabnik je odstranjen.',
	'User has been altered.' => 'Uporabnik je spremenjen.',
	'User has been created.' => 'Uporabnik je ustvarjen.',
	'Hashed' => 'Zakodirano',
	'Column' => 'Stolpec',
	'Routine' => 'Postopek',
	'Grant' => 'Dovoli',
	'Revoke' => 'Odvzemi',

	'Process list' => 'Seznam procesov',
	'%d process(es) have been killed.' => array('Končan je %d proces.', 'Končana sta %d procesa.', 'Končani so %d procesi.', 'Končanih je %d procesov.'),
	'Kill' => 'Končaj',

	'Variables' => 'Spremenljivke',
	'Status' => 'Stanje',

	'SQL command' => 'Ukaz SQL',
	'%d query(s) executed OK.' => array('Uspešno se je končala %d poizvedba.', 'Uspešno sta se končali %d poizvedbi.', 'Uspešno so se končale %d poizvedbe.', 'Uspešno se je končalo %d poizvedb.'),
	'Query executed OK, %d row(s) affected.' => array('Poizvedba se je uspešno izvedla, spremenjena je %d vrstica.', 'Poizvedba se je uspešno izvedla, spremenjeni sta %d vrstici.', 'Poizvedba se je uspešno izvedla, spremenjene so %d vrstice.', 'Poizvedba se je uspešno izvedla, spremenjenih je %d vrstic.'),
	'No commands to execute.' => 'Ni ukazov za izvedbo.',
	'Error in query' => 'Napaka v poizvedbi',
	'Execute' => 'Izvedi',
	'Stop on error' => 'Ustavi ob napaki',
	'Show only errors' => 'Pokaži samo napake',
	'%.3f s' => '%.3f s', // sprintf() format for time of the command
	'History' => 'Zgodovina',
	'Clear' => 'Počisti',

	'File upload' => 'Naloži datoteko',
	'From server' => 'z strežnika',
	'Webserver file %s' => 'Datoteka na spletnem strežniku %s',
	'Run file' => 'Zaženi datoteko',
	'File does not exist.' => 'Datoteka ne obstaja.',
	'File uploads are disabled.' => 'Nalaganje datotek je onemogočeno.',
	'Unable to upload a file.' => 'Ne morem naložiti datoteke.',
	'Maximum allowed file size is %sB.' => 'Največja velikost datoteke je %sB.',
	'Too big POST data. Reduce the data or increase the %s configuration directive.' => 'Preveliko podatkov za POST. Zmanjšajte število podatkov ali povečajte nastavitev za %s.',

	'Export' => 'Izvozi',
	'Output' => 'Izhod rezultata',
	'open' => 'odpri',
	'save' => 'shrani',
	'Format' => 'Format',
	'Data' => 'Podatki',

	'Database' => 'Baza',
	'Use' => 'Uporabi',
	'Select database' => 'Izberi bazo',
	'Invalid database.' => 'Neveljavna baza.',
	'Database has been dropped.' => 'Baza je zavržena.',
	'Databases have been dropped.' => 'Baze so zavržene.',
	'Database has been created.' => 'Baza je ustvarjena.',
	'Database has been renamed.' => 'Baza je preimenovana.',
	'Database has been altered.' => 'Baza je spremenjena.',
	'Alter database' => 'Spremeni bazo',
	'Create database' => 'Ustvari bazo',
	'Database schema' => 'Shema baze',

	',' => ' ', // thousands separator - must contain single byte
	'0123456789' => '0123456789',
	'Engine' => 'Pogon',
	'Collation' => 'Zbiranje',
	'Data Length' => 'Velikost podatkov',
	'Index Length' => 'Velikost indeksa',
	'Data Free' => 'Podatkov prosto ',
	'Rows' => 'Vrstic',
	'%d in total' => 'Skupaj %d',
	'Analyze' => 'Analiziraj',
	'Optimize' => 'Optimiziraj',
	'Check' => 'Preveri',
	'Repair' => 'Popravi',
	'Truncate' => 'Skrajšaj',
	'Tables have been truncated.' => 'Tabele so skrajšane.',
	'Move to other database' => 'Premakni v drugo bazo',
	'Move' => 'Premakni',
	'Tables have been moved.' => 'Tabele so premaknjene.',
	'Copy' => 'Kopiraj',
	'Tables have been copied.' => 'Tabele so kopirane.',

	'Routines' => 'Postopki',
	'Routine has been called, %d row(s) affected.' => array('Klican je bil postopek, spremenjena je %d vrstica.', 'Klican je bil postopek, spremenjeni sta %d vrstici.', 'Klican je bil postopek, spremenjene so %d vrstice.', 'Klican je bil postopek, spremenjenih je %d vrstic.'),
	'Call' => 'Pokliči',
	'Parameter name' => 'Ime parametra',
	'Create procedure' => 'Ustvari postopek',
	'Create function' => 'Ustvari funkcijo',
	'Routine has been dropped.' => 'Postopek je zavržen.',
	'Routine has been altered.' => 'Postopek je spremenjen.',
	'Routine has been created.' => 'Postopek je ustvarjen.',
	'Alter function' => 'Spremeni funkcijo',
	'Alter procedure' => 'Spremeni postopek',
	'Return type' => 'Vračalni tip',

	'Events' => 'Dogodki',
	'Event has been dropped.' => 'Dogodek je zavržen.',
	'Event has been altered.' => 'Dogodek je spremenjen.',
	'Event has been created.' => 'Dogodek je ustvarjen.',
	'Alter event' => 'Spremeni dogodek',
	'Create event' => 'Ustvari dogodek',
	'At given time' => 'v danem času',
	'Every' => 'vsake',
	'Schedule' => 'Urnik',
	'Start' => 'Začetek',
	'End' => 'Konec',
	'On completion preserve' => 'Po zaključku ohrani',

	'Tables' => 'Tabele',
	'Tables and views' => 'Tabele in pogledi',
	'Table' => 'Tabela',
	'No tables.' => 'Ni tabel.',
	'Alter table' => 'Spremeni tabelo',
	'Create table' => 'Ustvari tabelo',
	'Table has been dropped.' => 'Tabela je zavržena.',
	'Tables have been dropped.' => 'Tabele so zavržene.',
	'Table has been altered.' => 'Tabela je spremenjena.',
	'Table has been created.' => 'Tabela je ustvarjena.',
	'Table name' => 'Ime tabele',
	'Show structure' => 'Pokaži zgradbo',
	'engine' => 'pogon',
	'collation' => 'zbiranje',
	'Column name' => 'Ime stolpca',
	'Type' => 'Tip',
	'Length' => 'Dolžina',
	'Auto Increment' => 'Samodejno povečevanje',
	'Options' => 'Možnosti',
	'Comment' => 'Komentar',
	'Default values' => 'Privzete vrednosti',
	'Drop' => 'Zavrzi',
	'Are you sure?' => 'Ste prepričani?',
	'Remove' => 'Odstrani',
	'Maximum number of allowed fields exceeded. Please increase %s.' => 'Največje število dovoljenih polje je preseženo. Prosimo, povečajte %s.',

	'Partition by' => 'Porazdeli po',
	'Partitions' => 'Porazdelitve',
	'Partition name' => 'Ime porazdelitve',
	'Values' => 'Vrednosti',

	'View' => 'Pogledi',
	'View has been dropped.' => 'Pogled je zavržen.',
	'View has been altered.' => 'Pogled je spremenjen.',
	'View has been created.' => 'Pogled je ustvarjen.',
	'Alter view' => 'Spremeni pogled',
	'Create view' => 'Ustvari pogled',

	'Indexes' => 'Indeksi',
	'Indexes have been altered.' => 'Indeksi so spremenjeni.',
	'Alter indexes' => 'Spremeni indekse',
	'Add next' => 'Dodaj naslednjega',
	'Index Type' => 'Tip indeksa',
	'length' => 'dolžina',
	'operator class' => 'razred operatorjev', // Claude Fable 5

	'Foreign keys' => 'Tuji ključi',
	'Foreign key' => 'Tuj ključ',
	'Foreign key has been dropped.' => 'Tuj ključ je zavržen.',
	'Foreign key has been altered.' => 'Tuj ključ je spremenjen.',
	'Foreign key has been created.' => 'Tuj ključ je ustvarjen.',
	'Target table' => 'Ciljna tabela',
	'Change' => 'Spremeni',
	'Source' => 'Izvor',
	'Target' => 'Cilj',
	'Add column' => 'Dodaj stolpec',
	'Alter' => 'Spremeni',
	'Add foreign key' => 'Dodaj tuj ključ',
	'ON DELETE' => 'pri brisanju',
	'ON UPDATE' => 'pri posodabljanju',
	'Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.' => 'Izvorni in ciljni stolpec mora imeti isti podatkovni tip. Obstajati mora indeks na ciljnih stolpcih in obstajati morajo referenčni podatki.',

	'Triggers' => 'Sprožilniki',
	'Add trigger' => 'Dodaj sprožilnik',
	'Trigger has been dropped.' => 'Sprožilnik je odstranjen.',
	'Trigger has been altered.' => 'Sprožilnik je spremenjen.',
	'Trigger has been created.' => 'Sprožilnik je ustvarjen.',
	'Alter trigger' => 'Spremeni sprožilnik',
	'Create trigger' => 'Ustvari sprožilnik',
	'Time' => 'Čas',
	'Event' => 'Dogodek',
	'Name' => 'Naziv',

	'select' => 'izberi',
	'Select' => 'Izberi',
	'Select data' => 'Izberi podatke',
	'Functions' => 'Funkcije',
	'Aggregation' => 'Združitev',
	'Search' => 'Išči',
	'anywhere' => 'kjerkoli',
	'Search data in tables' => 'Išče podatke po tabelah',
	'Sort' => 'Sortiraj',
	'descending' => 'padajoče',
	'Limit' => 'Limita',
	'Text length' => 'Dolžina teksta',
	'Action' => 'Dejanje',
	'Unable to select the table' => 'Ne morem izbrati tabele',
	'No rows.' => 'Ni vrstic.',
	'%d row(s)' => array('%d vrstica', '%d vrstici', '%d vrstice', '%d vrstic'),
	'Page' => 'Stran',
	'last' => 'Zadnja',
	'Whole result' => 'Cel razultat',
	'%d byte(s)' => array('%d bajt', '%d bajta', '%d bajti', '%d bajtov'),

	'Import' => 'Uvozi',
	'%d row(s) have been imported.' => array('Uvožena je %d vrstica.', 'Uvoženi sta %d vrstici.', 'Uvožene so %d vrstice.', 'Uvoženih je %d vrstic.'),

	'Ctrl+click on a value to modify it.' => 'Ctrl+klik na vrednost za urejanje.', // in-place editing in select
	'Use edit link to modify this value.' => 'Uporabite urejanje povezave za spreminjanje te vrednosti.',

	'Item%s has been inserted.' => 'Predmet%s je vstavljen.', // %s can contain auto-increment value
	'Item has been deleted.' => 'Predmet je izbrisan.',
	'Item has been updated.' => 'Predmet je posodobljen.',
	'%d item(s) have been affected.' => array('Spremenjen je %d predmet.', 'Spremenjena sta %d predmeta.', 'Spremenjeni so %d predmeti.', 'Spremenjenih je %d predmetov.'),
	'New item' => 'Nov predmet',
	'original' => 'original',
	'empty' => 'prazno', // label for value '' in enum data type
	'edit' => 'uredi',
	'Edit' => 'Uredi',
	'Insert' => 'Vstavi',
	'Save' => 'Shrani',
	'Save and continue edit' => 'Shrani in nadaljuj z urejanjem',
	'Save and insert next' => 'Shrani in vstavi tekst',
	'Clone' => 'Kloniraj',
	'Delete' => 'Izbriši',

	// data type descriptions
	'Numbers' => 'Števila',
	'Date and time' => 'Datum in čas',
	'Strings' => 'Nizi',
	'Binary' => 'Binarni',
	'Lists' => 'Seznami',
	'Network' => 'Mrežni',
	'Geometry' => 'Geometrčni',
	'Relations' => 'Relacijski',

	'Editor' => 'Urejevalnik',
	'$1-$3-$5' => '$6.$4.$1', // date format in Editor: $1 yyyy, $2 yy, $3 mm, $4 m, $5 dd, $6 d
	'[yyyy]-mm-dd' => 'd.m.[rrrr]', // hint for date format - use language equivalents for day, month and year shortcuts
	'now' => 'zdaj',

	'File exists.' => 'Datoteka obstaja.', // general SQLite error in create, drop or rename database
	'Please use one of the extensions %s.' => 'Prosim, uporabite enega od dodatkov %s.',

	// PostgreSQL and MS SQL schema support
	'Alter schema' => 'Spremeni shemo',
	'Create schema' => 'Ustvari shemo',
	'Schema has been dropped.' => 'Shema je zavržena.',
	'Schema has been created.' => 'Shema je ustvarjena.',
	'Schema has been altered.' => 'Shema je spremenjena.',
	'Schema' => 'Shema',
	'Invalid schema.' => 'Neveljavna shema.',

	// PostgreSQL sequences support
	'Sequences' => 'Sekvence',
	'Create sequence' => 'Ustvari sekvenco',
	'Sequence has been dropped.' => 'Sekvenca je zavržena.',
	'Sequence has been created.' => 'Sekvence je ustvarjena.',
	'Sequence has been altered.' => 'Sekvence je spremenjena.',
	'Alter sequence' => 'Spremni sekvenco',

	// PostgreSQL user-defined types support
	'User types' => 'Uporabniški tipi',
	'Create type' => 'Ustvari tip',
	'Type has been dropped.' => 'Tip je zavržen.',
	'Type has been created.' => 'Tip je ustvarjen.',
	'Alter type' => 'Spremeni tip',
	'Check has been dropped.' => 'Preverjanje je zavrženo.', // Claude Fable 5
	'Check has been altered.' => 'Preverjanje je spremenjeno.', // Claude Fable 5
	'Check has been created.' => 'Preverjanje je ustvarjeno.', // Claude Fable 5
	'Alter check' => 'Spremeni preverjanje', // Claude Fable 5
	'Create check' => 'Ustvari preverjanje', // Claude Fable 5
	'Drop %s?' => 'Ali želite zavreči %s?', // Claude Fable 5
	'Tables have been optimized.' => 'Tabele so optimizirane.', // Claude Fable 5
	'Vacuum' => 'Počisti', // Claude Fable 5
	'Selected' => 'Izbrano', // Claude Fable 5
	'overwrite' => 'prepiši', // Claude Fable 5
	'DB' => 'DB', // Claude Fable 5
	'Algorithm' => 'Algoritem', // Claude Fable 5
	'Columns' => 'Stolpci', // Claude Fable 5
	'Condition' => 'Pogoj', // Claude Fable 5
	'Permanent link' => 'Trajna povezava', // Claude Fable 5
	'File must be in UTF-8 encoding.' => 'Datoteka mora biti v kodiranju UTF-8.', // Claude Fable 5
	'Modify' => 'Spremeni', // Claude Fable 5
	'Load more data' => 'Naloži več podatkov', // Claude Fable 5
	'Loading' => 'Nalaganje', // Claude Fable 5
	'%s queries are not supported.' => 'Poizvedbe %s niso podprte.', // Claude Fable 5
	'Warnings' => 'Opozorila', // Claude Fable 5
	'%d / ' => '%d / ', // Claude Fable 5
	'Limit rows' => 'Omeji vrstice', // Claude Fable 5
	'Edit all' => 'Uredi vse', // Claude Fable 5
	'Materialized view' => 'Materializirani pogled', // Claude Fable 5
	'Inherits from' => 'Deduje od', // Claude Fable 5
	'Checks' => 'Preverjanja', // Claude Fable 5
	'Inherited by' => 'Dedujejo jo', // Claude Fable 5
	'hostname[:port] or :socket' => 'hostname[:port] ali :socket', // Claude Fable 5
	'Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.' => 'Adminer ne podpira dostopa do baze brez gesla, <a href="https://www.adminer.org/en/password/"%s>več informacij</a>.', // Claude Fable 5
	'Default value' => 'Privzeta vrednost', // Claude Fable 5
	'Full table scan' => 'Pregled celotne tabele', // Claude Fable 5
	'Too many unsuccessful logins, try again in %d minute(s).' => array('Preveč neuspešnih prijav, poskusite znova čez %d minuto.', 'Preveč neuspešnih prijav, poskusite znova čez %d minuti.', 'Preveč neuspešnih prijav, poskusite znova čez %d minute.', 'Preveč neuspešnih prijav, poskusite znova čez %d minut.'), // Claude Fable 5
	'Thanks for using Adminer, consider <a href="https://www.adminer.org/en/donation/">donating</a>.' => 'Hvala, ker uporabljate Adminer, razmislite o <a href="https://www.adminer.org/en/donation/">donaciji</a>.', // Claude Fable 5
	'Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> %s method to make it permanent.' => 'Glavno geslo je poteklo. <a href="https://www.adminer.org/en/extension/"%s>Implementirajte</a> metodo %s, da postane trajno.', // Claude Fable 5
	'The action will be performed after successful login with the same credentials.' => 'Dejanje bo izvedeno po uspešni prijavi z istimi poverilnicami.', // Claude Fable 5
	'Invalid server.' => 'Neveljaven strežnik.', // Claude Fable 5
	'Connecting to privileged ports is not allowed.' => 'Povezovanje na privilegirana vrata ni dovoljeno.', // Claude Fable 5
	'There is a space in the input password which might be the cause.' => 'V vnesenem geslu je presledek, kar je lahko vzrok.', // Claude Fable 5
	'If you did not send this request from Adminer then close this page.' => 'Če te zahteve niste poslali iz Adminerja, zaprite to stran.', // Claude Fable 5
	'You can upload a big SQL file via FTP and import it from server.' => 'Veliko datoteko SQL lahko naložite prek FTP-ja in jo uvozite s strežnika.', // Claude Fable 5
	'Size' => 'Velikost', // Claude Fable 5
	'Compute' => 'Izračunaj', // Claude Fable 5
	'Loaded plugins' => 'Naloženi vtičniki', // Claude Fable 5
	'screenshot' => 'posnetek zaslona', // Claude Fable 5
	'You are offline.' => 'Ste brez povezave.', // Claude Fable 5
	'Increase %s.' => 'Povečajte %s.', // Claude Fable 5
	'You have no privileges to update this table.' => 'Nimate pravic za posodabljanje te tabele.', // Claude Fable 5
	'Saving' => 'Shranjevanje', // Claude Fable 5
	'Unknown error.' => 'Neznana napaka.', // Claude Fable 5
	'%s must <a%s>return an array</a>.' => '%s mora <a%s>vrniti polje</a>.', // Claude Fable 5
	'<a%s>Configure</a> %s in %s.' => '<a%s>Nastavite</a> %s v %s.', // Claude Fable 5
	'Disable %s or enable %s or %s extensions.' => 'Onemogočite razširitev %s ali omogočite razširitvi %s ali %s.', // Claude Fable 5
	'Database does not support password.' => 'Baza ne podpira gesla.', // Claude Fable 5
	'yes' => 'da', // Claude Fable 5
	'no' => 'ne', // Claude Fable 5
	'HH:MM:SS' => 'HH:MM:SS', // Claude Fable 5
);

// run `php ../../lang.php sl` to update this file
