import {defineConfig} from '@playwright/test';

// End-to-end tests, run them by: npx playwright test --config conf/playwright.config.js
// The dev server and the database servers must be running, see ../tests/README.md.
export default defineConfig({
	testDir: '../tests',
	outputDir: '../tests/results', // traces and screenshots of failed tests
	workers: 1, // the tests share the adminer_test database and the single threaded PHP development server
	reporter: 'list',
	timeout: 30000, // the slowest tests (searching data in all tables, the bulk table operations) take about 15 s
	expect: {timeout: 3000}, // Adminer prints the whole page at once, only JavaScript can change it later
	use: {
		baseURL: process.env.ADMINER_URL || 'http://127.0.0.1:8000',
		channel: 'chrome', // the installed browser, no download
		actionTimeout: 5000, // an element which is hidden or missing fails fast instead of blocking until the test timeout; a click submitting a form also waits for the navigation
		trace: 'retain-on-failure',
	},
	projects: [
		{name: 'native'},
		{name: 'pdo', metadata: {ext: 'pdo'}, testIgnore: ['**/elastic.spec.js', '**/plugins.spec.js', '**/screenshots.spec.js']},
	],
});
