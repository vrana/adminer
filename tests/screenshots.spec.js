import {test} from '@playwright/test';
import {button, expectNoErrors, goto, newPage} from './adminer.js';

// Screenshots published on the website, cropped afterwards by screenshots.php - see README.md.

test.describe.configure({mode: 'serial'}); // the tests depend on each other, e.g. on being logged in

const dir = 'tests/screenshots/'; // Playwright resolves the path against the repository root, where it is started

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

/** Save a screenshot of the whole page
* @param {string} name without the extension
*/
async function screenshot(name) {
	await page.screenshot({path: dir + name + '.png', fullPage: true});
}

test('Login', async () => {
	await goto(page, '/adminer/');
	await page.locator('[name="lang"]').selectOption({label: 'English'}); // submits the form
	await page.locator('#username').fill('ODBC');
	await page.locator('[name="auth[permanent]"]').check();
	await screenshot('auth');
	await page.locator('[name="auth[password]"]').fill('ODBC');
	await page.locator('[name="auth[db]"]').fill('adminer_demo');
	await button(page, 'Login').click();
	await screenshot('db');
});

test('Table', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&table=posts');
	await screenshot('table');
	await page.emulateMedia({colorScheme: 'dark'}); // dark.css is linked with this media query
	await screenshot('dark');
	await page.emulateMedia({colorScheme: 'light'});
});

test('Alter table', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&create=posts');
	await page.locator('[name="comments"]').check(); // displays the column
	await page.locator('[name="defaults"]').check();
	await screenshot('create');
});

test('Select', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&select=comments');
	await screenshot('select');
});

test('Edit', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&edit=posts&where[id]=1');
	await page.locator('[name="fields[title]"]').click(); // focuses the field
	await screenshot('edit');
});

test('Databases', async () => {
	await goto(page, '/adminer/?username=ODBC&dbsize=1');
	await screenshot('database');
});

test('Schema', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&schema=comments:0x19_post_tags:9x0_posts:5x8_tags:15x0_users:19x18');
	await screenshot('schema');
});

test('Export', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&dump=');
	await screenshot('dump');
});

test('SQL command', async () => {
	await goto(page, '/adminer/?username=ODBC&db=adminer_demo&sql=SELECT+%2A%0AFROM+%60users%60%0ALIMIT+50');
	await button(page, 'Execute').click();
	await screenshot('sql');
});
