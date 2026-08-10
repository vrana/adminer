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
	await page.locator('[name="auth[driver]"]').selectOption({label: 'PostgreSQL'});
	await page.locator('[name="auth[server]"]').fill('localhost:26257');
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await button(page, 'Login').click();
	await expectExtension(page);
	await expect(page.locator('body')).toContainText('CockroachDB');
	await link(page, 'SQL command').click();
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&sql=DROP+DATABASE+IF+EXISTS+adminer_test%3BCREATE+DATABASE+adminer_test');
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('Create table', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'integer'});
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('name');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'character varying'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await page.locator('[name="comments"]').uncheck();
	await page.locator('[name="comments"]').click();
	await page.locator('[name="fields[1.1][comment]"]').fill('Interpret');
	await page.locator('[name="Comment"]').fill('Interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Create index', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&table=interprets');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[2][columns][1]"]').selectOption({label: 'name'});
	await expect(page.locator('[name="indexes[2][name]"]')).toHaveValue('interprets_name');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('multiple primary keys for table "interprets" are not allowed');
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'INDEX'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
});

test('Create table 2', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&table=interprets');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('albums');
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('interpret');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'integer'});
	await page.locator('[name="fields[1.11][field]"]').fill('title');
	await page.locator('[name="fields[1.11][type]"]').selectOption({label: 'character varying'});
	await page.locator('[name="fields[1.11][length]"]').fill('50');
	await page.locator('[name="comments"]').check();
	await page.locator('[name="fields[1.1][comment]"]').fill('Interpret');
	await page.locator('[name="fields[1.11][comment]"]').fill('Album');
	await page.locator('[name="Comment"]').fill('Albums');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Foreign key', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&table=albums');
	await link(page, 'Create foreign key').click();
	await page.locator('[name="table"]').selectOption({label: 'interprets'});
	await page.locator('[name="source[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Foreign key has been created.');
});

test('Alter table', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&table=interprets');
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

test('Check constraints', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&table=albums');
	await link(page, 'Create check').click();
	await page.locator('[name="name"]').fill('albums_interpret_check');
	await setValue(page, 'clause', 'interpret > 0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Check has been created.');
	await link(page, 'New item').click();
	await page.locator('[name="fields[interpret]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('failed to satisfy CHECK constraint');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&check=albums&name=albums_interpret_check');
	await expect(page.locator('body')).toContainText('((interpret > 0:::INT8))');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Check has been dropped.');
});

test('Create view', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('albums_interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('View has been created.');
});

test('Materialized view', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('materialized_view');
	await page.locator('[name="materialized"]').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Materialized view');
});

test('Invalid table', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&table=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&create=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=invalid');
	await expect(page.locator('body')).toContainText('Unable to select the table:');
});

test('Schema', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&schema=');
	await expect(page.locator('body')).toContainText('Permanent link');
});

test('Insert', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&edit=interprets');
	await page.locator('[name="fields[id]"]').fill('1');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has been inserted.');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&edit=albums');
	await page.locator('[name="fields[interpret]"]').fill('1');
	await page.locator('[name="fields[title]"]').fill('Dangerous');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has been inserted.');
});

test('Clone', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
	await page.locator('[name="check[]"]').click();
	await page.locator('[name="clone"]').click();
	await page.locator('[name="fields[id]"]').fill('2');
	await page.locator('[name="fields[title]"]').fill('Black and White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 2 has been inserted.');
});

test('Pagination', async () => {
	// the cloned row is first because the id of the original one was generated by unique_rowid()
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums&order[0]=id&limit=1');
	await expect(page.locator('body')).toContainText('Black and White');
	await expect(page.locator('body')).not.toContainText('Dangerous');
	await expect(page.locator('body')).toContainText('2 rows');
	await link(page, 'Load more data').click(); // appends the next page by AJAX
	await expect(page.locator('body')).toContainText('Dangerous');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums&order[0]=id&limit=1&page=last');
	await expect(page.locator('body')).toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('Black and White');
	await expect(page.locator("//fieldset[legend='Page']/b")).toHaveText('2'); // the current page, not a link
});

test('Select', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'title'});
	await page.locator('[name="where[0][val]"]').fill('Dangerous');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('1 row');
});

test('Enum', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Create type').click();
	await page.locator('[name="name"]').fill('alive');
	await setValue(page, 'as', "AS ENUM('alive', 'deceased')");
	await button(page, 'Save').click();
	await link(page, 'interprets').click();
	await link(page, 'Alter table').click();
	await page.locator('[name="add[3]"]').click();
	await page.locator('[name="fields[3.1][field]"]').fill('alive');
	await page.locator('[name="fields[3.1][type]"]').selectOption({label: 'alive'});
	await page.locator('[name="fields[3.1][null]"]').click();
	await button(page, 'Save').click();
	await link(page, 'alive').click();
	await expect(page.locator('body')).toContainText("'alive', 'deceased'");
	await button(page, 'Drop').click();
	await expect(page.locator('body')).toContainText('cannot drop type');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&edit=interprets&where[id]=1');
	await button(page, 'val-deceased').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('deceased');
});

test('Explain', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
	await link(page, 'Edit').click();
	await button(page, 'Execute').click();
	await link(page, 'Explain').click();
	await expect(page.locator('body')).toContainText('LIMITED SCAN');
});

test('Reference', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
	await link(page, '1').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public');
	await page.locator('[name="query"]').fill('Jackson');
	await page.locator('[name="search"]').click();
	await link(page, 'interprets').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Modify', async () => {
	// text_length=5 shortens the value, so that Ctrl+click has to load the original by AJAX
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums&order[0]=id&text_length=5');
	const td = page.locator('td[id$="[title]"]').first();
	await td.click({modifiers: ['Control']});
	const input = td.locator('textarea'); // a shortened value is edited in a textarea, it can hold newlines
	await expect(input).toHaveValue('Black and White'); // the cell displays only 'Black…'
	await input.fill('Bad');
	await page.locator('#save').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
	await expect(page.locator('body')).toContainText('Bad');
});

test('Update', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&edit=albums&where[id]=2');
	await page.locator('[name="fields[title]"]').fill('Black or White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
	await page.locator('input[name="check[]"][value="where[id]=2"]').click();
	await expect(page.locator('input[name="check[]"][value="where[id]=2"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Truncate', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
	await page.locator('[name="all"]').click();
	await expect(page.locator('[name="all"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('No rows.');
});

test('Import and export CSV', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&select=albums');
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
	// CockroachDB doesn't implement VACUUM, so only the operations of the other buttons are tested
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent(
		'CREATE SCHEMA adminer_test2; CREATE TABLE bulk_test (id int); INSERT INTO bulk_test VALUES (1)'
	));
	await button(page, 'Execute').click();
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public');
	// every operation redirects back to this page with the checkboxes cleared
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="truncate"]').click();
	await expect(page.locator('body')).toContainText('Tables have been truncated.');
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="target"]').selectOption('adminer_test2');
	await page.locator('[name="move"]').click();
	await expect(page.locator('body')).toContainText('Tables have been moved.');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=adminer_test2');
	await expect(page.locator('body')).toContainText('bulk_test');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&sql='
		+ encodeURIComponent('DROP SCHEMA adminer_test2 CASCADE'));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('SQL file import', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&import=');
	await page.locator('[name="sql_file[]"]').setInputFiles({
		name: 'test.sql',
		mimeType: 'application/sql',
		buffer: Buffer.from("INSERT INTO interprets (id, name) VALUES (99, 'Karel Gott');\n"
			+ "DELIMITER ;;\nUPDATE interprets SET name = 'Karel Gott ml.' WHERE id = 99;;\nDELIMITER ;\n"),
	});
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('2 queries executed OK.');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&sql='
		+ encodeURIComponent('SELECT * FROM interprets'));
	await page.locator('[name="limit"]').fill('10'); // without a limit, the export is done by JavaScript from the printed table
	await button(page, 'Execute').click();
	await page.locator('a[href="#export-1"]').click();
	await page.locator('#export-1 [name="format"]').selectOption('csv');
	await page.locator('#export-1 [name="export"]').click();
	await expect(page.locator('body')).toContainText('Karel Gott ml.');
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&sql='
		+ encodeURIComponent('DELETE FROM interprets WHERE id = 99'));
	await button(page, 'Execute').click();
});

test('Export', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&dump=');
	await page.locator('[name="output"]').first().click();
	await page.locator('[name="format"]').first().click();
	await page.locator('[name="table_style"]').selectOption({label: 'DROP+CREATE'});
	await page.locator('[name="data_style"]').selectOption({label: 'INSERT'});
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('CREATE TABLE "public"."interprets"');
	await expect(page.locator('body')).toContainText('INSERT INTO "interprets"');
	await expect(page.locator('body')).toContainText('VIEW "public"."albums_interprets"');
	// several tables in a non-SQL format are packed to a TAR archive built in a temporary file
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&dump=');
	await page.locator('input[name="output"][value="text"]').click();
	await page.locator('input[name="format"][value="csv"]').click();
	const [download] = await Promise.all([
		page.waitForEvent('download'),
		button(page, 'Export').click(),
	]);
	expect(download.suggestedFilename()).toBe('adminer_test.tar');
});

test('Procedures', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&procedure=');
	await page.locator('[name="add[0]"]').click();
	await page.locator('[name="fields[1][field]"]').fill('interpret_name');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'character varying'});
	await page.locator('[name="fields[1][length]"]').fill('50');
	await page.locator('[name="fields[1.1][field]"]').fill('album_title');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'character varying'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await setValue(page, 'definition', 'SELECT id FROM interprets;');
	await page.locator('[name="name"]').fill('insert_album');
	await page.locator('[name="language"]').selectOption({label: 'sql'}); // submits the form
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Routine has been created.');
	await link(page, 'insert_album').click();
	await page.locator('[name="fields[interpret_name]"]').fill('Michael Jackson');
	await page.locator('[name="fields[album_title]"]').fill('Dangerous');
	await button(page, 'Call').click();
	await expect(page.locator('body')).toContainText('Routine has been called,');
	await link(page, 'public').click();
	await link(page, 'Alter').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Routine has been dropped.');
	await goto(page, '/adminer/?pgsql=localhost%3A26257&username=ODBC&db=adminer_test&ns=public&sql=DROP+PROCEDURE+%22insert_album%22');
	await button(page, 'Execute').click();
});

test('Generated columns', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&create=');
	await page.locator('[name="name"]').fill('generated');
	await page.locator('[name="fields[1][field]"]').fill('normal');
	await page.locator('[name="fields[1.1][field]"]').fill('stored');
	await page.locator('[name="fields[1.1][generated]"]').selectOption({label: 'STORED'});
	await page.locator('[name="fields[1.1][default]"]').fill('normal + 200');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('normal + 200');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[2][columns][1]"]').selectOption({label: 'stored'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
	await link(page, 'New item').click();
	await expect(page.locator('[name="fields[stored]"]')).toHaveCount(0);
	await page.locator('[name="fields[normal]"]').fill('20');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('220');
});

test('Scheme', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Alter schema').click();
	await page.locator('[name="name"]').fill('public');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Schema: public');
});

test('Drop', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public');
	await page.locator('#check-all').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('Partitioning', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public&create=');
	await page.locator('[name="name"]').fill('range');
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await link(page, 'Partition by').click();
	await page.locator('[name="partition_by"]').selectOption({label: 'RANGE'});
	await page.locator('[name="partition"]').fill('id');
	await page.locator('[name="partition_names[]"]').fill('old');
	await page.locator('[name="partition_values[]"]').first().fill('10');
	await page.locator("//table[@id='partition-table']/tbody/tr[2]/td/input").first().fill('new');
	await page.locator("//table[@id='partition-table']/tbody/tr[2]/td[2]/input").fill('MAXVALUE');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('PARTITION BY RANGE(id)');
	await expect(page.locator('body')).toContainText('PARTITION "old" VALUES FROM (MINVALUE) TO (10)');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('list');
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await link(page, 'Partition by').click();
	await page.locator('[name="partition_by"]').selectOption({label: 'LIST'});
	await page.locator('[name="partition"]').fill('id');
	await page.locator('[name="partition_names[]"]').fill('odd');
	await page.locator('[name="partition_values[]"]').first().fill('1,3,5');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('PARTITION BY LIST(id)');
	await expect(page.locator('body')).toContainText('PARTITION "odd" VALUES IN (1,3,5)');
	await link(page, 'public').click();
	await page.locator('input[name="tables[]"][value="list"]').click();
	await page.locator('input[name="tables[]"][value="range"]').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('Variables', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&variables=');
	await expect(page.locator('body')).toContainText('crdb_version');
});

test('SQL command', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC&sql=SELECT+122%2B1');
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('123');
});

test('Logout', async () => {
	await goto(page, '/adminer/?pgsql=localhost:26257&username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
