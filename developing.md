# Notes for developers

Jakub Vrána

## Request lifecycle

Request lifecycle is pretty simple. Adminer loads a database driver based on a URL parameter (e.g. `pgsql=`). The drivers live in [adminer/drivers/](/adminer/drivers/) and [plugins/drivers/](/plugins/drivers/). The driver consists of the class [`Driver`](/adminer/include/driver.inc.php) and a bunch of functions which would have a better home in `Driver` but aren't there for historical reasons.

A driver also creates the [`Db`](https://github.com/vrana/adminer/blob/v5.0.6/adminer/drivers/mysql.inc.php#L62) class based on the available PHP extensions. So there's no `DriverMysql` or `DbMysqlPdo`, there's always up to one `Driver` and `Db`.

If the URL contains `username=` then Adminer tries to authenticate that user. If it fails then a login form is displayed on the same URL and post data is stored to hidden form fields. If the user authenticates with the same credentials then the action is performed.

All state-changing actions (primarily all data modifications but also language change or logout) are performed using POST with a CSRF token present. This token is not necessary in modern browsers because Adminer also sets cookies as SameSite but it is kept just to be sure. If the POST action is successful then Adminer redirects the browser to GET so that page refresh doesn't perform the action again. Unsuccessful POST displays the same page with form fields pre-filled. Page refresh tries the same action again to handle the situation where the reason for the error was fixed e.g. in a different browser tab.

Then the request is routed based on the other URL parameters. E.g. if there's `indexes=` in the URL then [adminer/indexes.inc.php](/adminer/indexes.inc.php) is loaded. The table name is taken from this parameter. This creates simpler URLs than e.g. `action=indexes&table=customers`.

PHP session is stopped before the page rendering starts. This disallows setting `$_SESSION` later in the code but allows opening another page with Adminer e.g. if there's a long running query in the first one.

Database identifiers such as column names could be arbitrary so they are never transferred in URL or POST naked. They are always wrapped to `fields[col]` or similar. A possible `[` in the name is escaped.

Adminer often checks for an empty string using `$table != ""` or similar. It is because a table name could be `0` and a simple check `!$table` would fail.

## Classes, functions, constants, variables

Adminer defines lots of functions and some global variables. The functions live in a namespace so they don't collide with anything else. The global variables should be avoided but Adminer uses them for simplicity. They are minified during compilation to some random strings so they are unusable externally (e.g. by plugins). Plugins can access some of them using helpers (e.g. `Adminer\driver()`).

There are also some constants in Adminer's namespace. A prominent example is `JUSH` which is a syntax highlighting ID (e.g. `pgsql` for PostgreSQL). This is sometimes used for simple ifs in Adminer code but it should be avoided for anything more complicated - usually a method in `Driver` is better.

## Backwards compatibility

Adminer is very conservative about the required PHP version. PHP 5.3 is still supported. Users sometimes upload Adminer to a server where they just couldn't upgrade PHP. The compatibility is occasionally [checked](https://github.com/vrana/adminer/blob/v5.0.6/phpcs.xml#L121). I bump the required PHP version only if it improves the code significantly. In the past, old PHP versions had some bugs and working around them was a pain. It's not true anymore and new PHP versions usually only add new features. If using a new feature would lead to better Adminer code then I'd consider bumping the required PHP version. This happened e.g. with anonymous functions or namespaces.

The same is true also for database systems. I support even the officially unsupported database versions because they are actually still used in the real world. I remove support for an old version only if it would complicate the code significantly. E.g. info about generated columns is available in `information_schema` which is not present in MySQL 4. So I've dropped support for MySQL 4 because it would need a totally different code path for getting info about columns.

Adminer aims to be compatible with its past versions which is important mainly for plugins. Only a significant improvement such as adding namespaces could break backwards compatibility.

## Extending functionality

Apart from the driver classes, there's also the class [`Adminer`](/adminer/include/adminer.inc.php) used for customization. It's powerful enough to split the functionality of Adminer and Adminer Editor (which has no DDL). It's possible to extend this class to create own customization. I use this feature to create admin interfaces for my own projects.

A more common way of hooking into Adminer is using the class [`Plugins`](/adminer/include/plugins.inc.php). I don't like the code in this class (it's very repetitive) but it serves the purpose well. It allows creating plugins which don't need to extend any Adminer class. Developers are familiar with creating a class containing some methods so this has low entry requirements. I've considered using hooks instead (e.g. `$hooks->register("tableName", $callback)` and then `$hooks->call("tableName")` but I like the fact that I can simply call `$adminer->tableName()` in Adminer code. Supporting this syntax with hooks would mean just moving the repetitive code elsewhere.

## Code style

Adminer uses quite strict code [style](/phpcs.xml) which might be perhaps slightly unusual. E.g. doc-comments are not indented by one space which is because some editors start the next line with a space if you hit Enter after `*/` (e.g. VS Code). I'm not very attached to a particular code style and I'm open to changes but all code must look the same. So if you want to change the code style then you need to adapt all existing code to it.

There's no rule about using `"` or `'`. Most code uses `"` because it's more versatile e.g. if you decide to use a variable in the string later. I use it even in cases where it's unlikely that a variable would be used later (e.g. `$_GET["table"]`) just because I have an editor snippet to insert this. `'` is used mostly with regular expressions and it is mandatory for extracting translations in `lang()`.

I don't use `"{$var}"` because it's longer. In the rare cases where `$var` couldn't be used in a string directly, I rather split the string: `"prefix$var" . "suffix"`.

Never use `$_REQUEST`. Make your mind about the right place for the param and access it there.

I'm not very happy about naming style. PHP's global functions use snake_case so I use it too in functions and variable names. The MySQLi's `Db` class extends the `mysqli` class so it uses snake_case for its methods too. But I prefer camelCase which is used in methods of other classes and their parameters. So it's not very consistent and sometimes you pass `$table_status` to a method accepting `$tableStatus`. The best solution is to use one word for everything which is not very practical. Some pages use capitals for the main object, e.g. `$TABLE` which I don't like very much but it shines nicely in the code.

Code after `if` and loops must be wrapped to `{}` blocks. They are removed in minification. `else if` is forbidden, use `elseif`.

I use empty lines to split code blocks but perhaps slightly less than usual. I have an editor shortcut to jump between empty lines and I use it primarily to jump between functions if a file has them. Lines with lone `}` divide the code optically well enough for me.

Well used ternary operators make the code more readable and shorter. However, Adminer code sometimes overuses them.

```php
// I find this more readable and less repetitive:
$title = ($update
    ? lang('Save and continue edit')
    : lang('Save and insert next')
);

// Than this:
if ($update) {
    $title = lang('Save and continue edit');
} else { // If you change else to elseif in the future then $title may stay uninitialized
    $title = lang('Save and insert next');
}
```

Adminer has a line length limit 250 which is ridiculous. All lines fit my screen but I still want to make them shorter. Perhaps 150 would be more reasonable but I also hate wrapping lines at random places - they must be wrapped at logical blocks which often requires at least a small refactoring. These refactorings introduced bugs in the past so I'm hesitant to do them just to fit some arbitrary limit.

## Comments

All functions have doc-comments but I hate repetition so e.g. the `Db` methods are documented only in [mysql.inc.php](/adminer/drivers/mysql.inc.php) and not in the other drivers. Parameter names are not repeated in `@param`, only the type and description is there based on the order. Doc-comment is imperative ("Get" not "Gets"), starts with a capital letter and doesn't end with a fullstop

Inline commets are useful e.g. to link specifications but they are usually avoided to explain the code which should be self-explanatory. Inline comments start with a small letter but I'm not very happy about it. They don't end with a fullstop.

## Error handling

Adminer strictly initializes all variables before use which is [checked](https://github.com/vrana/php-initialized). However, Adminer relies on the default value of uninitialized array items. This leads to much more readable code, consider e.g. this code:

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

Treating undefined variables as empty was a big progress from the C language where they contain random data. Sadly, developers abused this feature which led PHP to issue first notices and then warnings in this case. Adminer [silences](https://github.com/vrana/adminer/blob/v5.0.6/adminer/include/errors.inc.php) these errors. In projects, where I'm forced to check array key existence before using it, I quickly create a function like this.

```php
function idx($array, $key, $default = null) {
    // Note that this couldn't use isset() because idx(array(null), 0, '') would return a wrong value
    return (array_key_exists($key, $array) ? $array[$key] : $default);
}
```

It would be possible to use such a function in Adminer but the code would still be less readable than the current approach. Using `isset` can lead to bugs such as in this code: `isset($rw["name"])` (I want to check if `$row` contains `name` but I've made a typo in the variable name which `isset` silences). `empty()` is even worse and it should be avoided in most cases.

Adminer uses `@` only in cases where a possible error is unavoidable, e.g. when writing to files (even if you check if the file is writable then there's a race condition between the check and the actual write).

## Escaping

There's no auto-escaping in Adminer. When printing untrusted data (including e.g. table names), you must use `h()` which is a shortcut for `htmlspecialchars` with escaping also `"` and `'`. It would be nice to have some template system but it would have to be powerful enough to support streaming. Adminer needs to print data immediately to display at least partial results when some query is slow.

When constructing SQL queries, you must use `q()` for strings and `idf_escape()` for identifiers. Adminer needs full power when constructing queries so using some helper here would be challenging.

## Minimalism

Adminer is minimalist in everything - if something doesn't need to be there then it shouldn't be. It's true for UI which I try to keep as uncluttered as possible. E.g. I'm almost never interested in an index name, I'm always interested in columns of that index. So Adminer displays the index name only in `title=""`. The same is true for code - e.g. public visibility is the default so it doesn't need to be explicitly specified. People use `public` to differentiate from the case where they've forgotten to specify the visibility but I don't suffer from this.

If something could be implemented in a plugin then I accept it to the core only if it's useful for almost everyone. E.g. [sticky table headers](https://github.com/vrana/adminer/issues/918) are useful for everyone so they have been included. But [dark mode switcher](https://github.com/vrana/adminer/issues/926) would clutter the UI and it's useful only for someone so I've created a plugin for that.

## Dependencies

Adminer uses [Git submodules](https://git-scm.com/docs/git-submodule) for dependencies which predates [Composer](https://getcomposer.org/) or other package managers. Submodules are very convenient for developing - e.g. I add some feature to the syntax highlighter, commit this change and then I immediately use it in Adminer. The Adminer's commit just includes the current HEAD of the submodule. I don't need to release a new version for every change, update lock files or do stuff like that.

## Tests

Adminer doesn't have unit tests but it has quite extensive [end-to-end tests](/tests/). The advantage of these tests is that they verify the correct behavior even in the UI which is otherwise hard to test. They currently run about 10 minutes but it's bearable before releasing a new version. The advantage is that they allow to discover even e.g. JavaScript errors for real use-cases.

## JavaScript

Adminer should work with disabled JavaScript but it's more pleasant with JS enabled. Adminer doesn't use any framework but instead it has simple helpers like `qsa()` which is `document.querySelectorAll()` and then simple functions calling these helpers. These functions used to be bound directly in HTML (`<a onclick="tableClick()">`) but enabling strict CSP made this impossible. Adminer now registers these helpers using a short `<script>` element right after the tag it handles. It usually uses `qsl()` (query selector last) for this. The advantage of this approach is that the handlers are available immediately - remember that Adminer sometimes needs to wait for the database server for the response so it couldn't register the handlers e.g. at the end of the page. The only exception are handlers registered in a loop - registering them individually is slow so they are registered at once after the loop.

JavaScript code is split into [functions.js](/adminer/static/functions.js) (which is common) and [editing.js](/adminer/static/editing.js) (which is Adminer or Adminer Editor specific). These two files are concatenated in compilation to one file because they can't live without each other.

JavaScript follows coding style specified in [eslint.config.mjs](/eslint.config.mjs) but ESLint requires adding dependencies to the project which I don't want so I run it externally.

## Styles

Adminer generates a very simple HTML and styles it with simple CSS. It respects user preference for dark mode. Users can change the style with `adminer.css`. If you want to style some element without a class name then be a little creative with your selectors. If it's hard then I generally accept patches adding a class name to an element you want to style.

## Compilation

Adminer source code is structured into a reasonable number of reasonably small files. For simple deployment, these files are bundled into a single `*.php` file. The bundling is done by inlining `include` files. Static files such as `*.js` or `*.css` are also inlined and served by the `?file=` route.

If you wonder why includes in Adminer start with `./` - it's to skip `include_path` and it has nothing to do with compilation.

Compilation also [shrinks](https://github.com/vrana/PhpShrink) PHP code - it removes whitespace, comments and shortens variable names. This has the benefit that plugins couldn't overwrite Adminer's variables. The compiled file is binary which is perfectly valid in PHP but it's not valid UTF-8 which is debatable.

A huge chunk of the compiled file was occupied by translations. In source codes, a translation maps from English string to a localized string. For compilation, the identifiers are changed to numbers and translations are LZW compressed which saves a lot. This is decompressed into a session variable when Adminer runs to save time. It's also possible to compile a single language file which is even smaller.

`compile.php` outputs the compiled file into the current directory but you don't need to run it from Adminer's directory. I often run it from a different directory to prepare releases (29 files) or dogfood versions of Adminer.

## Version check

Adminer checks for new versions from [adminer.org/version/](https://www.adminer.org/version/) which is quite elaborate. The response is signed with a private key to avoid MitM attacks. The downside is that adminer.org gets the addresses of Adminer installations in the logs. However, I don't check these logs and nobody else have an access to this server. There's a [plugin](/plugins/version-noverify.php) to disable version checks but please check the version by some other means if you use it to get security updates. It should be considered to get the version info from some independent entity, e.g. GitHub.

## Translations

All user visible strings should be marked as translatable with `lang('')`. This extracts them for translation and actually translates them if a translation is available.

Translations are updated with [lang.php](/lang.php) which also checks style, e.g. matching full stops.

Plurals are stored as arrays. The logic for picking the right element from this array is in [lang.inc.php](/adminer/include/lang.inc.php).

Web is translated separately using Google Sheets.

## Commits

Every commit should do only one thing and it should be as small as possible. An [example](https://github.com/peterpp/jush/commit/2de4bac) of a poor commit in a related project describes one useful change in the description but it actually does three things:
- Adds dark mode which is desired
- Randomly changes some colors in the light mode
- Changes indentation in some files which makes them inconsistent with everything else

This commit should be split into three and I'd accept only the change that is actually described.

I try to honor authorship if possible but I don't want commits changing the repo to a wrong state in the history. It means that I often amend e.g. pull requests. Please don't be offended by this - your proposed change is still there under your name but the code might be slightly different. This is simpler for me than requesting changes to such pull requests.

If the change modifies Adminer behavior for end users then it should be documented in [CHANGELOG](/CHANGELOG.md) in the same commit. This is quite important - I have a keyboard shortcut to blame the current line and I have another shortcut to open GitHub for the returned SHA. I often blame lines in changelog to see what they actually modified. Changes invisible to users (such as refactorings) shouldn't be documented here - the commit log is a sufficient place for them.

Commit messages should start with a capital letter and the first line shouldn't end with a full stop. There's no line length limit but be reasonable. If the commit is specific to some area (e.g. SQLite or CSS) then the message should be formatted as `Area: Message`. Detailed description is used rarely e.g. to link other commits (use the first 7 chars of SHA in this case).

If a commit handles some bug then it should be marked `(bug #n)` or `(fix #n)` if it fixes that bug.

Always diff your change before actually committing it. It helps with finding errors such as forgotten debug code.
