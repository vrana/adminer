import {expect, test} from '@playwright/test';
import {button, expectNoErrors, goto, link, newPage} from './adminer.js';

// The plugins are instantiated by tests/plugins.php, not by the local adminer-plugins/ directory.
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
	await goto(page, '/tests/plugins.php');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await button(page, 'Login').click();
	await expect(page.locator('body')).toContainText('Logged in as');
	await goto(page, '/tests/plugins.php?username=ODBC&sql='
		+ encodeURIComponent('DROP DATABASE IF EXISTS adminer_test; CREATE DATABASE adminer_test'));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
	// the tables are created by SQL, the structure itself is tested by mysql.spec.js
	await goto(page, '/tests/plugins.php?username=ODBC&db=adminer_test&sql=' + encodeURIComponent(
		'CREATE TABLE interprets (id int PRIMARY KEY AUTO_INCREMENT, name varchar(50));'
		+ ' CREATE TABLE albums (id int PRIMARY KEY AUTO_INCREMENT, interpret int, title varchar(50),'
		+ ' FOREIGN KEY (interpret) REFERENCES interprets (id));'
		+ " INSERT INTO interprets (name) VALUES ('Michael Jackson');"
		+ " INSERT INTO albums (interpret, title) VALUES (1, 'Dangerous')"
	));
	await button(page, 'Execute').click();
	await expect(page.locator('body')).toContainText('Query executed OK');
});

test('Export formats', async () => {
	await goto(page, '/tests/plugins.php?username=ODBC&db=adminer_test&dump=');
	// dumpFormat() and dumpOutput() are aggregated across all plugins, not stopped by the first one
	await expect(page.locator('input[name="format"][value="json"]')).toHaveCount(1);
	await expect(page.locator('input[name="format"][value="xml"]')).toHaveCount(1);
	await expect(page.locator('input[name="output"][value="zip"]')).toHaveCount(1);
	await page.locator('input[name="output"][value="text"]').click();
	await page.locator('input[name="format"][value="json"]').click();
	await page.locator('[name="data_style"]').selectOption({label: 'INSERT'});
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('"albums": [');
	await expect(page.locator('body')).toContainText('"title": "Dangerous"');
	await goto(page, '/tests/plugins.php?username=ODBC&db=adminer_test&dump=');
	await page.locator('input[name="format"][value="xml"]').click();
	await button(page, 'Export').click();
	await expect(page.locator('body')).toContainText('<database name="adminer_test">');
});

test('Import CSV', async () => {
	await goto(page, '/tests/plugins.php?username=ODBC&db=adminer_test&import=');
	await page.locator('[name="csv_file[]"]').setInputFiles({
		name: 'singers.csv',
		mimeType: 'text/csv',
		buffer: Buffer.from('id,name\r\n1,"Karel Gott"\r\n'),
	});
	await page.locator('[name="csv_exists"]').selectOption('drop');
	await page.locator('[name="csv"]').click(); // the plugin creates the table from the file
	await goto(page, '/tests/plugins.php?username=ODBC&db=adminer_test&select=singers');
	await expect(page.locator('body')).toContainText('Karel Gott');
});

test('Edit foreign', async () => {
	await goto(page, '/tests/plugins.php?username=ODBC&db=adminer_test&edit=albums');
	// the plugin replaces the input by a list of the referenced values
	await expect(page.locator('select[name="fields[interpret]"]')).toHaveCount(1);
	await page.locator('[name="fields[interpret]"]').selectOption('1');
	await page.locator('[name="fields[title]"]').fill('Bad');
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Item 2 has been inserted.');
});

test('Configuration', async () => {
	await goto(page, '/tests/plugins.php?username=ODBC'); // the link is printed below the list of the plugins
	await link(page, 'Configuration').click();
	await page.locator('input[name="config[design]"][value="builtin"]').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Configuration saved.');
	await goto(page, '/tests/plugins.php?username=ODBC&config=');
	await expect(page.locator('input[name="config[design]"][value="builtin"]')).toBeChecked();
	await page.locator('input[name="config[design]"][value=""]').click();
	await button(page, 'Save').click();
	await expect(page.locator('body')).toContainText('Configuration saved.');
});

test('Logout', async () => {
	await goto(page, '/tests/plugins.php?username=ODBC');
	await page.locator('[name="logout"]').click();
	await expect(page.locator('body')).toContainText('Logout successful.');
});
