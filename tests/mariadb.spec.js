import {expect, test} from '@playwright/test';
import {button, expectExtension, expectNoErrors, goto, link, newPage, setValue} from './adminer.js';

test.describe.configure({mode: 'serial'}); // the tests depend on each other, e.g. on being logged in

let page;

test.beforeAll(async ({browser}) => {
	page = await newPage(browser);
});

test.afterEach(async () => {
	await expectNoErrors();
});

test.afterAll(async () => {
	await page.close();
});

test('Login', async () => {
	await goto(page, '/adminer/');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[server]"]').fill('localhost:3307');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await button(page, 'Login').click();
	await expectExtension(page);
	await expect(page.locator('body')).toContainText('MariaDB');
	await link(page, 'SQL command').click();
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&sql=DROP+DATABASE+IF+EXISTS+adminer_test');
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('Create database', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC');
	await link(page, 'Create database').click();
	await page.locator('[name="name"]').fill('adminer_test');
	await page.locator('[name="collation"]').selectOption({label: 'utf8mb4_general_ci'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Database has been created.');
});

test('Create table', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="Engine"]').selectOption({label: 'InnoDB'});
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'int'});
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('name');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'varchar'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await page.locator('[name="comments"]').uncheck();
	await page.locator('[name="comments"]').click();
	await page.locator('[name="fields[1.1][comment]"]').fill('Interpret');
	await page.locator('[name="Comment"]').fill('Interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Create index', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=interprets');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[2][columns][1]"]').selectOption({label: 'name'});
	await expect(page.locator('[name="indexes[2][name]"]')).toHaveValue('name');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Multiple primary key defined');
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'INDEX'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
});

test('Partitioning', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=interprets');
	await link(page, 'Alter table').click();
	await link(page, 'Partition by').click(); // the fieldset is hidden
	await page.locator('[name="partition_by"]').selectOption({label: 'HASH'});
	await page.locator('[name="partition"]').fill('id');
	await page.locator('[name="partitions"]').fill('2');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
	await link(page, 'Alter table').click();
	await page.locator('[name="partition_by"]').selectOption({label: 'RANGE'});
	await page.locator('[name="partition_values[]"]').first().fill('10');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
	await link(page, 'Alter table').click();
	await page.locator('[name="partition_by"]').selectOption({label: ''});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Create table 2', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=interprets');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('albums');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'int'});
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('interpret');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'int'});
	await page.locator('[name="fields[1.11][field]"]').fill('title');
	await page.locator('[name="fields[1.11][type]"]').selectOption({label: 'varchar'});
	await page.locator('[name="fields[1.11][length]"]').fill('50');
	await page.locator('[name="comments"]').check();
	await page.locator('[name="fields[1.1][comment]"]').fill('Interpret');
	await page.locator('[name="fields[1.11][comment]"]').fill('Album');
	await page.locator('[name="Comment"]').fill('Albums');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Foreign key', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=albums');
	await link(page, 'Create foreign key').click();
	await page.locator('[name="table"]').selectOption({label: 'interprets'});
	await page.locator('[name="source[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Foreign key has been created.');
});

test('Alter table', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=interprets');
	await link(page, 'Alter table').click();
	await page.locator('[name="add[2]"]').click();
	await page.locator('[name="fields[2.1][field]"]').fill('albums');
	await page.locator('[name="fields[2.1][type]"]').selectOption({label: 'int'});
	await page.locator('[name="fields[2.1][length]"]').fill('');
	await page.locator('[name="defaults"]').uncheck();
	await page.locator('[name="defaults"]').click();
	await page.locator('[name="fields[2.1][default]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Create trigger', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&trigger=albums');
	await page.locator('[name="Timing"]').selectOption({label: 'AFTER'});
	await setValue(page, 'Statement', 'UPDATE interprets SET albums = albums + 1 WHERE id = NEW.interpret');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Trigger has been created.');
});

test('Check constraints', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=albums');
	await link(page, 'Create check').click();
	await page.locator('[name="name"]').fill('albums_interpret_check');
	await setValue(page, 'clause', 'interpret > 0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Check has been created.');
	await link(page, 'New item').click();
	await page.locator('[name="fields[interpret]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('CONSTRAINT `albums_interpret_check` failed for `adminer_test`.`albums`');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&check=albums&name=albums_interpret_check');
	await expect(page.locator('body')).toContainText('`interpret` > 0');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Check has been dropped.');
});

test('Create view', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('albums_interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('View has been created.');
});

test('Invalid table', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&table=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&create=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=invalid');
	await expect(page.locator('body')).toContainText('Unable to select the table:');
});

test('Schema', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&schema=');
	await expect(page.locator('body')).toContainText('Permanent link');
});

test('Insert', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 1 has been inserted.');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&edit=albums');
	await page.locator('[name="fields[interpret]"]').fill('1');
	await page.locator('[name="fields[title]"]').fill('Dangerous');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 1 has been inserted.');
});

test('Clone', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await page.locator('[name="check[]"]').click();
	await page.locator('[name="clone"]').click();
	await page.locator('[name="fields[title]"]').fill('Black and White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 2 has been inserted.');
});

test('Pagination', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums&limit=1');
	await expect(page.locator('body')).toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('Black and White');
	await expect(page.locator('body')).toContainText('2 rows');
	await link(page, 'Load more data').click(); // appends the next page by AJAX
	await expect(page.locator('body')).toContainText('Black and White');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums&limit=1&page=last');
	await expect(page.locator('body')).toContainText('Black and White');
	await expect(page.locator('body')).not.toContainText('Dangerous');
	await expect(page.locator("//fieldset[legend='Page']/b")).toHaveText('2'); // the current page, not a link
});

test('Select', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'title'});
	await page.locator('[name="where[0][val]"]').fill('Dangerous');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('1 row');
});

test('Explain', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await link(page, 'Edit').click();
	await button(page, 'Execute').click();
	await link(page, 'Explain').click();
	await expect(page.locator('body')).toContainText('possible_keys');
});

test('Reference', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await link(page, '1').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test');
	await page.locator('[name="query"]').fill('Jackson');
	await page.locator('[name="search"]').click();
	await link(page, 'interprets').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Update', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&edit=albums&where[id]=2');
	await page.locator('[name="fields[title]"]').fill('Black or White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Modify', async () => {
	// text_length=5 shortens the value, so that Ctrl+click has to load the original by AJAX
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums&text_length=5');
	const td = page.locator('td[id$="[title]"]').first();
	await td.click({modifiers: ['Control']});
	const input = td.locator('textarea'); // a shortened value is edited in a textarea, it can hold newlines
	await expect(input).toHaveValue('Dangerous'); // the cell displays only 'Dange…'
	await input.fill('Bad');
	await page.locator('#save').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
	await expect(page.locator('body')).toContainText('Bad');
});

test('Delete', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await page.locator('input[name="check[]"][value="where[id]=2"]').click();
	await expect(page.locator('input[name="check[]"][value="where[id]=2"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Truncate', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await page.locator('[name="all"]').click();
	await expect(page.locator('[name="all"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('No rows.');
});

test('Import and export CSV', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&select=albums');
	await page.locator('a[href="#import"]').click(); // the file field is hidden until then
	await page.locator('[name="csv_file"]').setInputFiles({
		name: 'albums.csv',
		mimeType: 'text/csv',
		buffer: Buffer.from('id,interpret,title\r\n1,1,Bad\r\n2,1,"Off the Wall"\r\n'),
	});
	await page.locator('[name="separator"]').selectOption('csv');
	await page.locator('[name="import"]').click();
	await expect(page.locator('body')).toContainText('2 rows have been imported.');
	await expect(page.locator('body')).toContainText('Off the Wall');
	// the same primary key is updated, not inserted
	await page.locator('a[href="#import"]').click();
	await page.locator('[name="csv_file"]').setInputFiles({
		name: 'albums.csv',
		mimeType: 'text/csv',
		buffer: Buffer.from('id,interpret,title\r\n2,1,Thriller\r\n'),
	});
	await page.locator('[name="import"]').click();
	await expect(page.locator('body')).toContainText('1 row has been imported.');
	await expect(page.locator('body')).toContainText('2 rows');
	await expect(page.locator('body')).toContainText('Thriller');
	await page.locator('a[href="#fieldset-export"]').click();
	await page.locator('[name="output"]').selectOption('text');
	await page.locator('[name="format"]').selectOption('csv');
	await page.locator('[name="export"]').click();
	await expect(page.locator('body')).toContainText('id,interpret,title');
	await expect(page.locator('body')).toContainText('2,1,Thriller');
});

test('Bulk table operations', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&sql=' + encodeURIComponent(
		'CREATE DATABASE adminer_test2; CREATE TABLE bulk_test (id int); CREATE TABLE bulk_test2 (id int);'
		+ ' INSERT INTO bulk_test VALUES (1)'
	));
	await button(page, 'Execute').click();
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test');
	// every operation redirects back to this page with the checkboxes cleared
	for (const [name, label] of [['', 'Analyze'], ['optimize', 'Optimize'], ['check', 'Check'], ['repair', 'Repair']]) {
		await page.locator('input[name="tables[]"][value="albums"]').check();
		await (name ? page.locator('[name="' + name + '"]') : button(page, label)).click();
		await expect(page.locator('.message')).toContainText('adminer_test.albums'); // one row per table
	}
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="truncate"]').click();
	await expect(page.locator('body')).toContainText('Tables have been truncated.');
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="target"]').selectOption('adminer_test2');
	await page.locator('[name="copy"]').click();
	await expect(page.locator('body')).toContainText('Tables have been copied.');
	await page.locator('input[name="tables[]"][value="bulk_test2"]').check();
	await page.locator('[name="target"]').selectOption('adminer_test2');
	await page.locator('[name="move"]').click();
	await expect(page.locator('body')).toContainText('Tables have been moved.');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test2');
	await expect(page.locator('body')).toContainText('bulk_test'); // the copied one
	await expect(page.locator('body')).toContainText('bulk_test2'); // the moved one
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&sql='
		+ encodeURIComponent('DROP DATABASE adminer_test2'));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('SQL file import', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&import=');
	await page.locator('[name="sql_file[]"]').setInputFiles({
		name: 'test.sql',
		mimeType: 'application/sql',
		buffer: Buffer.from("INSERT INTO interprets (name) VALUES ('Karel Gott');\n"
			+ "DELIMITER ;;\nCREATE PROCEDURE bulk_test_proc() BEGIN SELECT 1; END;;\nDELIMITER ;\n"
			+ 'DROP PROCEDURE bulk_test_proc;\n'),
	});
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('3 queries executed OK.');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&sql='
		+ encodeURIComponent('SELECT * FROM interprets'));
	await page.locator('[name="limit"]').fill('10'); // without a limit, the export is done by JavaScript from the printed table
	await button(page, 'Execute').click();
	await page.locator('a[href="#export-1"]').click();
	await page.locator('#export-1 [name="format"]').selectOption('csv');
	await page.locator('#export-1 [name="export"]').click();
	await expect(page.locator('body')).toContainText('Karel Gott');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&sql='
		+ encodeURIComponent("DELETE FROM interprets WHERE name = 'Karel Gott'"));
	await button(page, 'Execute').click();
});

test('Privileges', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&user=');
	await page.locator('[name="user"]').fill('adminer_test');
	await page.locator('[name="objects[0]"]').fill('adminer_test.*');
	await page.locator('[name="grants[0][ALTER]"]').click();
	await page.locator('[name="grants[0][CREATE]"]').click();
	await page.locator('input[name="grants[0][CREATE VIEW]"]').click();
	await page.locator('[name="grants[0][DELETE]"]').click();
	await page.locator('[name="grants[0][DROP]"]').click();
	await page.locator('[name="grants[0][INDEX]"]').click();
	await page.locator('[name="grants[0][INSERT]"]').click();
	await page.locator('[name="grants[0][REFERENCES]"]').click();
	await page.locator('[name="grants[0][SELECT]"]').click();
	await page.locator('input[name="grants[0][SHOW VIEW]"]').click();
	await page.locator('[name="grants[0][UPDATE]"]').click();
	await page.locator('input[name="grants[0][CREATE TEMPORARY TABLES]"]').click();
	await page.locator('input[name="grants[0][LOCK TABLES]"]').click();
	await page.locator('input[name="grants[0][CREATE ROUTINE]"]').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('User has been created.');
	await page.locator("//div[@id='content']/form/table/tbody/tr[td[1]='adminer_test']/td[3]/a").click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('User has been dropped.');
});

test('Process list', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&processlist=');
	await expect(page.locator('body')).toContainText('SHOW FULL PROCESSLIST');
});

test('Export', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&dump=');
	await page.locator('[name="output"]').first().click();
	await page.locator('[name="format"]').first().click();
	await page.locator('[name="table_style"]').selectOption({label: 'DROP+CREATE'});
	await page.locator('[name="triggers"]').check();
	await page.locator('[name="data_style"]').selectOption({label: 'INSERT'});
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('CREATE TABLE `interprets`');
	await expect(page.locator('body')).toContainText('CREATE TRIGGER `albums_ai`');
	await expect(page.locator('body')).toContainText('INSERT INTO `interprets`');
	await expect(page.locator('body')).toContainText('VIEW `albums_interprets`');
	// several tables in a non-SQL format are packed to a TAR archive built in a temporary file
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&dump=');
	await page.locator('input[name="output"][value="text"]').click();
	await page.locator('input[name="format"][value="csv"]').click();
	const [download] = await Promise.all([
		page.waitForEvent('download'),
		button(page, 'Export').click(),
	]);
	expect(download.suggestedFilename()).toBe('adminer_test.tar');
});

test('Events', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&event=');
	await page.locator('[name="EVENT_NAME"]').fill('no_albums');
	await page.locator('[name="INTERVAL_FIELD"]').selectOption({label: 'DAY'});
	await page.locator('[name="INTERVAL_VALUE"]').fill('1');
	await page.locator('[name="ON_COMPLETION"]').click();
	await setValue(page, 'EVENT_DEFINITION', 'DELETE FROM albums WHERE interprets = 0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Event has been created.');
	await link(page, 'Alter').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Event has been dropped.');
});

test('Procedures', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&procedure=');
	await page.locator('[name="add[0]"]').click();
	await page.locator('[name="fields[1][field]"]').fill('interpret_name');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'varchar'});
	await page.locator('[name="fields[1][length]"]').fill('50');
	await page.locator('[name="fields[1.1][field]"]').fill('album_title');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'varchar'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await setValue(page, 'definition', 'BEGIN\nSELECT id INTO @interpret FROM interprets WHERE name = interpret_name;\nIF @interpret IS NULL THEN\n    INSERT INTO interprets (name) VALUES (interpret_name);\n    SET @interpret = LAST_INSERT_ID();\nEND IF;\nINSERT INTO albums (interpret, title) VALUES (@interpret, album_title);\nEND');
	await page.locator('[name="name"]').fill('insert_album');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Routine has been created.');
	await link(page, 'insert_album').click();
	await page.locator('[name="fields[interpret_name]"]').fill('Michael Jackson');
	await page.locator('[name="fields[album_title]"]').fill('Dangerous');
	await button(page, 'Call').click();
	await expect(page.locator('body')).toContainText('Routine has been called,');
	await link(page, 'adminer_test').click();
	await link(page, 'Alter').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Routine has been dropped.');
});

test('Generated columns', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&db=adminer_test&create=');
	await page.locator('[name="name"]').fill('generated');
	await page.locator('[name="fields[1][field]"]').fill('normal');
	await page.locator('[name="fields[1.1][field]"]').fill('virtual');
	await page.locator('[name="fields[1.1][generated]"]').selectOption({label: 'VIRTUAL'});
	await page.locator('[name="fields[1.1][default]"]').fill('normal + 100');
	await page.locator('[name="fields[1.11][field]"]').fill('stored');
	await page.locator('[name="fields[1.11][generated]"]').selectOption({label: 'STORED'});
	await page.locator('[name="fields[1.11][default]"]').fill('normal + 200');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('`normal` + 100');
	await expect(page.locator('body')).toContainText('`normal` + 200');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[1][columns][1]"]').selectOption({label: 'virtual'});
	await page.locator('[name="indexes[1][columns][11]"]').selectOption({label: 'stored'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
	await link(page, 'New item').click();
	await expect(page.locator('[name="fields[virtual]"]')).toHaveCount(0);
	await expect(page.locator('[name="fields[stored]"]')).toHaveCount(0);
	await page.locator('[name="fields[normal]"]').fill('20');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('120');
	await expect(page.locator('body')).toContainText('220');
});

test('Variables', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&variables=');
	await expect(page.locator('body')).toContainText('basedir');
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&status=');
	await expect(page.locator('body')).toContainText('Uptime');
});

test('History', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC&sql=');
	await expect(page.locator('body')).toContainText('DROP DATABASE IF EXISTS adminer_test');
});

test('Logout', async () => {
	await goto(page, '/adminer/?server=localhost:3307&username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
