import {expect, test} from '@playwright/test';
import {button, expectExtension, expectNoErrors, extension, goto, link, newPage, setValue} from './adminer.js';

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
	await page.locator('[name="auth[driver]"]').selectOption({label: 'MS SQL'});
	await page.locator('[name="auth[server]"]').fill('');
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await button(page, 'Login').click();
	await expectExtension(page);
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
	if (await page.locator('#check-all').count()) { // tables left by an interrupted run, the user can't recreate the database
		await page.locator('#check-all').click();
		await page.locator('[name="drop"]').click();
		await expect(page.locator('body')).toContainText('No tables.');
	}
});

test('Create table', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
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
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&table=interprets');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[2][columns][1]"]').selectOption({label: 'name'});
	await expect(page.locator('[name="indexes[2][name]"]')).toHaveValue('interprets_name');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText("Table 'interprets' already has a primary key defined on it.");
	await page.locator('[name="indexes[2][type]"]').selectOption({label: 'INDEX'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
});

test('Create table 2', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&table=interprets');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('albums');
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
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&table=albums');
	await link(page, 'Create foreign key').click();
	await page.locator('[name="table"]').selectOption({label: 'interprets'});
	await page.locator('[name="source[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Foreign key has been created.');
});

test('Alter table', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&table=interprets');
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

test('Check constraints', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&table=albums');
	await link(page, 'Create check').click();
	await page.locator('[name="name"]').fill('albums_interpret_check');
	await setValue(page, 'clause', 'interpret > 0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Check has been created.');
	await link(page, 'New item').click();
	await page.locator('[name="fields[interpret]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('statement conflicted with the CHECK constraint');
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&check=albums&name=albums_interpret_check');
	await expect(page.locator('body')).toContainText('([interpret]>(0))');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Check has been dropped.');
});

test('Create view', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&view=');
	await setValue(page, 'select', 'SELECT albums.id, albums.title, interprets.name FROM albums LEFT JOIN interprets ON albums.interpret = interprets.id');
	await page.locator('[name="name"]').fill('albums_interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('View has been created.');
});

test('Invalid table', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&table=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&create=invalid');
	await expect(page.locator('body')).toContainText('No tables.');
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=invalid');
	await expect(page.locator('body')).toContainText('Unable to select the table:');
});

test('Schema', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&schema=');
	await expect(page.locator('body')).toContainText('Permanent link');
});

test('Insert', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 1 has been inserted.');
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&edit=albums');
	await page.locator('[name="fields[interpret]"]').fill('1');
	await page.locator('[name="fields[title]"]').fill('Dangerous');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 2 has been inserted.');
});

test('Clone', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=albums');
	await page.locator('[name="check[]"]').click();
	await page.locator('[name="clone"]').click();
	await page.locator('[name="fields[title]"]').fill('Black and White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 3 has been inserted.');
});

test('Select', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=albums');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'title'});
	await page.locator('[name="where[0][val]"]').fill('Dangerous');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('1 row');
});

test('Explain', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=albums');
	await link(page, 'Edit').click();
	await button(page, 'Execute').click();
	if (!extension()) { // MS SQL doesn't support EXPLAIN through PDO
		await link(page, 'Explain').click();
		await expect(page.locator('body')).toContainText('Clustered Index Scan');
	}
});

test('Reference', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=albums');
	await link(page, '1').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
	await page.locator('[name="query"]').fill('Jackson');
	await page.locator('[name="search"]').click();
	await link(page, 'interprets').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables with special types', async () => {
	const sql = 'CREATE TABLE types (id int PRIMARY KEY, b bit, bin binary(4), tx text, ntx ntext, x xml,'
		+ ' u uniqueidentifier, sd smalldatetime, g geography, t varchar(50));'
		+ " INSERT INTO types VALUES (1, 1, 0x61626333, 'abc3', N'abc3', '<a>abc3</a>',"
		+ " '00000000-0000-0000-0000-000000000003', '2020-01-03 12:34:00', geography::Point(3, 4, 4326), 'abc3')";
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&sql=' + encodeURIComponent(sql));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	for (const [op, query] of [['LIKE %%', 'abc'], ['LIKE %%', '3'], ['=', 'abc3']]) {
		await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
		await page.locator('[name="op"]').selectOption(op);
		await page.locator('[name="query"]').fill(query);
		await page.locator('[name="search"]').click();
		await expect(page.locator('.error')).toHaveCount(0); // a column which can't be searched must be skipped, not reported
		await expect(page.locator("li a[href*='select=types&where']")).toBeVisible(); // the list of the tables holding the value
	}
	// whether these values are found depends on the types of the driver, only the missing error is checked
	for (const query of ['2020-01-03', '12:34:56', 'ěščř']) {
		await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
		await page.locator('[name="query"]').fill(query);
		await page.locator('[name="search"]').click();
		await expect(page.locator('.error')).toHaveCount(0);
	}
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&sql=' + encodeURIComponent('DROP TABLE types'));
	await button(page, 'Execute').click();
});

test('Update', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&edit=albums&where[id]=2');
	await page.locator('[name="fields[title]"]').fill('Black or White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=albums');
	await page.locator('input[name="check[]"][value="where[id]=2"]').click();
	await expect(page.locator('input[name="check[]"][value="where[id]=2"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Truncate', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&select=albums');
	await page.locator('[name="all"]').click();
	await expect(page.locator('[name="all"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('No rows.');
});

test('Export', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&dump=');
	await page.locator('[name="output"]').first().click();
	await page.locator('[name="format"]').first().click();
	await page.locator('[name="table_style"]').selectOption({label: 'DROP+CREATE'});
	await page.locator('[name="data_style"]').selectOption({label: 'INSERT'});
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('CREATE TABLE [dbo].[interprets]');
	await expect(page.locator('body')).toContainText('INSERT INTO [dbo].[interprets]');
	await expect(page.locator('body')).toContainText('VIEW [dbo].[albums_interprets]');
});

test('Generated columns', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo&create=');
	await page.locator('[name="name"]').fill('generated');
	await page.locator('[name="fields[1][field]"]').fill('normal');
	await page.locator('[name="fields[1.1][field]"]').fill('virtual');
	await page.locator('[name="fields[1.1][generated]"]').selectOption({label: 'VIRTUAL'});
	await page.locator('[name="fields[1.1][default]"]').fill('normal + 100');
	await page.locator('[name="fields[1.11][field]"]').fill('stored');
	await page.locator('[name="fields[1.11][generated]"]').selectOption({label: 'PERSISTED'});
	await page.locator('[name="fields[1.11][default]"]').fill('normal + 200');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('[normal]+(100)');
	await expect(page.locator('body')).toContainText('[normal]+(200)');
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

test('Scheme', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
	await link(page, 'Alter schema').click();
	await page.locator('[name="name"]').fill('dbo');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Schema: dbo');
});

test('Drop', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo');
	await page.locator('#check-all').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('SQL command', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC&sql=SELECT+122%2B1');
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('123');
});

test('Logout', async () => {
	await goto(page, '/adminer/?mssql=&username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
