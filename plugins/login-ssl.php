<?php

/** Connect to MySQL, PostgreSQL or MS SQL using SSL
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginSsl {
	protected $ssl;

	/**
	* MySQL: ["key" => filename, "cert" => filename, "ca" => filename, "verify" => bool]
	* PostgresSQL: ["mode" => sslmode] (https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-SSLMODE)
	* MSSQL: ["Encrypt" => true, "TrustServerCertificate" => true] (https://learn.microsoft.com/en-us/sql/connect/php/connection-options)
	*/
	function __construct(array $ssl) {
		$this->ssl = $ssl;
	}

	function connectSsl() {
		return $this->ssl;
	}
}
