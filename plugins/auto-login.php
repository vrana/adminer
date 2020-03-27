<?php

/** Docker use example:

    app/DockerFile:
        FROM adminer
        ARG MYSQL_DATABASE
        ARG MYSQL_USER
        ARG MYSQL_PASSWORD
        ARG POSTGRES_DB
        ARG POSTGRES_USER
        ARG POSTGRES_PASSWORD
        COPY plugins-enabled/auto-login.php /var/www/html/plugins-enabled/auto-login.php
        RUN sed -i -e s/{MYSQL_DATABASE}/${MYSQL_DATABASE}/g \
            -e s/{MYSQL_USER}/${MYSQL_USER}/g \
            -e s/{MYSQL_PASSWORD}/${MYSQL_PASSWORD}/g \
            -e s/{POSTGRES_DB}/${POSTGRES_DB}/g \
            -e s/{POSTGRES_USER}/${POSTGRES_USER}/g \
            -e s/{POSTGRES_PASSWORD}/${POSTGRES_PASSWORD}/g \
            /var/www/html/plugins-enabled/auto-login.php

    app/plugins-enabled/auto-login.php:
        <?php
        require_once('plugins/auto-login.php');
        return new AdminerAutoLogin (
            array(
                'MariaDB' => array(
                    'server' => 'db-mysql', // db-service name in docker-compose.yaml
                    'driver' => 'server', // for MySQL / MariaDb
                    'db' => '{MYSQL_DATABASE}',
                    'username' => '{MYSQL_USER}',
                    'password' => '{MYSQL_PASSWORD}',
                ),
                'PostgreSQL' => array(
                    'server' => 'db-postgres',
                    'driver'=> 'pgsql',
                    'db' => '{POSTGRES_DB}',
                    'username' => '{POSTGRES_USER}',
                    'password' => '{POSTGRES_PASSWORD}',
                ),
            )
        );
*/

/** Display constant list of servers in login form and authorize without entering db user login/password
* @link https://www.adminer.org/plugins/#use
* @author Apres Antonyan, https://github.com/Apres2707
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerAutoLogin {
    /** @access protected */
    var $servers;

    /** Set supported servers
    * @param array array("ServerDisplayName" => array("server" => "127.0.0.1:5432", "driver" => "server|pgsql|sqlite|...",
    *   "database" => "db_name", "username" => "your_db_login", "password" => "your_db_password"))
    */
    function __construct($servers) {
        $this->servers = $servers;
        if ($_POST["auth"]) {
            $key = $_POST["auth"]["server"];
            $_POST["auth"]["driver"] = $this->servers[$key]["driver"];
            $_POST["auth"]["db"] = $this->servers[$key]["db"];
            $_POST["auth"]["username"] = $this->servers[$key]["username"];
            $_POST["auth"]["password"] = $this->servers[$key]["password"];
        }
    }

    function credentials() {
        return array($this->servers[SERVER]["server"], $_GET["username"], get_password());
    }

    function login($login, $password) {
        if (!$this->servers[SERVER]) {
            return false;
        }
    }

    function loginFormField($name, $heading, $value) {
        if (in_array($name, ['driver', 'db', 'username', 'password'])) {
            return '';
        } elseif ($name == 'server') {
            return $heading . "<select name='auth[server]'>" . optionlist(array_keys($this->servers), SERVER) . "</select>\n";
        }
    }
}
