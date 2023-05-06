Adminer - Database management in a single PHP file
Adminer Editor - Data manipulation for end-users

A bit of history:
The original Adminer was created and maintained by vrana in repository vrana/adminer.
Not being maintained for more than two years and being a daily user of Adminer, I've tried to get in touch with the original developer
to propose my help to continue the project, but without success, I got no answer.
I have then started to search if someone would be interested in continuing the project with me and found someone who seemed to have
the same interest and view on the future of this project.
I am now starting to take over the project under a slightly different name and will try to keep it compatible with all currently compatible
database engines but also to give Adminer a new features / layout / ...

Before to participate:
Before to start developing around AdminerEvo, please read carefully the roadmap, the status of open issues, and even get in touch with us.
It would be sad spending time/energy in development of a feature which would finally not be accepted to be part of the master project.

Information from the original developer:

Supports: MySQL, MariaDB, PostgreSQL, SQLite, MS SQL, Oracle, Elasticsearch, MongoDB, SimpleDB (plugin), Firebird (plugin), ClickHouse (plugin)
Requirements: PHP 5+
Apache License 2.0 or GPL 2

adminer/index.php - Run development version of Adminer
editor/index.php - Run development version of Adminer Editor
editor/example.php - Example customization
plugins/readme.txt - Plugins for Adminer and Adminer Editor
adminer/plugin.php - Plugin demo
adminer/sqlite.php - Development version of Adminer with SQLite allowed
editor/sqlite.php - Development version of Editor with SQLite allowed
adminer/designs.php - Development version of Adminer with adminer.css switcher
compile.php - Create a single file version
lang.php - Update translations
tests/katalon.html - Katalon Automation Recorder test suite

If downloaded from Git then run: git submodule update --init
