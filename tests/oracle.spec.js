import {expect, test} from '@playwright/test';
import {button, expectExtension, expectNoErrors, goto, link, newPage, setValue} from './adminer.js';

test.describe.configure({mode: 'serial'}); // the tests depend on each other, e.g. on being logged in

const server = 'localhost:1521/XEPDB1'; // the Easy Connect syntax, the service name selects the pluggable database
const root = '/adminer/?oracle=' + server + '&username=ODBC';
const db = root + '&db=adminer_test';

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
	await page.locator('[name="auth[driver]"]').selectOption({label: 'Oracle'});
	await page.locator('[name="auth[server]"]').fill(server);
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await button(page, 'Login').click();
	await expectExtension(page);
	// a database is a schema, so an interrupted run is cleaned up by dropping and recreating it
	await goto(page, root);
	const check = page.locator('input[name="db[]"][value="adminer_test"]');
	if (await check.count()) {
		await check.check();
		await page.locator('[name="drop"]').click();
		await expect(page.locator('body')).toContainText('Databases have been dropped.');
	}
	await link(page, 'Create database').click();
	await page.locator('[name="name"]').fill('adminer_test');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Database has been created.');
});

test('Create table', async () => {
	await goto(page, db);
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'number'});
	await page.locator('[name="fields[1.1][field]"]').fill('name');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'varchar2'});
	await page.locator('[name="fields[1.1][length]"]').fill('50');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Create index', async () => {
	await goto(page, db + '&table=interprets');
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[1][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[1][columns][1]"]').selectOption({label: 'id'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
});

test('Create table 2', async () => {
	await goto(page, db);
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('albums');
	await page.locator('[name="fields[1][field]"]').fill('id');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'number'});
	await page.locator('[name="fields[1.1][field]"]').fill('interpret');
	await page.locator('[name="fields[1.1][type]"]').selectOption({label: 'number'});
	await page.locator('[name="fields[1.11][field]"]').fill('title');
	await page.locator('[name="fields[1.11][type]"]').selectOption({label: 'varchar2'});
	await page.locator('[name="fields[1.11][length]"]').fill('50');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
	// a primary key is needed to address a single row, Oracle has no auto increment
	await link(page, 'Alter indexes').click();
	await page.locator('[name="indexes[1][type]"]').selectOption({label: 'PRIMARY'});
	await page.locator('[name="indexes[1][columns][1]"]').selectOption({label: 'id'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Indexes have been altered.');
});

test('Foreign key', async () => {
	await goto(page, db + '&table=albums');
	await link(page, 'Create foreign key').click();
	await page.locator('[name="table"]').selectOption({label: 'interprets'});
	await page.locator('[name="source[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Foreign key has been created.');
});

test('Alter table', async () => {
	await goto(page, db + '&table=interprets');
	await link(page, 'Alter table').click();
	await page.locator('[name="add[2]"]').click();
	await page.locator('[name="fields[2.1][field]"]').fill('albums');
	await page.locator('[name="fields[2.1][type]"]').selectOption({label: 'number'});
	await page.locator('[name="defaults"]').uncheck();
	await page.locator('[name="defaults"]').click(); // reveals the default value inputs
	await page.locator('[name="fields[2.1][default]"]').fill('0');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Create view', async () => {
	await goto(page, db + '&view=');
	// Adminer quotes the identifiers so the lower case names have to be quoted in the hand written SQL too
	await setValue(page, 'select', 'SELECT "albums"."id", "albums"."title", "interprets"."name" FROM "albums" LEFT JOIN "interprets" ON "albums"."interpret" = "interprets"."id"');
	await page.locator('[name="name"]').fill('albums_interprets');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('View has been created.');
});

test('Invalid object', async () => {
	await goto(page, db + '&table=invalid');
	await expect(page.locator('body')).toContainText('Not found.');
	await goto(page, db + '&select=invalid');
	await expect(page.locator('body')).toContainText('Not found.');
	await goto(page, db + '&foreign=albums&name=invalid');
	await expect(page.locator('body')).toContainText('Not found.');
});

test('Invalid database', async () => {
	await goto(page, root + '&db=invalid');
	await expect(page.locator('body')).toContainText('Invalid database.');
});

test('Insert', async () => {
	await goto(page, db + '&edit=interprets');
	await page.locator('[name="fields[id]"]').fill('1');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been inserted.');
	await goto(page, db + '&edit=albums');
	await page.locator('[name="fields[id]"]').fill('1');
	await page.locator('[name="fields[interpret]"]').fill('1');
	await page.locator('[name="fields[title]"]').fill('Dangerous');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been inserted.');
});

test('Clone', async () => {
	await goto(page, db + '&select=albums');
	await page.locator('[name="check[]"]').click();
	await page.locator('[name="clone"]').click();
	await page.locator('[name="fields[id]"]').fill('2');
	await page.locator('[name="fields[title]"]').fill('Black and White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.'); // the clone is INSERT ... SELECT
});

test('Pagination', async () => {
	// the offset is built by a rownum column which must not be printed
	await goto(page, db + '&select=albums&order[0]=id&limit=1');
	await expect(page.locator('body')).toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('Black and White');
	await link(page, 'Load more data').click(); // appends the next page by AJAX
	await expect(page.locator('body')).toContainText('Black and White');
	await expect(page.locator('body')).not.toContainText('RNUM');
	await goto(page, db + '&select=albums&order[0]=id&limit=1&page=1');
	await expect(page.locator('body')).toContainText('Black and White');
	await expect(page.locator('body')).not.toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('RNUM');
});

test('Select', async () => {
	await goto(page, db + '&select=albums');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'title'});
	await page.locator('[name="where[0][val]"]').fill('Dangerous');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'interpret'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('Dangerous');
	await expect(page.locator('body')).not.toContainText('Black and White');
});

test('Explain', async () => {
	await goto(page, db + '&select=albums');
	await link(page, 'Edit').click();
	await button(page, 'Execute').click();
	await link(page, 'Explain').click();
	await expect(page.locator('body')).toContainText('TABLE ACCESS');
});

test('Reference', async () => {
	await goto(page, db + '&select=albums');
	await link(page, '1').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables', async () => {
	await goto(page, db);
	await page.locator('[name="query"]').fill('Jackson');
	await page.locator('[name="search"]').click();
	await link(page, 'interprets').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Search in tables with special types', async () => {
	const sql = 'CREATE TABLE "types" ("id" number PRIMARY KEY, "r" raw(4), "c" clob, "b" blob, "d" date, "t" varchar2(50))';
	await goto(page, db + '&sql=' + encodeURIComponent(sql));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	await goto(page, db + '&sql=' + encodeURIComponent(
		"INSERT INTO \"types\" VALUES (1, HEXTORAW('61626333'), 'abc3', HEXTORAW('61626333'), DATE '2020-01-03', 'abc3')"
	));
	await button(page, 'Execute').click();
	await goto(page, db);
	// the LOB and binary columns can't be compared with a string, they must be skipped instead of reported
	for (const [op, query] of [['LIKE %%', 'abc'], ['=', 'abc3']]) {
		await page.locator('[name="op"]').selectOption(op);
		await page.locator('[name="query"]').fill(query);
		await page.locator('[name="search"]').click();
		await expect(page.locator('.error')).toHaveCount(0);
		await expect(page.locator("li a[href*='select=types&where']")).toBeVisible();
	}
	await goto(page, db + '&sql=' + encodeURIComponent('DROP TABLE "types"'));
	await button(page, 'Execute').click();
});

test('Modify', async () => {
	// text_length=5 shortens the value, so that Ctrl+click has to load the original by AJAX
	await goto(page, db + '&select=albums&order[0]=id&text_length=5');
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
	await goto(page, db + '&edit=albums&where[id]=2');
	await page.locator('[name="fields[title]"]').fill('Black or White');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, db + '&select=albums');
	await page.locator('input[name="check[]"][value="where[id]=2"]').click();
	await expect(page.locator('input[name="check[]"][value="where[id]=2"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Truncate', async () => {
	await goto(page, db + '&select=albums');
	await page.locator('[name="all"]').click();
	await expect(page.locator('[name="all"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('No rows.');
});

test('Drop', async () => {
	await goto(page, db);
	await page.locator('#check-all').click();
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('SQL command', async () => {
	await goto(page, root + '&sql=' + encodeURIComponent('SELECT 122+1 FROM dual'));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('123');
});

test('Drop database', async () => {
	await goto(page, db + '&database=');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Database has been dropped.');
});

test('Logout', async () => {
	await goto(page, root);
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
