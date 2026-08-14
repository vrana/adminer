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

test('Password required', async () => {
	await goto(page, '/tests/sqlite.php');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('[name="auth[driver]"]').selectOption({label: 'SQLite'});
	await page.locator('#username').fill('ODBC');
	await button(page, 'Login').click(); // no password, the driver stays selected in the re-printed form
	await expect(page.locator('body')).toContainText('Adminer does not support accessing a database without a password.');
	await link(page, 'Require a password.').click();
	await expect(page.locator('#password-less')).toContainText("new Adminer\\Password('$2y$"); // the rest of the hash differs in every request
	await page.locator('[name="auth[password]"]').fill('invalid');
	await button(page, 'Login').click();
	await expect(page.locator('body')).toContainText('The database does not support passwords.');
	await link(page, 'Require a password.').click();
	await expect(page.locator('#password-less')).toContainText("new Adminer\\Password('$2y$");
});

test('Login', async () => {
	await goto(page, '/tests/sqlite.php');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('[name="auth[driver]"]').selectOption({label: 'SQLite'});
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('YOUR_PASSWORD_HERE');
	await button(page, 'Login').click();
	await expectExtension(page);
	await expect(page.locator('body')).toContainText('Adminer\\Password');
	await link(page, 'Create database').click();
	await page.locator('[name="name"]').fill('adminer_test');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Please use one of these file extensions');
	await page.locator('[name="name"]').fill('adminer_test.sqlite');
	await button(page, 'Save').click();
	const exists = page.getByText('File exists.');
	await expect(exists.or(page.getByText('Database has been created.'))).toBeVisible();
	if (await exists.count()) { // a database left by an interrupted run; SQLite databases are not listed so it can be dropped only by opening it
		await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&database=');
		await page.locator('[name="drop"]').click();
		await expect(page.locator('body')).toContainText('Database has been dropped.');
		await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&database=');
		await page.locator('[name="name"]').fill('adminer_test.sqlite');
		await button(page, 'Save').click();
	}
	await expect(page.locator('body')).toContainText('Database has been created.');
});

test('Create table', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'integer'});
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('name');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'text'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Create index', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&table=interprets');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[2][columns][1]"]').selectOption({label: 'name'});
	await expect(page.locator('[name="indexes[2][name]"]')).toHaveValue('interprets_name');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has more than one primary key');
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'INDEX'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
});

test('Create table 2', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&table=interprets');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('albums');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'integer'});
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('interpret');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'integer'});
	await page.locator('[name="fields[1.11][field]"]').fill('title');
	await page.locator('[name="fields[1.11][type]"]').selectOption({label: 'text'});
	await page.locator('[name="fields[1.11][length]"]').fill('50');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Foreign key', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&table=albums');
	await link(page, 'Create foreign key').click();
	await page.locator('[name="table"]').selectOption({label: 'interprets'});
	await page.locator('[name="source[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Foreign key has been created.');
});

test('Alter table', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&table=interprets');
	await link(page, 'Alter table').click();
	await page.locator('[name="add[2]"]').click();
	await page.locator('[name="fields[2.1][field]"]').fill('albums');
	await page.locator('[name="fields[2.1][type]"]').selectOption({label: 'integer'});
	await page.locator('[name="fields[2.1][length]"]').fill('');
	await page.locator('[name="defaults"]').uncheck();
	await page.locator('[name="defaults"]').click();
	await page.locator('[name="fields[2.1][default]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Create trigger', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&trigger=albums');
	await page.locator('[name="Timing"]').selectOption({label: 'AFTER'});
	await setValue(page, 'Statement', 'BEGIN\nUPDATE interprets SET albums = albums + 1 WHERE id = NEW.interpret;\nEND');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Trigger has been created.');
});

test('Check constraints', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&table=albums');
	await link(page, 'Create check').click();
	await setValue(page, 'clause', 'interpret > 0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Check has been created.');
	await link(page, 'New item').click();
	await page.locator('[name="fields[interpret]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('CHECK constraint failed');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&check=albums&name=interpret+%3E+0');
	await expect(page.locator('body')).toContainText('interpret > 0');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Check has been dropped.');
});

test('Create view', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('albums_interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('View has been created.');
});

test('Invalid table', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&table=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&create=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=invalid');
	await expect(page.locator('body')).toContainText('Unable to select the table:');
});

test('Schema', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&schema=');
	await expect(page.locator('body')).toContainText('Permanent link');
});

test('Insert', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 1 has been inserted.');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&edit=albums');
	await page.locator('[name="fields[interpret]"]').fill('1');
	await page.locator('[name="fields[title]"]').fill('Dangerous');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 1 has been inserted.');
});

test('Clone', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
	await page.locator('[name="check[]"]').click();
	await page.locator('[name="clone"]').click();
	await page.locator('[name="fields[title]"]').fill('Black and White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 2 has been inserted.');
});

test('Pagination', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums&order[0]=id&limit=1');
	await expect(page.locator('body')).toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('Black and White');
	await expect(page.locator('body')).toContainText('2 rows');
	await link(page, 'Load more data').click(); // appends the next page by AJAX
	await expect(page.locator('body')).toContainText('Black and White');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums&order[0]=id&limit=1&page=last');
	await expect(page.locator('body')).toContainText('Black and White');
	await expect(page.locator('body')).not.toContainText('Dangerous');
	await expect(page.locator("//fieldset[legend='Page']/b")).toHaveText('2'); // the current page, not a link
});

test('Select', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'title'});
	await page.locator('[name="where[0][val]"]').fill('Dangerous');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('1 row');
});

test('Explain', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
	await link(page, 'Edit').click();
	await button(page, 'Execute').click();
	await link(page, 'Explain').click();
	await expect(page.locator('body')).toContainText('SCAN albums');
});

test('Reference', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
	await link(page, '1').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite');
	await page.locator('[name="query"]').fill('Jackson');
	await page.locator('[name="search"]').click();
	await link(page, 'interprets').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables with special types', async () => {
	const sql = 'CREATE TABLE types (id integer PRIMARY KEY, b blob, n integer, d date, tm time, j json, t text);'
		+ " INSERT INTO types VALUES (1, x'61626333', 3, '2020-01-03', '12:34:56', '{\"a\": 3}', 'abc3')";
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&sql=' + encodeURIComponent(sql));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite');
	for (const [op, query] of [['LIKE %%', 'abc'], ['LIKE %%', '3'], ['=', 'abc3'], ['LIKE', '%bc%']]) {
		await page.locator('[name="op"]').selectOption(op);
		await page.locator('[name="query"]').fill(query);
		await page.locator('[name="search"]').click();
		await expect(page.locator('.error')).toHaveCount(0); // a column which can't be searched must be skipped, not reported
		await expect(page.locator("li a[href*='select=types&where']")).toBeVisible(); // the list of the tables holding the value
	}
	// whether these values are found depends on the types of the driver, only the missing error is checked
	for (const query of ['2020-01-03', '12:34:56', 'ěščř']) {
		await page.locator('[name="query"]').fill(query);
		await page.locator('[name="search"]').click();
		await expect(page.locator('.error')).toHaveCount(0);
	}
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&sql=' + encodeURIComponent('DROP TABLE types'));
	await button(page, 'Execute').click();
});

test('Modify', async () => {
	// text_length=5 shortens the value, so that Ctrl+click has to load the original by AJAX
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums&order[0]=id&text_length=5');
	const td = page.locator('td[id$="[title]"]').first();
	await td.click({modifiers: ['Control']});
	const input = td.locator('textarea'); // a shortened value is edited in a textarea, it can hold newlines
	await expect(input).toHaveValue('Dangerous'); // the cell displays only 'Dange…'
	await input.fill('Bad');
	await page.locator('#save').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
	await expect(page.locator('body')).toContainText('Bad');
});

test('Update', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&edit=albums&where[id]=2');
	await page.locator('[name="fields[title]"]').fill('Black or White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
	await page.locator('input[name="check[]"][value="where[id]=2"]').click();
	await expect(page.locator('input[name="check[]"][value="where[id]=2"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Truncate', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
	await page.locator('[name="all"]').click();
	await expect(page.locator('[name="all"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('No rows.');
});

test('Import and export CSV', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&select=albums');
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
	// SQLite move_tables() always fails, so the Move button is not tested
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&sql='
		+ encodeURIComponent('CREATE TABLE bulk_test (id integer); INSERT INTO bulk_test VALUES (1)'));
	await button(page, 'Execute').click();
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite');
	// every operation redirects back to this page with the checkboxes cleared
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await button(page, 'Vacuum').click();
	await expect(page.locator('body')).toContainText('Tables have been optimized.');
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="check"]').click();
	await expect(page.locator('.message')).toContainText('bulk_test'); // one row per table
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="truncate"]').click();
	await expect(page.locator('body')).toContainText('Tables have been truncated.');
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Tables have been dropped.');
});

test('SQL file import', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&import=');
	await page.locator('[name="sql_file[]"]').setInputFiles({
		name: 'test.sql',
		mimeType: 'application/sql',
		buffer: Buffer.from("INSERT INTO interprets (name) VALUES ('Karel');\n"
			+ "DELIMITER ;;\nUPDATE interprets SET name = 'Karel Gott' WHERE name = 'Karel';;\nDELIMITER ;\n"),
	});
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('2 queries executed OK.');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&sql='
		+ encodeURIComponent('SELECT * FROM interprets'));
	await page.locator('[name="limit"]').fill('10'); // without a limit, the export is done by JavaScript from the printed table
	await button(page, 'Execute').click();
	await page.locator('a[href="#export-1"]').click();
	await page.locator('#export-1 [name="format"]').selectOption('csv');
	await page.locator('#export-1 [name="export"]').click();
	await expect(page.locator('body')).toContainText('Karel Gott');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&sql='
		+ encodeURIComponent("DELETE FROM interprets WHERE name = 'Karel Gott'"));
	await button(page, 'Execute').click();
});

test('Export', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&dump=');
	await page.locator('[name="output"]').first().click();
	await page.locator('[name="format"]').first().click();
	await page.locator('[name="table_style"]').selectOption({label: 'DROP+CREATE'});
	await page.locator('[name="triggers"]').check();
	await page.locator('[name="data_style"]').selectOption({label: 'INSERT'});
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('CREATE TABLE "interprets"');
	await expect(page.locator('body')).toContainText('CREATE TRIGGER "albums_ai"');
	await expect(page.locator('body')).toContainText('INSERT INTO "interprets"');
	await expect(page.locator('body')).toContainText('VIEW "albums_interprets"');
	// several tables in a non-SQL format are packed to a TAR archive built in a temporary file
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&dump=');
	await page.locator('input[name="output"][value="text"]').click();
	await page.locator('input[name="format"][value="csv"]').click();
	const [download] = await Promise.all([
		page.waitForEvent('download'),
		button(page, 'Export').click(),
	]);
	expect(download.suggestedFilename()).toBe('adminer_test-sqlite.tar'); // the dot of the filename is not allowed in the name
});

test('Generated columns', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&create=');
	await page.locator('[name="name"]').fill('generated');
	await page.locator('[name="fields[1][field]"]').fill('normal');
	await page.locator('[name="fields[1.1][field]"]').fill('stored');
	await page.locator('[name="fields[1.1][generated]"]').selectOption({label: 'STORED'});
	await page.locator('[name="fields[1.1][default]"]').fill('normal + 200');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('normal + 200');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[1][columns][1]"]').selectOption({label: 'stored'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
	await link(page, 'New item').click();
	await expect(page.locator('[name="fields[stored]"]')).toHaveCount(0);
	await page.locator('[name="fields[normal]"]').fill('20');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('220');
});

test('Variables', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&variables=');
	await expect(page.locator('body')).toContainText('integrity_check');
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&status=');
	await expect(page.locator('body')).toContainText('MAX_COLUMN');
});

test('Drop', async () => {
	await goto(page, '/tests/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite&database=');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Database has been dropped.');
	await page.locator('#logout').click();
	await expect(page.locator('body')).toContainText('Thanks for using Adminer. Consider donating.');
});
