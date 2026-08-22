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
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await button(page, 'Login').click();
	await expectExtension(page);
	await link(page, 'SQL command').click();
	await goto(page, '/adminer/?pgsql=&username=ODBC&sql=DROP+DATABASE+IF+EXISTS+adminer_test%3BCREATE+DATABASE+adminer_test');
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('Create table', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'integer'});
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await page.locator('[name="fields[1.1][field]"]').fill('name');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'character varying'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await page.locator('[name="fields[1.11][field]"]').fill('surname');
	await page.locator('[name="fields[1.11][type]"]').selectOption({label: 'character varying'});
	await page.locator('[name="fields[1.11][length]"]').fill('50');
	await page.locator('[name="comments"]').uncheck();
	await page.locator('[name="comments"]').click();
	await page.locator('[name="fields[1.1][comment]"]').fill('Interpret');
	await page.locator('[name="Comment"]').fill('Interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Create index', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&table=interprets');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[2][columns][1]"]').selectOption({label: 'name'});
	await expect(page.locator('[name="indexes[2][name]"]')).toHaveValue('interprets_name');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('multiple primary keys for table "interprets" are not allowed');
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'INDEX'});
	await page.locator('input[name="options"]').uncheck();
	await page.locator('input[name="options"]').click();
	await page.locator('[name="indexes[3][type]"]').selectOption({label: 'INDEX'});
	await page.locator('[name="indexes[3][columns][1]"]').selectOption({label: 'surname'});
	await page.locator('[name="indexes[3][algorithm]"]').selectOption({label: 'hash'});
	await page.locator('[name="indexes[3][partial]"]').fill('surname IS NOT NULL');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
	await expect(page.locator('body')).toContainText('INDEX (hash)');
	await expect(page.locator('body')).toContainText('WHERE surname IS NOT NULL');
});

test('Create table 2', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&table=interprets');
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
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&table=albums');
	await link(page, 'Create foreign key').click();
	await page.locator('[name="table"]').selectOption({label: 'interprets'});
	await page.locator('[name="source[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Foreign key has been created.');
});

test('Alter table', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&table=interprets');
	await link(page, 'Alter table').click();
	await page.locator('[name="add[3]"]').click();
	await page.locator('[name="fields[3.1][field]"]').fill('albums');
	await page.locator('[name="fields[3.1][type]"]').selectOption({label: 'integer'});
	await page.locator('[name="fields[3.1][length]"]').fill('');
	await page.locator('[name="defaults"]').uncheck();
	await page.locator('[name="defaults"]').click();
	await page.locator('[name="fields[3.1][default]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Check constraints', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&table=albums');
	await link(page, 'Create check').click();
	await page.locator('[name="name"]').fill('albums_interpret_check');
	await setValue(page, 'clause', 'interpret > 0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Check has been created.');
	await link(page, 'New item').click();
	await page.locator('[name="fields[interpret]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('violates check constraint');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&check=albums&name=albums_interpret_check');
	await expect(page.locator('body')).toContainText('(interpret > 0)');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Check has been dropped.');
});

test('Create view', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('albums_interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('View has been created.');
});

test('Materialized view', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('materialized_view');
	await page.locator('[name="materialized"]').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Materialized view');
});

test('Invalid table', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&table=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&create=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=invalid');
	await expect(page.locator('body')).toContainText('Unable to select the table:');
});

test('Schema', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&schema=');
	await expect(page.locator('body')).toContainText('Permanent link');
});

test('Insert', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 1 has been inserted.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&edit=albums');
	await page.locator('[name="fields[interpret]"]').fill('1');
	await page.locator('[name="fields[title]"]').fill('Dangerous');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 2 has been inserted.');
});

test('Clone', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
	await page.locator('[name="check[]"]').click();
	await page.locator('[name="clone"]').click();
	await page.locator('[name="fields[title]"]').fill('Black and White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 3 has been inserted.');
});

test('Pagination', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums&order[0]=id&limit=1');
	await expect(page.locator('body')).toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('Black and White');
	await expect(page.locator('body')).toContainText('2 rows');
	await link(page, 'Load more data').click(); // appends the next page by AJAX
	await expect(page.locator('body')).toContainText('Black and White');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums&order[0]=id&limit=1&page=last');
	await expect(page.locator('body')).toContainText('Black and White');
	await expect(page.locator('body')).not.toContainText('Dangerous');
	await expect(page.locator("//fieldset[legend='Page']/b")).toHaveText('2'); // the current page, not a link
});

test('Select', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'title'});
	await page.locator('[name="where[0][val]"]').fill('Dangerous');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('1 row');
});

test('Types', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Create type').click();
	await page.locator('[name="name"]').fill('genre');
	await setValue(page, 'as', "AS ENUM ('rock', 'pop')");
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Type has been created.');
	await link(page, 'genre').click();
	await page.locator('[name="name"]').fill('genres');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Type has been altered.');
	await link(page, 'genres').click();
	await setValue(page, 'as', "AS ENUM ('rock', 'jazz', 'pop')");
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Type has been altered.');
	await link(page, 'genres').click();
	await expect(page.locator('body')).toContainText("'rock', 'jazz', 'pop'");
	await setValue(page, 'as', "AS ENUM ('rock', 'jazz', 'pop', 'jazz')");
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('enum label "jazz" already exists');
	await button(page, 'Drop').click();
	await expect(page.locator('body')).toContainText('Type has been dropped.');
});

test('Enum', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Create type').click();
	await page.locator('[name="name"]').fill('alive');
	await setValue(page, 'as', "AS ENUM('alive', 'deceased')");
	await button(page, 'Save').click();
	await link(page, 'interprets').click();
	await link(page, 'Alter table').click();
	await page.locator('[name="add[4]"]').click();
	await page.locator('[name="fields[4.1][field]"]').fill('alive');
	await page.locator('[name="fields[4.1][type]"]').selectOption({label: 'alive'});
	await page.locator('[name="fields[4.1][null]"]').click();
	await button(page, 'Save').click();
	await link(page, 'alive').click();
	await expect(page.locator('body')).toContainText("'alive', 'deceased'");
	await button(page, 'Drop').click();
	await expect(page.locator('body')).toContainText('cannot drop type');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&edit=interprets&where[id]=1');
	await button(page, 'val-deceased').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('deceased');
});

test('Composite type', async () => {
	const sql = "CREATE TYPE composite_key AS (a int, b text);"
		+ " CREATE TABLE composites (id composite_key PRIMARY KEY, val text);"
		+ " INSERT INTO composites VALUES (ROW(1, 'x'), 'one'), (ROW(2, NULL), 'two')";
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent(sql));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	const select = '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=composites&order[0]=id';
	await goto(page, select);
	await link(page, 'edit').click(); // the row is identified by the composite value, which has to be cast in the condition
	await expect(page.locator('[name="fields[val]"]')).toHaveValue('one');
	await page.locator('[name="fields[val]"]').fill('uno');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
	await goto(page, select);
	await page.locator('input[name="check[]"]').nth(1).click(); // (2,) - the composite comparison considers a NULL member equal to a NULL member
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'composite_key').click();
	await page.locator('[name="name"]').fill('composite_id'); // renaming must not re-create the type used by the table
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Type has been altered.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent('DROP TABLE composites; DROP TYPE composite_id'));
	await button(page, 'Execute').click();
});

test('Explain', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
	await link(page, 'Edit').click();
	await button(page, 'Execute').click();
	await link(page, 'Explain').click();
	await expect(page.locator('body')).toContainText('Seq Scan');
});

test('Reference', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
	await link(page, '1').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await page.locator('[name="query"]').fill('Jackson');
	await page.locator('[name="search"]').click();
	await link(page, 'interprets').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables with special types', async () => {
	const sql = "CREATE TYPE mood AS ENUM ('abc3', 'x');"
		+ " CREATE TABLE types (id int PRIMARY KEY, b bytea, o boolean, tm time, r int4range, u uuid, e mood, a text[], t text);"
		+ " INSERT INTO types VALUES (1, 'abc3', true, '12:34:56', '[1,3)', '00000000-0000-0000-0000-000000000003', 'abc3', '{abc3,x}', 'abc3')";
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent(sql));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	for (const [op, query] of [['LIKE %%', 'abc'], ['LIKE %%', '3'], ['=', 'abc3'], ['~', 'abc'], ['ILIKE %%', 'ABC']]) {
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
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent('DROP TABLE types; DROP TYPE mood'));
	await button(page, 'Execute').click();
});

test('Modify', async () => {
	// text_length=5 shortens the value, so that Ctrl+click has to load the original by AJAX
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums&order[0]=id&text_length=5');
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
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&edit=albums&where[id]=2');
	await page.locator('[name="fields[title]"]').fill('Black or White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
	await page.locator('input[name="check[]"][value="where[id]=2"]').click();
	await expect(page.locator('input[name="check[]"][value="where[id]=2"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Truncate', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
	await page.locator('[name="all"]').click();
	await expect(page.locator('[name="all"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('No rows.');
});

test('Import and export CSV', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=albums');
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
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent(
		'CREATE SCHEMA adminer_test2; CREATE TABLE bulk_test (id int); INSERT INTO bulk_test VALUES (1)'
	));
	await button(page, 'Execute').click();
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	// every operation redirects back to this page with the checkboxes cleared
	for (const [name, label] of [['', 'Vacuum'], ['optimize', 'Optimize']]) {
		await page.locator('input[name="tables[]"][value="albums"]').check();
		await (name ? page.locator('[name="' + name + '"]') : button(page, label)).click();
		await expect(page.locator('body')).toContainText('Tables have been optimized.');
	}
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="truncate"]').click();
	await expect(page.locator('body')).toContainText('Tables have been truncated.');
	await page.locator('input[name="tables[]"][value="bulk_test"]').check();
	await page.locator('[name="target"]').selectOption('adminer_test2');
	await page.locator('[name="move"]').click();
	await expect(page.locator('body')).toContainText('Tables have been moved.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=adminer_test2');
	await expect(page.locator('body')).toContainText('bulk_test');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql='
		+ encodeURIComponent('DROP SCHEMA adminer_test2 CASCADE'));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('SQL file import', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&import=');
	await page.locator('[name="sql_file[]"]').setInputFiles({
		name: 'test.sql',
		mimeType: 'application/sql',
		buffer: Buffer.from("INSERT INTO interprets (name, surname) VALUES ('Karel', 'Gott');\n"
			+ "DELIMITER ;;\nUPDATE interprets SET albums = 1 WHERE surname = 'Gott';;\nDELIMITER ;\n"),
	});
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('2 queries executed OK.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql='
		+ encodeURIComponent('SELECT * FROM interprets'));
	await page.locator('[name="limit"]').fill('10'); // without a limit, the export is done by JavaScript from the printed table
	await button(page, 'Execute').click();
	await page.locator('a[href="#export-1"]').click();
	await page.locator('#export-1 [name="format"]').selectOption('csv');
	await page.locator('#export-1 [name="export"]').click();
	await expect(page.locator('body')).toContainText('Karel,Gott');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql='
		+ encodeURIComponent("DELETE FROM interprets WHERE surname = 'Gott'"));
	await button(page, 'Execute').click();
});

test('Process list', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&processlist=');
	await expect(page.locator('body')).toContainText('pg_stat_activity');
});

test('Export', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&dump=');
	await page.locator('[name="output"]').first().click();
	await page.locator('[name="format"]').first().click();
	await page.locator('[name="table_style"]').selectOption({label: 'DROP+CREATE'});
	await page.locator('[name="data_style"]').selectOption({label: 'INSERT'});
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('CREATE TABLE "public"."interprets"');
	await expect(page.locator('body')).toContainText('INSERT INTO "interprets"');
	await expect(page.locator('body')).toContainText('VIEW "public"."albums_interprets"');
	// several tables in a non-SQL format are packed to a TAR archive built in a temporary file
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&dump=');
	await page.locator('input[name="output"][value="text"]').click();
	await page.locator('input[name="format"][value="csv"]').click();
	const [download] = await Promise.all([
		page.waitForEvent('download'),
		button(page, 'Export').click(),
	]);
	expect(download.suggestedFilename()).toBe('adminer_test.tar');
});

test('Procedures', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&procedure=');
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
	await link(page, 'Options').click(); // the fieldset is hidden
	await page.locator('[name="options[SECURITY]"]').selectOption({label: 'DEFINER'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Routine has been created.');
	await link(page, 'insert_album').click();
	await page.locator('[name="fields[interpret_name]"]').fill('Michael Jackson');
	await page.locator('[name="fields[album_title]"]').fill('Dangerous');
	await button(page, 'Call').click();
	await expect(page.locator('body')).toContainText('Routine has been called,');
	await link(page, 'public').click();
	await link(page, 'Alter').click();
	await expect(page.locator('#fieldset-options')).toBeVisible(); // the characteristics are not default
	await expect(page.locator('[name="options[SECURITY]"]')).toHaveValue('SECURITY DEFINER');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Routine has been dropped.');
});

test('Generated columns', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&create=');
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

test('Sequences', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'albums_id_seq').click();
	await page.locator('[name="name"]').fill('albums_id_seq2');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Sequence has been altered.');
});

test('Scheme', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await link(page, 'Alter schema').click();
	await page.locator('[name="name"]').fill('public');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Schema: public');
});

test('Drop', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public');
	await page.locator('#check-all').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('Partitioning', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&create=');
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
	await expect(page.locator('body')).toContainText('"range_old" PARTITION OF "range" FOR VALUES FROM (MINVALUE) TO (10)');
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
	await expect(page.locator('body')).toContainText('"list_odd" PARTITION OF "list" FOR VALUES IN (1,3,5)');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('hash');
	await page.locator('input[name="auto_increment_col"][value="1"]').click();
	await link(page, 'Partition by').click();
	await page.locator('[name="partition_by"]').selectOption({label: 'HASH'});
	await page.locator('[name="partition"]').fill('id');
	await page.locator('[name="partitions"]').fill('4');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('PARTITION BY HASH(id)');
	await expect(page.locator('body')).toContainText('"hash_0" PARTITION OF "hash" FOR VALUES WITH (MODULUS 4, REMAINDER 0)');
	await link(page, 'hash_0').click();
	await expect(page.locator('body')).toContainText('Inherits from');
	await link(page, 'public').click();
	await page.locator('input[name="tables[]"][value="hash"]').click();
	await page.locator('input[name="tables[]"][value="list"]').click();
	await page.locator('input[name="tables[]"][value="range"]').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('Partitioned rows', async () => {
	const sql = "CREATE TABLE parts (id int, val text) PARTITION BY LIST (id);"
		+ " CREATE TABLE parts_1 PARTITION OF parts FOR VALUES IN (1);"
		+ " CREATE TABLE parts_2 PARTITION OF parts FOR VALUES IN (2);"
		+ " INSERT INTO parts VALUES (1, 'one'), (2, 'two')"; // both rows are the first one in their partition, so they share the ctid
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent(sql));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	const select = '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&select=parts&order[0]=id';
	await goto(page, select);
	await link(page, 'edit').click(); // the table has no unique key, so the row is identified by the ctid, which is unique only within a partition
	await page.locator('[name="fields[val]"]').fill('uno');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
	await goto(page, select);
	await expect(page.locator('body')).toContainText('two'); // the row in the other partition must keep its value
	await page.locator('input[name="check[]"]').first().click();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
	await goto(page, '/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=' + encodeURIComponent('DROP TABLE parts'));
	await button(page, 'Execute').click();
});

test('Variables', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&variables=');
	await expect(page.locator('body')).toContainText('autovacuum');
});

test('SQL command', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC&sql=SELECT+122%2B1');
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('123');
});

test('COPY from stdin', async () => {
	await goto(page, "/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public&sql=CREATE+TABLE+copy_test+(id+int%2C+name+varchar)%3B%0ACOPY+copy_test+FROM+stdin%3B%0A1%09a%3B+b%0A2%09c%0A%5C.%0ASELECT+'copied+'+%7C%7C+count(*)+%7C%7C+'+'+%7C%7C+min(name)+AS+test+FROM+copy_test%3B%0ADROP+TABLE+copy_test%3B");
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK, 2 rows affected.');
	await expect(page.locator('body')).toContainText('copied 2 a; b');
});

test('Logout', async () => {
	await goto(page, '/adminer/?pgsql=&username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
