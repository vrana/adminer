<div class="grid-left" markdown>
![image](assets/logo.svg){.index-logo}
</div>

<div class="grid-right" markdown>
<p>
<b>AdminerEvo</b> is a web-based database management interface, with a focus on
security, user experience, performance, functionality and size.
</p>

<p>
It is available for download as a single self-contained PHP file, making it easy
to deploy anywhere.
</p>

[Download](https://github.com/adminerevo/adminerevo){ .md-button .md-button--secondary target=\_blank }
</div>

<div class="clear"></div>

AdminerEvo works out of the box with MySQL, MariaDB, PostgreSQL, SQLite, MS SQL,
Oracle, Elasticsearch and MongoDB. In addition, there are plugins for
[SimpleDB](https://github.com/adminerevo/adminerevo/blob/main/plugins/drivers/simpledb.php),
[Firebird](https://github.com/adminerevo/adminerevo/blob/main/plugins/drivers/firebird.php) and
[ClickHouse](https://github.com/adminerevo/adminerevo/blob/main/plugins/drivers/clickhouse.php).

AdminerEvo is developed by the AdminerEvo community and is a continuation of
the [Adminer](https://www.adminer.org/) project by
[Jakub Vrána](https://www.vrana.cz/).

## Rationale

Existing database management interfaces often come in the form of desktop
clients, or as large web applications. They often only support a single DBMS.

Adminer aims to offer a familiar interface in a lightweight package, no matter
the environment. The only requirement is a webserver configured to run a current
version of [PHP](https://php.net/).

## History

The project was started by Jakub Vrána as phpMinAdmin, with the aim of providing
a light-weight alternative to phpMyAdmin. A 1.0.0 version was released on the
11th of July 2007.

Nearly two years later, Jakub renamed the project to Adminer, as its former name
started as somewhat of a joke and caused confusion with the phpMyAdmin project.

Around the same time, Jakub had an article published in the _php|architect_
August 2009 edition, which he made available on his
[blog](https://php.vrana.cz/architecture-of-adminer.php)
([archive](https://archive.is/XjTDx)). The article goes into detail about his
ideas for Adminer and how it was designed. Some of this is still relevant today.

A major announcement came the following year, with the release of 3.0.0. This
release introduced support for multiple database drivers and already included
SQLite, PostgreSQL, MS SQL and Oracle.

In 2016 the project's source code was moved from its home on
[SourceForge](https://sourceforge.net/p/adminer/) to
[GitHub](https://github.com/vrana/adminer/). Bug reports and user forums,
however, remained where they were.

Finally, in May of 2023, after a long period without released and with user
contributions piling up without being merged, a group of individuals decided to
join forces and revive the project as AdminerEvo.

## Support

The community is available at
[GitHub Discussions](https://github.com/adminerevo/adminerevo/discussions) where
we discuss ideas and issues.

If you would like to report a bug, please look through the open
[issues](https://github.com/adminerevo/adminerevo/issues) or create a new one.

### Contributions

We welcome [pull requests](https://github.com/adminerevo/adminerevo/pulls),
however we suggest discussing your idea first via the
[discussion board](https://github.com/adminerevo/adminerevo/discussions).
