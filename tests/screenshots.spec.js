import {test} from '@playwright/test';
import {button, expectNoErrors, goto, newPage} from './adminer.js';

// Screenshots published on the website, cropped afterwards by bin/screenshots.php of the website repository - see README.md.

test.describe.configure({mode: 'serial'}); // the tests depend on each other, e.g. on being logged in

const dir = 'tests/screenshots/'; // Playwright resolves the path against the repository root, where it is started

let page;

test.beforeAll(async ({browser}) => {
	page = await newPage(browser, {deviceScaleFactor: 2}); // the pictures are displayed in half the size
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
	await page.locator('#username').fill('adminer');
	await page.locator('[name="auth[permanent]"]').check();
	await screenshot('auth');
	await page.locator('[name="auth[password]"]').fill('adminer');
	await page.locator('[name="auth[db]"]').fill('adminer_demo');
	await button(page, 'Login').click();
	await page.locator('[name="tables[]"][value="posts"]').check();
	await screenshot('db');
});

test('Table', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&create=posts');
	await page.locator('[name="fields[1][length]"]').fill('11'); // altering the table displays a message on the table page
	await button(page, 'Save').click();
	await screenshot('table');
	await page.emulateMedia({colorScheme: 'dark'}); // dark.css is linked with this media query
	await screenshot('dark');
	await page.emulateMedia({colorScheme: 'light'});
});

test('Alter table', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&create=posts');
	await page.locator('[name="comments"]').uncheck(); // hides the column, the previous test saved it checked
	await page.locator('[name="defaults"]').uncheck();
	await page.locator('[name="fields[2][type]"]').selectOption({label: 'users'}); // the foreign key of the author column
	await screenshot('create');
});

test('Select', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&select=comments&text_length=15');
	await page.locator('[name="check[]"]').nth(6).check(); // the rows with id 7 and 8
	await page.locator('[name="check[]"]').nth(7).check();
	await page.getByRole('cell', {name: 'gregor', exact: true}).click({modifiers: ['Control']}); // displays the inline edit field
	await screenshot('select');
});

test('Edit', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&edit=posts&where[id]=1');
	await page.locator('[name="fields[title]"]').click(); // focuses the field
	await screenshot('edit');
});

test('Databases', async () => {
	await goto(page, '/adminer/?username=adminer&dbsize=1');
	await page.locator('[name="db[]"][value="adminer_demo"]').check();
	await screenshot('database');
});

test('Schema', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&schema=comments:0x19_post_tags:9x0_posts:5x8_tags:15x0_users:19x18');
	await screenshot('schema');
});

test('Export', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&dump=');
	await screenshot('dump');
});

test('SQL command', async () => {
	await goto(page, '/adminer/?username=adminer&db=adminer_demo&sql=SELECT+id%2C+name%2C+email%2C+role%0AFROM+users%0ALIMIT+50');
	await button(page, 'Execute').click();
	await screenshot('sql');
});
