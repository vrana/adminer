# Adminer - Database management in a single PHP file
## Adminer Editor - Data manipulation for end-users

[https://www.adminer.org/](https://www.adminer.org/)

Supports: MySQL, MariaDB, PostgreSQL, SQLite, MS SQL, Oracle, SimpleDB, Elasticsearch, MongoDB, Firebird
Requirements: PHP 5+
Apache License 2.0 or GPL 2

- adminer/index.php - Run development version of Adminer
- editor/index.php - Run development version of Adminer Editor
- editor/example.php - Example customization
- plugins/readme.txt - Plugins for Adminer and Adminer Editor
- adminer/plugin.php - Plugin demo
- adminer/sqlite.php - Development version of Adminer with SQLite allowed
- adminer/designs.php - Development version of Adminer with adminer.css switcher
- compile.php - Create a single file version
- lang.php - Update translations
- tests/katalon.html - Katalon Automation Recorder test suite

If downloaded from Git then run: 

    $ git submodule update --init

If you like to use adminer with (Docker)[https://www.docker.com/get-started], e.g. with a postgres image

Example [adminer-stack.yml](adminer-stack.yml) for [postgres](https://hub.docker.com/_/postgres/):

    version: '3.1'

    services:

      db:
        image: postgres
        restart: always
        environment:
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: postgres

      adminer:
        image: adminer
        restart: always
        ports:
          - 8080:8080

Run 

    $ docker stack deploy -c adminer-stack.yml postgres (or docker-compose -f stack.yml up)
    
wait for it to initialize completely, and visit (swarm)[http://swarm-ip:8080], (localhost:8080)[http://localhost:8080], or your http://host-ip:8080 (as appropriate).
