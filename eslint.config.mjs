// prepend adminer/include/functions.js to adminer/include/editing.js and editor/include/editing.js, then delete

import { globalIgnores } from "eslint/config";
import js from "@eslint/js";
import globals from "globals";

export default [
	globalIgnores(["externals/"]),
	js.configs.recommended,
	{
		languageOptions: {
			globals: {
				...globals.browser,
				jush: false, jushLinks: false,
				offlineMessage: false, thousandsSeparator: false, // include/design.inc.php
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
];
