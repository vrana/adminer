import {defineConfig} from '@playwright/test';

// End-to-end tests, run them by: node tests/run.js
// The dev server and the database servers must be running, see ../tests/README.md.

// the installed Chrome by default, chromium-headless-shell is faster, firefox and webkit are other browsers; all of them except chrome need: npx playwright install <browser>
const browser = process.env.PLAYWRIGHT_BROWSER || 'chrome';
const otherEngine = /^(firefox|webkit)$/.test(browser); // the channel selects a build of Chromium only

export default defineConfig({
	testDir: '../tests',
	workers: 1, // more by --workers; the files of different drivers use different database servers but the native and pdo projects of one driver share the database, so tests/run.js runs them one after another
	reporter: 'list',
	timeout: 30000, // the slowest tests (searching data in all tables, the bulk table operations) take about 15 s
	expect: {timeout: 3000}, // Adminer prints the whole page at once, only JavaScript can change it later
	use: {
		baseURL: (process.env.ADMINER_URL || 'http://localhost:8000').replace(/\/?$/, '/'), // without the trailing slash, the relative URLs would replace the last part of the path
		browserName: (otherEngine ? browser : 'chromium'),
		channel: (otherEngine ? undefined : browser),
		actionTimeout: 5000, // an element which is hidden or missing fails fast instead of blocking until the test timeout; a click submitting a form also waits for the navigation
		trace: 'off', // recording takes a fifth of the time even if the trace is discarded, rerun a failed test by --trace=retain-on-failure
	},
	projects: [
		// pages and traces of failed tests, a directory per project because a run clears the directories of its projects
		{name: 'native', outputDir: '../tests/results/native'},
		{name: 'pdo', outputDir: '../tests/results/pdo', metadata: {ext: 'pdo'}, testIgnore: ['**/elastic.spec.js', '**/plugins.spec.js', '**/screenshots.spec.js']},
	],
});
