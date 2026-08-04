<?php

/** Connect to MySQL, PostgreSQL, MS SQL or Elasticsearch using SSL
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginSsl extends Adminer\Plugin {
	protected $ssl;

	/**
	* MySQL: ["key" => filename, "cert" => filename, "ca" => filename, "verify" => bool]
	* PostgresSQL: ["mode" => sslmode] (https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-SSLMODE)
	* MSSQL: ["Encrypt" => true, "TrustServerCertificate" => true] (https://learn.microsoft.com/en-us/sql/connect/php/connection-options)
	* Elasticsearch: ["key" => filename, "cert" => filename, "ca" => filename, "verify" => bool] (verify => false accepts the self-signed certificate created by Elasticsearch)
	*/
	function __construct(array $ssl) {
		$this->ssl = $ssl;
	}

	function connectSsl() {
		return $this->ssl;
	}

	protected $translations = array(
		'cs' => array('' => 'Připojení k MySQL, PostgreSQL, MS SQL a Elasticsearch pomocí SSL'),
		'de' => array('' => 'Stellen Sie eine Verbindung zu MySQL, PostgreSQL, MS SQL, Elasticsearch über SSL her'), // Claude Opus 5
		'pl' => array('' => 'Połącz się z MySQL, PostgreSQL, MS SQL, Elasticsearch za pomocą protokołu SSL'), // Claude Opus 5
		'ro' => array('' => 'Conectați-vă la MySQL, PostgreSQL, MS SQL, Elasticsearch utilizând SSL'), // Claude Opus 5
		'ja' => array('' => 'MySQL, PostgreSQL, MS SQL, Elasticsearch への接続時に SSL を利用'), // Claude Opus 5
		'hr' => array('' => 'Spajanje na MySQL, PostgreSQL, MS SQL i Elasticsearch putem SSL-a'), // Claude Opus 5
	);
}
