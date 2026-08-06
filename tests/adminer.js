import {expect, test} from '@playwright/test';

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
	if (ext && /^\/(adminer|editor)\//.test(url)) {
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
