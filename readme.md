# AdminerEvo

**Adminer** - Database management in a single PHP file  
**Adminer Editor** - Data manipulation for end-users

### A bit of history

The original Adminer was created and maintained by Jakub Vrána in the [vrana/adminer](https://github.com/vrana/adminer) repository.
Not being maintained for more than two years and being a daily user of Adminer, I've tried to get in touch with the original developer to propose my help to continue the project, but without success, I got no answer.
I have then started to search if someone would be interested in continuing the project with me and found someone who seemed to have the same interest and view on the future of this project.
I am now starting to take over the project under a slightly different name and will try to keep compatibility with all current database engines but also to give Adminer a new features, layout, etc.

### Before participating

Before you start developing around AdminerEvo, please carefully read the roadmap, the status of open issues, and even get in touch with us.
It would be sad spending time/energy on development of a feature which would not be accepted into the main project.

### Information from the original developer

|||
|---|---|
|Supports|MySQL, MariaDB, PostgreSQL, SQLite, MS SQL, Oracle, Elasticsearch, MongoDB, SimpleDB (plugin), Firebird (plugin), ClickHouse (plugin)|
|Requirements|PHP 5+|
|Licence|Apache License 2.0 or GPL 2|

&nbsp;

|File|Purpose|
|---|---|
|`adminer/index.php`|Run development version of Adminer|
|`editor/index.php`|Run development version of Adminer Editor|
|`editor/example.php`|Example customization|
|`plugins/readme.txt`|Plugins for Adminer and Adminer Editor|
|`adminer/plugin.php`|Plugin demo|
|`adminer/sqlite.php`|Development version of Adminer with SQLite allowed|
|`editor/sqlite.php`|Development version of Editor with SQLite allowed|
|`adminer/designs.php`|Development version of Adminer with `adminer.css` switcher|
|`compile.php`|Create a single file version|
|`lang.php`|Update translations|
|`tests/katalon.html`|Katalon Automation Recorder test suite|

If downloaded from Git then run: `git submodule update --init`
