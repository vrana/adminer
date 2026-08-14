import {expect, test} from '@playwright/test';

const errors = []; // errors reported since the last expectNoErrors()
const responses = []; // promises of the responses being searched for PHP errors

/** Open a browser page which collects the errors reported by PHP and by the browser
* @param {import('@playwright/test').Browser} browser
* @param {object} [options] passed to browser.newPage(), e.g. deviceScaleFactor
* @return {Promise<import('@playwright/test').Page>}
*/
export async function newPage(browser, options) {
	const page = await browser.newPage(options);
	page.on('dialog', dialog => dialog.accept()); // Katalon called this chooseOkOnNextConfirmation
	page.on('console', message => {
		// HTTP statuses are reported here too but Adminer sends 403 with the login form on purpose
		if (message.type() == 'error' && !/^Failed to load resource:/.test(message.text())) {
			errors.push('Console: ' + message.text());
		}
	});
	page.on('pageerror', error => errors.push('JavaScript: ' + error.message));
	page.on('response', response => responses.push(findPhpError(response)));
	return page;
}

/** Look for a PHP error in a response, display_errors prints them to the output */
async function findPhpError(response) {
	if (!/^(text\/(html|plain)|application\/json)\b/.test(response.headers()['content-type'] || '')) {
		return;
	}
	// a body of a request superseded by a navigation is never delivered, so don't wait for it forever
	const body = await Promise.race([
		response.text().catch(() => ''), // e.g. gone after a redirect
		new Promise(resolve => setTimeout(resolve, 2000, '')),
	]);
	// the development server prints the errors with html_errors, the compiled file can be served without them
	const match = body.match(/^(?:<b>)?(?:Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error)(?:<\/b>)?:.*/m);
	if (match) {
		errors.push('PHP: ' + match[0].replace(/<[^>]*>/g, ''));
	}
}

/** Fail if PHP or the browser reported an error since the last check */
export async function expectNoErrors() {
	await Promise.all(responses.splice(0));
	expect(errors.splice(0)).toEqual([]);
}

/** Get the PHP extension used by the current project
* @return {string} e.g. 'pdo', empty for the default extension
*/
export function extension() {
	return test.info().project.metadata.ext || '';
}

/** Open an Adminer URL, forcing the PHP extension used by the current project
* @param {import('@playwright/test').Page} page
* @param {string} url relative to baseURL
*/
export async function goto(page, url) {
	const ext = extension();
	if (ext && /^\/(adminer|editor|tests)\//.test(url)) {
		url += (url.includes('?') ? '&' : '?') + 'ext=' + ext;
	}
	await page.goto(url);
}

/** Get the first link with the given text - Adminer prints some links both in the content and in the menu
* @param {import('@playwright/test').Page} page
* @param {string} text
* @return {import('@playwright/test').Locator}
*/
export function link(page, text) {
	return page.getByRole('link', {name: text, exact: true}).first();
}

/** Get the first submit button with the given label - Adminer repeats them below long forms
* @param {import('@playwright/test').Page} page
* @param {string} value
* @return {import('@playwright/test').Locator}
*/
export function button(page, value) {
	return page.locator('input[value="' + value + '"]').first();
}

/** Set the value of a field which JavaScript replaces by a highlighted editor
* @param {import('@playwright/test').Page} page
* @param {string} name
* @param {string} value
*/
export async function setValue(page, name, value) {
	await page.locator('[name="' + name + '"]').evaluate((el, value) => {
		el.value = value;
		el.dispatchEvent(new Event('change')); // jush copies the value to the highlighted <pre>
	}, value);
}

/** Verify that the connection uses the extension required by the project
* @param {import('@playwright/test').Page} page
*/
export async function expectExtension(page) {
	const ext = extension();
	if (ext) {
		await expect(page.locator('body')).toContainText(ext.toUpperCase() + '_');
	}
}
