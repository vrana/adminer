import js from "@eslint/js";
import globals from "globals";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";

/** Create a processor linting the file together with a file prepended to it during compilation
* @param {string} prepended path relative to the directory of the linted file
* @return {Object} ESLint processor
*/
function merge(prepended) {
	let lines = 0;
	return {
		meta: {name: "merge"},
		supportsAutofix: false, // the fix ranges would point into the merged code
		preprocess(text, filename) {
			let prefix = readFileSync(join(dirname(filename), prepended), "utf8");
			if (!prefix.endsWith("\n")) {
				prefix += "\n";
			}
			lines = prefix.split("\n").length - 1;
			return [{text: prefix + text, filename: "merged.js"}]; // the virtual name must not match this config again
		},
		postprocess(messages) {
			return messages[0]
				.filter(message => message.line > lines) // the prepended file reports its own problems when linted itself
				.map(message => Object.assign(message, {
					line: message.line - lines,
					endLine: (message.endLine ? message.endLine - lines : message.endLine),
				}))
			;
		},
	};
}

export default [
	{ignores: ["externals/"]}, // a config with only ignores is global; not globalIgnores() to not require the eslint package itself
	js.configs.recommended,
	{
		files: ["**/*.js"], // not this .mjs config
		languageOptions: {
			ecmaVersion: 2015, // newer syntax is a parse error, disabling the whole file; doesn't catch newer built-ins (e.g. Object.entries())
			globals: {
				...globals.browser,
				jush: false, jushLinks: false,
				offlineMessage: false, thousandsSeparator: false, urlSeparators: false, // include/design.inc.php
				indexColumns: false, // select.inc.php
				tablePos: false, em: false, // schema.inc.php
			}
		},
		rules: {
			"no-var": "error",
			"prefer-const": "error",
			"no-unused-vars": "off", //! we want this only on global level
		},
	},
	// the files are concatenated during compilation and depend on each other
	{files: ["**/adminer/static/functions.js"], processor: merge("editing.js")},
	{files: ["**/adminer/static/editing.js"], processor: merge("functions.js")},
	{files: ["**/editor/static/editing.js"], processor: merge("../../adminer/static/functions.js")},
];
