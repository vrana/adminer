// Run the end-to-end tests by: node tests/run.js [playwright arguments], see tests/README.md
// The native and pdo projects of one driver share the database, so they run one after another and only the files inside a project run in parallel.
import {spawnSync} from 'child_process';

const args = process.argv.slice(2);
const projects = (args.some(arg => /^--project(=|$)/.test(arg)) ? [[]] : [['--project=native'], ['--project=pdo', '--pass-with-no-tests']]); // e.g. plugins.spec.js runs only natively
let status = 0;
for (const project of projects) {
	// the CLI script is run by node directly because Node refuses to spawn npx.cmd on Windows without a shell, which would mangle the arguments
	const result = spawnSync(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', '--config', 'conf/playwright.config.js', ...project, ...args], {stdio: 'inherit'});
	status = status || (result.status ?? 1);
}
process.exit(status);
