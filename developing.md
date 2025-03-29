# Notes for Developers

Jakub Vrána

## Request Lifecycle

The request lifecycle is straightforward.
Adminer loads a database driver based on a URL parameter (e.g., `pgsql=`).
The drivers live in [adminer/drivers/](/adminer/drivers/) and [plugins/drivers/](/plugins/drivers/).
The driver consists of the class [`Driver`](/adminer/include/driver.inc.php) and a set of functions that ideally belong in `Driver` but remain separate due to historical reasons.

A driver also creates the [`Db`](https://github.com/vrana/adminer/blob/v5.0.6/adminer/drivers/mysql.inc.php#L62) class based on available PHP extensions.
There is no `DriverMysql` or `DbMysqlPdo`; there is always up to one `Driver` and one `Db`.

If the URL contains `username=`, Adminer attempts to authenticate that user.
If authentication fails, a login form is displayed at the same URL, and POST data is stored in hidden form fields.
If the user authenticates using the same credentials, the action is performed.

All state-changing actions (primarily data modifications, as well as language change or logout) are performed using POST with a CSRF token present.
Adminer sets cookies as SameSite which adds a second protection but not for vulnerabilities on the same site.
If a POST action succeeds, Adminer redirects the browser to a GET request to prevent accidental re-submission.
An unsuccessful POST displays the same page with pre-filled form fields.
Refreshing the page attempts the action again, which is useful when errors were resolved in another browser tab.

Then, the request is routed based on other URL parameters.
For example, if the URL contains `indexes=`, then [adminer/indexes.inc.php](/adminer/indexes.inc.php) is loaded.
The table name is extracted from this parameter, resulting in simpler URLs (e.g., `indexes=customers` instead of `action=indexes&table=customers`).

The PHP session is stopped before rendering begins.
This prevents modifying `$_SESSION` later in the code but allows multiple Adminer pages to be opened simultaneously, even if one has a long-running query.

Database identifiers, such as column names, can be arbitrary, so they are never transferred in URLs or POST requests directly.
They are always wrapped (e.g., `fields[col]`), and any `[` in the name is escaped.

Adminer often checks for empty strings using `$table != ""` instead of `!$table`, since table names can be `0`, and `!$table` would fail in such cases.

## Classes, Functions, Variables, Constants

There are 4 main classes: `Driver`, `Db`, `Adminer` and `Plugins`.
They are described in other sections.

Adminer defines many functions which are namespaced to prevent collisions.

There are no global variables.
Some data is stored in static class variables.
These variables are minified during compilation into random strings, making them inaccessible externally (e.g., by plugins).
Plugins can access some of them using helper functions like `Adminer\driver()`.

Adminer also defines constants in its namespace.
A key example is `JUSH`, which represents a syntax highlighting ID (e.g., `pgsql` for PostgreSQL).
Simple conditional checks may use `JUSH`, but for complex logic, methods in `Driver` are preferred.

## Backwards Compatibility

Adminer is highly conservative regarding PHP version requirements.
Source codes require PHP 7.4 to take advantage of type declarations.
These type declarations are stripped during compilation to be compatible with PHP 5.3.
PHP 5.3 is still supported because some users cannot upgrade their servers.
Compatibility is periodically [checked](https://github.com/vrana/adminer/blob/v5.0.6/phpcs.xml#L121).
The required PHP version is only increased if it significantly improves the code.
Older PHP versions had bugs that required workarounds, but modern versions primarily introduce new features.

The same philosophy applies to database systems.
Even unsupported database versions are still supported because they remain in use.
Support for an old version is only dropped if maintaining it would overly complicate the code.
For instance, MySQL 4 lacks `information_schema`, making generated column support impractical, so support for MySQL 4 was removed.

Adminer aims for backward compatibility, particularly for plugins.
Only significant improvements, such as adding namespaces, justify breaking changes.

## Extending Functionality

Besides driver classes, Adminer provides the [`Adminer`](/adminer/include/adminer.inc.php) class for customization.
This class enables Adminer and Adminer Editor (which lacks DDL support) to share functionality.
Developers can extend this class to implement customizations, as I do for my projects.

A more common method for extending Adminer is the [`Plugins`](/adminer/include/plugins.inc.php) class.
A plugin is simply a class defining any methods from [`Adminer`](/adminer/include/adminer.inc.php).
The `Plugins::__call` method calls all registered plugins until one of them returns non-null.

## Code Style

Adminer follows a strict [coding style](/phpcs.xml), though some choices may seem unusual.
For instance, doc-comments are not indented by one space because some editors (e.g., VS Code) insert a space when pressing Enter after `*/`.

There is no enforced rule on `"` vs. `'`.
Most code uses `"` because it's more flexible (e.g., embedding variables).
Even in cases where variable interpolation is unlikely (e.g., `$_GET["table"]`), I still use `"` due to an existing editor snippet.
`'` is primarily used for regular expressions and is required for extracting translations in `lang()`.

I avoid `"{$var}"` because it is longer.
In rare cases where `$var` cannot be used directly within a string, I prefer splitting the string (`"prefix$var" . "suffix"`).

Never use `$_REQUEST`.
Decide where the parameter belongs and access it accordingly.

I am not entirely satisfied with the naming style.
PHP global functions use `snake_case`, so I use it for functions and variables.
MySQLi’s `Db` class extends `mysqli`, so it also uses `snake_case`.
However, I prefer `camelCase` for method names and parameters so I use it in other classes.
This inconsistency sometimes results in passing `$table_status` to a method expecting `$tableStatus`.
The best approach would be to use single-word names, though this is impractical.
Some pages use uppercase for main object (e.g., `$TABLE`), but I dislike this despite its visibility.
Return values of functions are usually constructed into variables named `$return`.

Code within `if` statements and loops must always be wrapped in `{}` blocks.
These are removed during minification.
`else if` is forbidden; use `elseif` instead.

I use empty lines sparingly to separate code blocks.
My editor shortcut jumps between empty lines, I use it primarily for navigating functions.
Lines containing only `}` naturally divide the code visually.

Well-used ternary operators enhance readability, but they are sometimes overused in Adminer.

```php
// Preferred
$title = ($update
    ? lang('Save and continue edit')
    : lang('Save and insert next')
);

// Less desirable
if ($update) {
    $title = lang('Save and continue edit');
} else { // If you change else to elseif in the future then $title may stay uninitialized
    $title = lang('Save and insert next');
}
```

Adminer has an excessive line length limit of 250 characters.
While all lines fit my screen, I prefer shorter lines.
A limit of 150 would be more reasonable, but wrapping lines at arbitrary points is unacceptable.
Proper line wrapping often requires refactoring, which has caused bugs in the past, so I hesitate to make changes purely for line length.

## Comments

All functions have doc-comments, but redundancy is avoided.
For example, `Db` methods are documented only in [`db.inc.php`](/adminer/include/db.inc.php), not in the drivers.
`@param` tags include only params with type [more specific](https://phpstan.org/writing-php-code/phpdoc-types) than the native type declaration or with a comment.
The doc-comments use [aliases](/phpstan.neon) for complex arrays.
Doc-comments are imperative ("Get" instead of "Gets"), start with a capital letter, and do not end with a period.

Inline comments are useful for linking specifications but are generally avoided for explaining self-explanatory code.
They start with a lowercase letter and do not end with a period, though I am not entirely happy with this convention.

Comments starting with `//!` mean TODO.
Comments starting with `//~` are meant for debugging.

## Error Handling

Adminer strictly initializes all variables before use, which is [verified](/phpstan.neon).
However, Adminer relies on the default value of uninitialized array items.
This approach leads to more readable code.
Consider the following examples:

```php
// Adminer style
if (extension_loaded("mysqli") && $_GET["ext"] != "pdo")

// Explicit isset
if (extension_loaded("mysqli") && (!isset($_GET["ext"]) || $_GET["ext"] != "pdo"))

// Possible since PHP 7.0
if (extension_loaded("mysqli") && ($_GET["ext"] ?? "") != "pdo")

// With idx() explained later
if (extension_loaded("mysqli") && idx($_GET, "ext") != "pdo")
```

Treating undefined variables as empty was a significant improvement over the C language, where they contained random data.
Unfortunately, developers abused this feature, leading PHP to issue first notices and later warnings.
Adminer [silences](/adminer/include/errors.inc.php) these errors.
In projects where I am required to check array key existence before usage, I quickly create a function like this:

```php
function idx($array, $key, $default = null) {
    // Note: isset() cannot be used here because idx(array(null), 0, '') would return an incorrect value.
    return array_key_exists($key, $array) ? $array[$key] : $default;
}
```

Although it would be possible to use such a function in Adminer, the code would still be less readable than the current approach.
Using `isset` can introduce bugs, such as in this case: `isset($rw["name"])`.
Here, I intended to check if `$row` contains `name`, but a typo in the variable name is silently ignored.
The same is true for `??`.
`empty()` is even worse and should be avoided in most cases.

Adminer uses `@` only where an error is unavoidable, such as when writing to files.
Even if you check whether a file is writable, a race condition exists between the check and the actual write operation.

## Escaping

Adminer does not implement automatic escaping.
When printing untrusted data (including e.g. table names), you must use `h()`, which is a shortcut for `htmlspecialchars` that also escapes `"` and `'`.
While a templating system would be useful, it would need to support streaming.
Adminer prints data immediately to display partial results when a query is slow.

When constructing SQL queries, use `q()` for strings and `idf_escape()` for identifiers.
Adminer requires full control when constructing queries, making the use of additional helpers challenging.

## Minimalism

Adminer is minimalist in every aspect - if something is unnecessary, it should not be included.
This philosophy extends to the UI, which remains as uncluttered as possible.
For example, index names are usually irrelevant compared to the columns they reference, so Adminer displays index names only in `title=""`.
The same principle applies to the code; for instance, `public` visibility is the default, so it does not need to be explicitly specified.
Many closing HTML tags are optional (e.g., `</li>` or `</html>`) and Adminer obviously doesn't print them.

If a feature can be implemented as a plugin, it is only added to the core if it benefits almost everyone.
For example, [sticky table headers](https://github.com/vrana/adminer/issues/918) are useful to all users and have been included, whereas a [dark mode switcher](https://github.com/vrana/adminer/issues/926) would clutter the UI and is only useful for some, so it remains a plugin.

## Dependencies

Adminer uses [Git submodules](https://git-scm.com/docs/git-submodule) for dependencies, predating [Composer](https://getcomposer.org/) and other package managers.
Submodules simplify development - for example, I can add a feature to the syntax highlighter, commit the change, and immediately use it in Adminer.
Adminer commits simply reference the current HEAD of the submodule, avoiding the need for frequent version releases, lock file updates, or other package management tasks.

## Tests

Adminer does not include unit tests but has extensive [end-to-end tests](/tests/).
These tests verify correct behavior, including UI functionality, which is otherwise difficult to test.
The tests take about 10 minutes to run, which is acceptable before a release.
They help detect even JavaScript errors in real-world use cases.

## JavaScript

Adminer functions without JavaScript but is more user-friendly when JavaScript is enabled.
It does not rely on any framework but includes simple helpers like `qsa()`, a shorthand for `document.querySelectorAll()`, along with small functions that call these helpers.

Previously, these functions were bound directly in HTML (`<a onclick="tableClick()">`), but strict CSP enforcement made this impossible.
Now, Adminer registers event handlers using a short `<script>` element immediately following the relevant tag, typically using `qsl()` (query selector last).
This ensures handlers are available immediately.
The only exception is handlers registered in a loop, where bulk registration is more efficient.

JavaScript code is split into [functions.js](/adminer/static/functions.js) (common utilities) and [editing.js](/adminer/static/editing.js) (specific to Adminer or Adminer Editor).
These files are concatenated during compilation since they depend on each other.

JavaScript code follows the coding style defined in [eslint.config.mjs](/eslint.config.mjs), but because ESLint requires additional dependencies, I run it externally.

## Styles

Adminer generates simple HTML and styles it with basic CSS, respecting user preferences for dark mode.
Users can customize styles via `adminer.css`.
If styling an element without a class name is difficult, I generally accept patches that add meaningful class names.

## Compilation

Adminer’s source code is divided into a manageable number of reasonably small files.
For simpler deployment, these files are bundled into a single `*.php` file by inlining `include` files.
Static files (`*.js`, `*.css`) are also inlined and served via the `?file=` route.

Includes in Adminer start with `./` to bypass `include_path`, which is unrelated to compilation.

Compilation also [shrinks](https://github.com/vrana/PhpShrink) PHP code by removing whitespace, comments, and shortening variable names.
This prevents plugins from overwriting Adminer’s variables.
The compiled file is binary, which is valid PHP but not valid UTF-8 - a debatable choice.

Translations used to occupy a large portion of the compiled file.
In the source code, translations map English strings to localized versions.
During compilation, identifiers are converted to numbers, and translations are LZW-compressed to save space.
This data is decompressed into a session variable at runtime to improve performance.
A single-language compilation is also possible to create even smaller files.

`compile.php` outputs the compiled file to the current directory, but it does not need to be run from Adminer’s directory.
I often run it from a separate directory to prepare releases (29 files) or test versions of Adminer.

## Version Check

Adminer checks for new versions via [adminer.org/version/](https://www.adminer.org/version/), using a signed response to prevent tampering with the version file on the server where an instance of Adminer runs.
However, this means that adminer.org has access to the IP addresses of Adminer installations.
I do not review logs with this information, and no one else has access to the server.
A [plugin](/plugins/version-noverify.php) disables version checks, but users should verify versions by other means to ensure security updates.
There's also a [plugin](/plugins/version-github.php) checking for new versions [from GitHub](https://github.com/vrana/adminer/releases).

## Translations

All user-visible strings should be translatable using `lang('')`.
This extracts them for translation and applies translations if available.

Translations are updated via [lang.php](/lang.php), which also checks for style consistency, such as matching punctuation.
Plurals are stored as arrays, with selection logic handled in [lang.inc.php](/adminer/include/lang.inc.php).
The website translations are managed separately via Google Sheets.

## Commits

Every commit should do only one thing and be as small as possible.
An [example](https://github.com/peterpp/jush/commit/2de4bac) of a poor commit in a related project describes one useful change in the description but actually does three things:

- Adds dark mode, which is desired.
- Randomly changes some colors in light mode.
- Changes indentation in some files, making them inconsistent with everything else.

This commit should be split into three, and I would accept only the change that is actually described.

I try to honor authorship whenever possible, but I don’t want commits introducing an incorrect state into the repository’s history.
This means that I often amend pull requests.
Please don’t be offended by this - your proposed change will still be there under your name, but the code might be slightly different.
This is simpler for me than requesting changes to such pull requests.

If a change modifies Adminer’s behavior for end users, it should be documented in [CHANGELOG](/CHANGELOG.md) in the same commit.
This is quite important - I have a keyboard shortcut to blame the current line and another shortcut to open GitHub for the returned SHA.
I often blame lines in the changelog to see what they actually modified.
Changes that are invisible to users (such as refactorings) shouldn’t be documented here; the commit log is sufficient for them.

Commit messages should start with a capital letter, and the first line shouldn’t end with a period.
There is no strict line length limit, but be reasonable.
If the commit is specific to a particular area (e.g., SQLite or CSS), the message should be formatted as `Area: Message`.
A detailed description is rarely used, except when linking to other commits (use the first seven characters of the SHA in this case).

If a commit addresses a bug, it should be marked as `(bug #n)` or `(fix #n)` if it fixes the bug.

Always diff your changes before committing.
This helps catch errors, such as forgotten debug code.
