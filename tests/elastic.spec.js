import {expect, test} from '@playwright/test';
import {button, goto, link} from './adminer.js';

test.describe.configure({mode: 'serial'}); // the tests depend on each other, e.g. on being logged in

let page;

test.beforeAll(async ({browser}) => {
	page = await browser.newPage();
	page.on('dialog', dialog => dialog.accept());
});

test.afterAll(async () => {
	await page.close();
});

test('Login', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('ODBC12');
	await button(page, 'Login').click();
	await expect(page.locator('body')).toContainText('JSON');
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic');
	if (await page.locator('#check-all').count()) { // tables left by an interrupted run
		await page.locator('#check-all').click();
		await page.locator('[name="drop"]').click();
		await expect(page.locator('body')).toContainText('No tables.');
	}
});

test('Create table', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic');
	await link(page, 'Create table').click();
	await page.locator('[name="name"]').fill('interprets');
	await page.locator('[name="fields[1][field]"]').fill('name');
	await page.locator('[name="fields[1][type]"]').selectOption({label: 'text'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been created.');
});

test('Alter table', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&table=interprets');
	await link(page, 'Alter table').click();
	await page.locator('[name="add[2]"]').click();
	await page.locator('[name="fields[3][field]"]').fill('albums');
	await page.locator('[name="fields[3][type]"]').selectOption({label: 'integer'});
	await page.locator('[name="add[3]"]').click();
	await page.locator('[name="fields[4][field]"]').fill('country');
	await page.locator('[name="fields[4][type]"]').selectOption({label: 'keyword'});
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Table has been altered.');
});

test('Rename table', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&create=interprets');
	await page.locator('[name="name"]').fill('singers');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Elasticsearch does not support renaming indexes.');
});

test('Insert', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Michael Jackson');
	await page.locator('[name="fields[country]"]').fill('US');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has been inserted.');
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&edit=interprets');
	await page.locator('[name="fields[name]"]').fill('Karel Gott');
	await page.locator('[name="fields[country]"]').fill('CZ');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('has been inserted.');
});

test('Select a full page', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&select=interprets&limit=1&order[0]=country');
	await expect(page.locator('body')).toContainText('Karel Gott');
	await expect(page.locator('body')).not.toContainText('Michael Jackson');
	await expect(page.locator('body')).toContainText('2 rows');
	await link(page, '2').click();
	await expect(page.locator('body')).toContainText('Michael Jackson');
});

test('Select', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&select=interprets');
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
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&select=interprets');
	await link(page, 'edit').click();
	await page.locator('[name="fields[albums]"]').fill('1');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item has been updated.');
});

test('Delete', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&select=interprets');
	await page.locator('[name="check[]"]').click();
	await expect(page.locator('[name="check[]"]')).toBeChecked();
	await page.locator('[name="delete"]').click();
	await expect(page.locator('body')).toContainText('1 item has been affected.');
});

test('Drop', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC&db=elastic&create=interprets');
	await page.locator('[name="drop"]').click();
	await expect(page.locator('body')).toContainText('No tables.');
});

test('Logout', async () => {
	await goto(page, '/adminer/elastic.php?elastic=https%3A%2F%2Flocalhost:9200&username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
