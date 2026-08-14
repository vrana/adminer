import {expect, test} from '@playwright/test';
import {button, expectNoErrors, goto, link, newPage} from './adminer.js';

test.describe.configure({mode: 'serial'}); // the tests depend on each other, e.g. on being logged in

let page;

const interprets = '[name="tables[]"][value="interprets"]'; // the server can hold other indexes, e.g. top_queries of OpenSearch

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
	await goto(page, '/tests/elastic.php?elastic=localhost');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('YOUR_PASSWORD_HERE');
	await button(page, 'Login').click();
	await expect(page.locator('#breadcrumb')).toContainText('OpenSearch'); // the login goes directly to the only database
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=');
	await expect(page.locator('body')).toContainText('JSON'); // the list of databases stays available at &db=
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data');
	if (await page.locator(interprets).count()) { // an index left by an interrupted run
		await page.locator(interprets).click();
		await page.locator('[name="drop"]').click();
		await expect(page.locator('body')).toContainText('Tables have been dropped.');
	}
});

test('Create table', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="fields[1][field]"]').fill('name');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'text'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Alter table', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&table=interprets');
	await link(page, 'Alter table').click();
	await page.locator('[name="add[2]"]').click(); // the original columns have no field input so this one submits the form and PHP adds the row
	await page.locator('[name="fields[3][field]"]').fill('albums');
	await page.locator('[name="fields[3][type]"]').selectOption({label: 'integer'});
	await page.locator('[name="add[3]"]').click(); // this one is handled by JavaScript which numbers the added row 3.1
	await page.locator('[name="fields[3.1][field]"]').fill('country');
	await page.locator('[name="fields[3.1][type]"]').selectOption({label: 'keyword'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Rename table', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&create=interprets');
	await page.locator('[name="name"]').fill('singers');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Renaming indexes is not supported.');
});

test('Insert', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await page.locator('[name="fields[country]"]').fill('US');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has been inserted.');
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Karel Gott');
	await page.locator('[name="fields[country]"]').fill('CZ');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has been inserted.');
});

test('Select a full page', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&select=interprets&limit=1&order[0]=country');
	await expect(page.locator('body')).toContainText('Karel Gott');
	await expect(page.locator('body')).not.toContainText('Michael Jackson');
	await expect(page.locator('body')).toContainText('2 rows');
	await link(page, '2').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Select', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&select=interprets');
	await link(page, 'Search').click();
	await page.locator('[name="where[0][col]"]').selectOption({label: 'name'});
	await page.locator('[name="where[0][op]"]').selectOption({label: 'should'});
	await page.locator('[name="where[0][val]"]').fill('Jackson');
	await link(page, 'Sort').click();
	await page.locator('[name="order[0]"]').selectOption({label: 'albums'});
	await button(page, 'Select').click();
	await expect(page.locator('body')).toContainText('1 row');
});

test('Update', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&select=interprets');
	await link(page, 'edit').click();
	await page.locator('[name="fields[albums]"]').fill('1');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&select=interprets');
	const check = page.locator('[name="check[]"]').first(); // the documents are identified by a generated _id and returned in an unspecified order
	await check.click();
	await expect(check).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Drop', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC&db=data&create=interprets');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('Table has been dropped.');
	await expect(page.locator(interprets)).toHaveCount(0);
});

test('Logout', async () => {
	await goto(page, '/tests/elastic.php?elastic=localhost&username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
