<?php

/** Connect to MySQL, PostgreSQL, MS SQL, Elasticsearch or LDAP using SSL
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
	* LDAP: ["key" => filename, "cert" => filename, "ca" => filename, "verify" => bool] (switches ldap:// to StartTLS, the ldap extension applies them only to StartTLS)
	*/
	function __construct(array $ssl) {
		$this->ssl = $ssl;
	}

	function connectSsl() {
		return $this->ssl;
	}

	protected $translations = array(
		'cs' => array('' => 'Připojení k MySQL, PostgreSQL, MS SQL, Elasticsearch a LDAP pomocí SSL'),
		'de' => array('' => 'Stellen Sie eine Verbindung zu MySQL, PostgreSQL, MS SQL, Elasticsearch, LDAP über SSL her'), // Claude Opus 5.5
		'hr' => array('' => 'Spajanje na MySQL, PostgreSQL, MS SQL, Elasticsearch i LDAP putem SSL-a'), // Claude Opus 5.5
		'ja' => array('' => 'MySQL, PostgreSQL, MS SQL, Elasticsearch, LDAP への接続時に SSL を利用'), // Claude Opus 5.5
		'pl' => array('' => 'Połącz się z MySQL, PostgreSQL, MS SQL, Elasticsearch, LDAP za pomocą protokołu SSL'), // Claude Opus 5.5
		'ro' => array('' => 'Conectați-vă la MySQL, PostgreSQL, MS SQL, Elasticsearch, LDAP utilizând SSL'), // Claude Opus 5.5
		'sk' => array('' => 'Pripojenie k MySQL, PostgreSQL, MS SQL, Elasticsearch alebo LDAP pomocou SSL'), // Claude Opus 5.5
		'zh' => array('' => '使用 SSL 连接 MySQL、PostgreSQL、MS SQL、Elasticsearch 或 LDAP'), // Claude Opus 5.5
	);
}
