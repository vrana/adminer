<?php
namespace Adminer;

Lang::$translations = array(
	'System' => 'Sistem', // label for database system selection (MySQL, SQLite, ...)
	'Server' => 'Server',
	'Username' => 'Korisničko ime',
	'Password' => 'Lozinka',
	'Permanent login' => 'Trajna prijava',
	'Login' => 'Prijava',
	'Logout' => 'Odjava',
	'Logged as: %s' => 'Prijavi se kao: %s',
	'Logout successful.' => 'Uspešna odjava.',
	'Invalid credentials.' => 'Nevažeće dozvole.',
	'Language' => 'Jezik',
	'Invalid CSRF token. Send the form again.' => 'Nevažeći CSRF kod. Proslijedite ponovo formu.',
	'No extension' => 'Bez dodataka',
	'None of the supported PHP extensions (%s) are available.' => 'Nijedan od podržanih PHP dodataka (%s) nije dostupan.',
	'Session support must be enabled.' => 'Morate omogućiti podršku za sesije.',
	'Session expired, please login again.' => 'Vaša sesija je istekla, prijavite se ponovo.',
	'%s version: %s through PHP extension %s' => '%s verzija: %s pomoću PHP dodatka je %s',
	'Refresh' => 'Osveži',

	'ltr' => 'ltr', // text direction - 'ltr' or 'rtl'

	'Privileges' => 'Dozvole',
	'Create user' => 'Novi korisnik',
	'User has been dropped.' => 'Korisnik je izbrisan.',
	'User has been altered.' => 'Korisnik je izmijenjen.',
	'User has been created.' => 'korisnik je spašen.',
	'Hashed' => 'Heširano',
	'Column' => 'kolumna',
	'Routine' => 'Rutina',
	'Grant' => 'Dozvoli',
	'Revoke' => 'Opozovi',

	'Process list' => 'Spisak procesa',
	'%d process(es) have been killed.' => array('%d proces je ukinut.', '%d procesa su ukinuta.', '%d procesa je ukinuto.'),
	'Kill' => 'Ubij',

	'Variables' => 'Promijenljive',
	'Status' => 'Status',

	'SQL command' => 'SQL komanda',
	'%d query(s) executed OK.' => array('%d upit je uspiješno izvršen.', '%d upita su uspiješno izvršena.', '%d upita je uspiješno izvršeno.'),
	'Query executed OK, %d row(s) affected.' => array('Upit je uspiješno izvršen, %d red je ažuriran.', 'Upit je uspiješno izvršen, %d reda su ažurirana.', 'Upit je uspiješno izvršen, %d redova je ažurirano.'),
	'No commands to execute.' => 'Bez komandi za izvršavanje.',
	'Error in query' => 'Greška u upitu',
	'Execute' => 'Izvrši',
	'Stop on error' => 'Zaustavi prilikom greške',
	'Show only errors' => 'Prikazuj samo greške',
	'%.3f s' => '%.3f s', // sprintf() format for time of the command
	'History' => 'Historijat',
	'Clear' => 'Očisti',
	'Edit all' => 'Izmijeni sve',

	'File upload' => 'Slanje datoteka',
	'From server' => 'Sa servera',
	'Webserver file %s' => 'Datoteka %s sa veb servera',
	'Run file' => 'Pokreni datoteku',
	'File does not exist.' => 'Datoteka ne postoji.',
	'File uploads are disabled.' => 'Onemogućeno je slanje datoteka.',
	'Unable to upload a file.' => 'Slanje datoteke nije uspelo.',
	'Maximum allowed file size is %sB.' => 'Najveća dozvoljena veličina datoteke je %sB.',
	'Too big POST data. Reduce the data or increase the %s configuration directive.' => 'Preveliki POST podatak. Morate da smanjite podatak ili povećajte vrijednost konfiguracione direktive %s.',

	'Export' => 'Izvoz',
	'Output' => 'Ispis',
	'open' => 'otvori',
	'save' => 'spasi',
	'Format' => 'Format',
	'Data' => 'Podaci',

	'Database' => 'Baza podataka',
	'Use' => 'Koristi',
	'Select database' => 'Izaberite bazu',
	'Invalid database.' => 'Neispravna baza podataka.',
	'Database has been dropped.' => 'Baza podataka je izbrisana.',
	'Databases have been dropped.' => 'Baze podataka su izbrisane.',
	'Database has been created.' => 'Baza podataka je spašena.',
	'Database has been renamed.' => 'Baza podataka je preimenovana.',
	'Database has been altered.' => 'Baza podataka je izmijenjena.',
	'Alter database' => 'Ažuriraj bazu podataka',
	'Create database' => 'Formiraj bazu podataka',
	'Database schema' => 'Šema baze podataka',

	'Permanent link' => 'Trajna veza', // link to current database schema layout

	',' => ',', // thousands separator - must contain single byte
	'0123456789' => '0123456789',
	'Engine' => 'Stroj',
	'Collation' => 'Sravnjivanje',
	'Data Length' => 'Dužina podataka',
	'Index Length' => 'Dužina indeksa',
	'Data Free' => 'Slobodno podataka',
	'Rows' => 'Redova',
	'%d in total' => 'ukupno %d',
	'Analyze' => 'Analiziraj',
	'Optimize' => 'Optimizuj',
	'Check' => 'Provjeri',
	'Repair' => 'Popravi',
	'Truncate' => 'Isprazni',
	'Tables have been truncated.' => 'Tabele su ispražnjene.',
	'Move to other database' => 'Premijesti u drugu bazu podataka',
	'Move' => 'Premijesti',
	'Tables have been moved.' => 'Tabele su premješćene.',
	'Copy' => 'Umnoži',
	'Tables have been copied.' => 'Tabele su umnožene.',

	'Routines' => 'Rutine',
	'Routine has been called, %d row(s) affected.' => array('Pozvana je rutina, %d red je ažuriran.', 'Pozvana je rutina, %d reda su ažurirani.', 'Pozvana je rutina, %d redova je ažurirano.'),
	'Call' => 'Pozovi',
	'Parameter name' => 'Naziv parametra',
	'Create procedure' => 'Formiraj proceduru',
	'Create function' => 'Formiraj funkciju',
	'Routine has been dropped.' => 'Rutina je izbrisana.',
	'Routine has been altered.' => 'Rutina je izmijenjena.',
	'Routine has been created.' => 'Rutina je spašena.',
	'Alter function' => 'Ažuriraj funkciju',
	'Alter procedure' => 'Ažuriraj proceduru',
	'Return type' => 'Povratni tip',

	'Events' => 'Događaji',
	'Event has been dropped.' => 'Događaj je izbrisan.',
	'Event has been altered.' => 'Događaj je izmijenjen.',
	'Event has been created.' => 'Događaj je spašen.',
	'Alter event' => 'Ažuriraj događaj',
	'Create event' => 'Napravi događaj',
	'At given time' => 'U zadato vrijeme',
	'Every' => 'Svaki',
	'Schedule' => 'Raspored',
	'Start' => 'Početak',
	'End' => 'Kraj',
	'On completion preserve' => 'Zadrži po završetku',

	'Tables' => 'Tabele',
	'Tables and views' => 'Tabele i pogledi',
	'Table' => 'Tabela',
	'No tables.' => 'Bez tabela.',
	'Alter table' => 'Ažuriraj tabelu',
	'Create table' => 'Napravi tabelu',
	'Table has been dropped.' => 'Tabela je izbrisana.',
	'Tables have been dropped.' => 'Tabele su izbrisane.',
	'Tables have been optimized.' => 'Tabele su optimizovane.',
	'Table has been altered.' => 'Tabela je izmijenjena.',
	'Table has been created.' => 'Tabela je spašena.',
	'Table name' => 'Naziv tabele',
	'Show structure' => 'Prikaži strukturu',
	'engine' => 'stroj',
	'collation' => 'Sravnjivanje',
	'Column name' => 'Naziv kolumne',
	'Type' => 'Tip',
	'Length' => 'Dužina',
	'Auto Increment' => 'Auto-priraštaj',
	'Options' => 'Opcije',
	'Comment' => 'Komentar',
	'Default values' => 'Podrazumijevane vrijednosti',
	'Drop' => 'Izbriši',
	'Are you sure?' => 'Da li ste sigurni?',
	'Remove' => 'Ukloni',
	'Maximum number of allowed fields exceeded. Please increase %s.' => 'Premašen je maksimalni broj dozvoljenih polja. Molim uvećajte %s.',

	'Partition by' => 'Podijeli po',
	'Partitions' => 'Podijele',
	'Partition name' => 'Ime podijele',
	'Values' => 'Vrijednosti',

	'View' => 'Pogled',
	'View has been dropped.' => 'Pogled je izbrisan.',
	'View has been altered.' => 'Pogled je izmijenjen.',
	'View has been created.' => 'Pogled je spašen.',
	'Alter view' => 'Ažuriraj pogled',
	'Create view' => 'Napravi pogled',

	'Indexes' => 'Indeksi',
	'Indexes have been altered.' => 'Indeksi su izmijenjeni.',
	'Alter indexes' => 'Ažuriraj indekse',
	'Add next' => 'Dodaj slijedeći',
	'Index Type' => 'Tip indeksa',
	'length' => 'dužina',
	'operator class' => 'klasa operatora', // Claude Fable 5

	'Foreign keys' => 'Strani ključevi',
	'Foreign key' => 'Strani ključ',
	'Foreign key has been dropped.' => 'Strani ključ je izbrisan.',
	'Foreign key has been altered.' => 'Strani ključ je izmijenjen.',
	'Foreign key has been created.' => 'Strani ključ je spašen.',
	'Target table' => 'Ciljna tabela',
	'Change' => 'izmijeni',
	'Source' => 'Izvor',
	'Target' => 'Cilj',
	'Add column' => 'Dodaj kolumnu',
	'Alter' => 'Ažuriraj',
	'Add foreign key' => 'Dodaj strani ključ',
	'ON DELETE' => 'ON DELETE (prilikom brisanja)',
	'ON UPDATE' => 'ON UPDATE (prilikom osvežavanja)',
	'Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.' => 'Izvorne i ciljne kolumne moraju biti istog tipa, ciljna kolumna mora biti indeksirana i izvorna tabela mora sadržati podatke iz ciljne.',

	'Triggers' => 'Okidači',
	'Add trigger' => 'Dodaj okidač',
	'Trigger has been dropped.' => 'Okidač je izbrisan.',
	'Trigger has been altered.' => 'Okidač je izmijenjen.',
	'Trigger has been created.' => 'Okidač je spašen.',
	'Alter trigger' => 'Ažuriraj okidač',
	'Create trigger' => 'Formiraj okidač',
	'Time' => 'Vrijeme',
	'Event' => 'Događaj',
	'Name' => 'Ime',

	'select' => 'izaberi',
	'Select' => 'Izaberi',
	'Selected' => 'Izabrano',
	'Select data' => 'Izaberi podatke',
	'Functions' => 'Funkcije',
	'Aggregation' => 'Sakupljanje',
	'Search' => 'Pretraga',
	'anywhere' => 'bilo gdje',
	'Search data in tables' => 'Pretraži podatke u tabelama',
	'Sort' => 'Poređaj',
	'descending' => 'opadajuće',
	'Limit' => 'Granica',
	'Text length' => 'Dužina teksta',
	'Action' => 'Akcija',
	'Full table scan' => 'Skreniranje kompletne tabele',
	'Unable to select the table' => 'Ne mogu da izaberem tabelu',
	'No rows.' => 'Bez redova.',
	'%d row(s)' => array('%d red', '%d reda', '%d redova'),
	'Page' => 'Strana',
	'last' => 'poslijednja',
	'Loading' => 'Učitavam',
	'Load more data' => 'Učitavam još podataka',
	'Whole result' => 'Ceo rezultat',
	'%d byte(s)' => array('%d bajt', '%d bajta', '%d bajtova'),

	'Import' => 'Uvoz',
	'%d row(s) have been imported.' => array('%d red je uvežen.', '%d reda su uvežena.', '%d redova je uveženo.'),

	'Ctrl+click on a value to modify it.' => 'Ctrl+klik na vrijednost za izmijenu.', // in-place editing in select
	'Use edit link to modify this value.' => 'Koristi vezu za izmijenu ove vrijednosti.',

	'Item%s has been inserted.' => 'Stavka %s je spašena.', // %s can contain auto-increment value
	'Item has been deleted.' => 'Stavka je izbrisana.',
	'Item has been updated.' => 'Stavka je izmijenjena.',
	'%d item(s) have been affected.' => array('%d stavka je ažurirana.', '%d stavke su ažurirane.', '%d stavki je ažurirano.'),
	'New item' => 'Nova stavka',
	'original' => 'original',
	'empty' => 'prazno', // label for value '' in enum data type
	'edit' => 'izmijeni',
	'Edit' => 'Izmijeni',
	'Insert' => 'Umetni',
	'Save' => 'Sačuvaj',
	'Save and continue edit' => 'Sačuvaj i nastavi uređenje',
	'Save and insert next' => 'Sačuvaj i umijetni slijedeće',
	'Clone' => 'Dupliraj',
	'Delete' => 'Izbriši',
	'Modify' => 'Izmjene',

	// data type descriptions
	'Numbers' => 'Broj',
	'Date and time' => 'Datum i vrijeme',
	'Strings' => 'Tekst',
	'Binary' => 'Binarno',
	'Lists' => 'Liste',
	'Network' => 'Mreža',
	'Geometry' => 'Geometrija',
	'Relations' => 'Odnosi',

	'Editor' => 'Uređivač',
	'$1-$3-$5' => '$5.$3.$1.', // date format in Editor: $1 yyyy, $2 yy, $3 mm, $4 m, $5 dd, $6 d
	'[yyyy]-mm-dd' => 'dd.mm.[yyyy].', // hint for date format - use language equivalents for day, month and year shortcuts
	'HH:MM:SS' => 'HH:MM:SS', // hint for time format - use language equivalents for hour, minute and second shortcuts
	'now' => 'sad',
	'yes' => 'da',
	'no' => 'ne',

	'File exists.' => 'Datoteka već postoji.', // general SQLite error in create, drop or rename database
	'Please use one of the extensions %s.' => 'Molim koristite jedan od nastavaka %s.',

	// PostgreSQL and MS SQL schema support
	'Alter schema' => 'Ažuriraj šemu',
	'Create schema' => 'Formiraj šemu',
	'Schema has been dropped.' => 'Šema je izbrisana.',
	'Schema has been created.' => 'Šema je spašena.',
	'Schema has been altered.' => 'Šema je izmijenjena.',
	'Schema' => 'Šema',
	'Invalid schema.' => 'Šema nije ispravna.',

	// PostgreSQL sequences support
	'Sequences' => 'Nizovi',
	'Create sequence' => 'Napravi niz',
	'Sequence has been dropped.' => 'Niz je izbrisan.',
	'Sequence has been created.' => 'Niz je formiran.',
	'Sequence has been altered.' => 'Niz je izmijenjen.',
	'Alter sequence' => 'Ažuriraj niz',

	// PostgreSQL user-defined types support
	'User types' => 'Korisnički tipovi',
	'Create type' => 'Definiši tip',
	'Type has been dropped.' => 'Tip je izbrisan.',
	'Type has been created.' => 'tip je spašen.',
	'Alter type' => 'Ažuriraj tip',
	'Check has been dropped.' => 'Provjera je izbrisana.', // Claude Fable 5
	'Check has been altered.' => 'Provjera je izmijenjena.', // Claude Fable 5
	'Check has been created.' => 'Provjera je napravljena.', // Claude Fable 5
	'Alter check' => 'Izmijeni provjeru', // Claude Fable 5
	'Create check' => 'Napravi provjeru', // Claude Fable 5
	'Drop %s?' => 'Izbrisati %s?', // Claude Fable 5
	'Vacuum' => 'Očisti', // Claude Fable 5
	'overwrite' => 'prepiši', // Claude Fable 5
	'DB' => 'DB', // Claude Fable 5
	'Algorithm' => 'Algoritam', // Claude Fable 5
	'Columns' => 'Kolumne', // Claude Fable 5
	'Condition' => 'Uslov', // Claude Fable 5
	'File must be in UTF-8 encoding.' => 'Datoteka mora biti u UTF-8 kodiranju.', // Claude Fable 5
	'%s queries are not supported.' => '%s upiti nisu podržani.', // Claude Fable 5
	'Warnings' => 'Upozorenja', // Claude Fable 5
	'%d / ' => '%d / ', // Claude Fable 5
	'Limit rows' => 'Ograniči broj redova', // Claude Fable 5
	'Materialized view' => 'Materijalizirani pogled', // Claude Fable 5
	'Inherits from' => 'Nasljeđuje od', // Claude Fable 5
	'Checks' => 'Provjere', // Claude Fable 5
	'Inherited by' => 'Naslijeđeno od', // Claude Fable 5
	'hostname[:port] or :socket' => 'hostname[:port] ili :socket', // Claude Fable 5
	'Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.' => 'Adminer ne podržava pristup bazi podataka bez lozinke, <a href="https://www.adminer.org/en/password/"%s>više informacija</a>.', // Claude Fable 5
	'Default value' => 'Zadana vrijednost', // Claude Fable 5
	'Too many unsuccessful logins, try again in %d minute(s).' => array('Previše neuspješnih prijava, pokušajte ponovo za %d minutu.', 'Previše neuspješnih prijava, pokušajte ponovo za %d minute.', 'Previše neuspješnih prijava, pokušajte ponovo za %d minuta.'), // Claude Fable 5
	'Thanks for using Adminer, consider <a href="https://www.adminer.org/en/donation/">donating</a>.' => 'Hvala što koristite Adminer, razmislite o <a href="https://www.adminer.org/en/donation/">donaciji</a>.', // Claude Fable 5
	'Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> %s method to make it permanent.' => 'Glavna lozinka je istekla. <a href="https://www.adminer.org/en/extension/"%s>Implementirajte</a> metodu %s da biste je učinili trajnom.', // Claude Fable 5
	'The action will be performed after successful login with the same credentials.' => 'Radnja će biti izvršena nakon uspješne prijave s istim podacima.', // Claude Fable 5
	'Invalid server.' => 'Nevažeći server.', // Claude Fable 5
	'Connecting to privileged ports is not allowed.' => 'Povezivanje na privilegirane portove nije dozvoljeno.', // Claude Fable 5
	'There is a space in the input password which might be the cause.' => 'U unesenoj lozinci postoji razmak, što bi mogao biti uzrok.', // Claude Fable 5
	'If you did not send this request from Adminer then close this page.' => 'Ako niste poslali ovaj zahtjev iz Adminera, zatvorite ovu stranicu.', // Claude Fable 5
	'You can upload a big SQL file via FTP and import it from server.' => 'Veliku SQL datoteku možete poslati putem FTP-a i uvesti je sa servera.', // Claude Fable 5
	'Size' => 'Veličina', // Claude Fable 5
	'Compute' => 'Izračunaj', // Claude Fable 5
	'Loaded plugins' => 'Učitani dodaci', // Claude Fable 5
	'screenshot' => 'snimak ekrana', // Claude Fable 5
	'You are offline.' => 'Van mreže ste.', // Claude Fable 5
	'Increase %s.' => 'Povećajte %s.', // Claude Fable 5
	'You have no privileges to update this table.' => 'Nemate privilegije za ažuriranje ove tabele.', // Claude Fable 5
	'Saving' => 'Spašavam', // Claude Fable 5
	'Unknown error.' => 'Nepoznata greška.', // Claude Fable 5
	'%s must <a%s>return an array</a>.' => '%s mora <a%s>vratiti niz</a>.', // Claude Fable 5
	'<a%s>Configure</a> %s in %s.' => '<a%s>Konfigurirajte</a> %s u %s.', // Claude Fable 5
	'Disable %s or enable %s or %s extensions.' => 'Onemogućite ekstenziju %s ili omogućite ekstenzije %s ili %s.', // Claude Fable 5
	'Database does not support password.' => 'Baza podataka ne podržava lozinku.', // Claude Fable 5
);

// run `php ../../lang.php bs` to update this file
