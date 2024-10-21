# Adminer

**Adminer** is a full-featured database management tool written in PHP. It consists of a single file ready to deploy to 
the target server. As a companion, **Adminer Editor** offers data manipulation for end-users.

Supported database drivers:
- MySQL, MariaDB, PostgreSQL, SQLite, MS SQL, Oracle, MongoDB
- With plugin: SimpleDB, Elasticsearch (beta), Firebird (alpha), ClickHouse (alpha)

## Requirements

- PHP 5.6+ with enabled sessions.

## Migration from original Adminer

No action is needed for now. But please, read 👉 **[What to expect](#what-to-expect)** section before you decide to
switch to this project.

## Usage

Download one for the latest [release files](https://github.com/pematon/adminer/releases), upload to the HTTP server 
with PHP and enjoy 😉 If you are not satisfied with any combination of the database driver and language, you can 
download the source code and compile your own Adminer:

- Download the source code.
- Run `composer install` to install dependencies.
- Run compile.php:

```shell
# Adminer
php compile.php <driver> <language>

# Editor
php compile.php editor <driver> <language>
```

For example:
```shell
php compile.php pgsql cs
```

[Available drivers](https://github.com/pematon/adminer/tree/master/adminer/drivers), 
[languages](https://github.com/pematon/adminer/tree/master/adminer/lang).

## Security

Adminer does not allow connecting to databases without a password and it rate-limits the connection attempts to protect 
against brute-force attacks. Still, it is highly recommended to 🔒 **restrict access to Adminer** 🔒 by whitelisting IP 
addresses allowed to connect to it, by password-protecting the access in your web server or by enabling security plugins 
(e.g. to require an OTP).

## Plugins

* Download plugins you want and place them into the `plugins` folder.
* Create `index.php` file specifying which plugins do you want to use.

File structure will be:

```
- plugins
    - drivers
        - elastic.php
    - dump-xml.php
    - tinymce.php
    - file-upload.php
    - ...
- adminer.php
- index.php
```

Index.php:
```php
<?php
function adminer_object() {
    // Required to run any plugin.
    include_once "./plugins/plugin.php";
    
    // Autoloader.
    foreach (glob("plugins/*.php") as $filename) {
        include_once "./$filename";
    }
    
    // Enable extra drivers just by including them.
    include_once "./plugins/drivers/elastic.php";
    
    // Specify enabled plugins.
    $plugins = [
        new AdminerDumpXml(),
        new AdminerTinymce(),
        new AdminerFileUpload("data/"),
        // ...
    ];
    
    // It is possible to combine customization and plugins.
    // class AdminerCustomization extends AdminerPlugin {
    // }
    // return new AdminerCustomization($plugins);
    
    return new AdminerPlugin($plugins);
}

// Include original Adminer or Adminer Editor.
include "./adminer.php";
```

[Available plugins](https://github.com/pematon/adminer/tree/master/plugins).

## Main project files
- adminer/index.php - Run development version of Adminer.
- editor/index.php - Run development version of Adminer Editor.
- editor/example.php - Example customization.
- plugins/readme.txt - Plugins for Adminer and Adminer Editor.
- adminer/plugin.php - Plugin demo.
- adminer/sqlite.php - Development version of Adminer with SQLite allowed.
- editor/sqlite.php - Development version of Editor with SQLite allowed.
- adminer/designs.php - Development version of Adminer with adminer.css switcher.
- compile.php - Create a single file version.
- lang.php - Update translations.
- tests/katalon.html - Katalon Automation Recorder test suite.

## Project history

Adminer was originally developed by Jakub Vrana, and it can be still found on [official pages](https://www.adminer.org/).
Unfortunately, it is not maintained for several years. In the meantime, I (@peterpp) created for my company a set of
custom plugins, modern theme, fixed some bugs and practically rewrote the Elasticsearch driver. I also looked closely 
and contributed to the [AdminerEvo](https://www.adminerevo.org/) project that looked promising. However, I finally 
decided to continue working on this fork and fulfill my own vision.

## What to expect

Our top priority is fixing the security issues and reported bugs. But we really want to move forward and transform
Adminer to a tool that will keep its simplicity, yet looks much better, is even easier to use and can be configured
without requirement of additional plugins.

### Version 4.x
Original design and backward compatibility is kept. Many issues were fixed, and we introduced several functional and 
UI improvements.

### Version 5
Bridges will be burned 🔥🔥🔥. It's in development already, so you [can check](https://github.com/pematon/adminer/tree/version-5) 
what's going on. Or you can become the early adopter and help us with testing 😉

Our goals are:

- **Requirements** - Bump minimal PHP to 7.1, maybe even higher. 
- **Themes** – Modernize the current old-school theme, add new default theme based on our [Adminer theme](https://github.com/pematon/adminer-theme), 
support dark mode, configurable color variants for production/devel environment. All current designs will be removed. 
- **Plugins** - Integrate several basic plugins, enable them by optional configuration.
- **Codebase** - Prefer code readability before minimalism, use PER coding style, add namespaces.
- **Compilation** - Allow to export selected drivers, themes, languages and plugins into a single adminer.php file.

We are also thinking to change the project's name, so people will clearly distinguish between original Adminer and 
other forks. Any suggestions are welcome.
