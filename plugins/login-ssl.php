<?php

/**
 * Connect to MySQL using SSL
 *
 * @link https://www.adminer.org/plugins/#use
 * @author Jakub Vrana, https://www.vrana.cz/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
  */
class AdminerLoginSsl
{
	private var $ssl;

	/**
	 * MySQL: ["key" => filename, "cert" => filename, "ca" => filename]
	 * PostgresSQL: ["mode" => sslmode] (https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-SSLMODE)
	 */
	function __construct(array $ssl)
	{
		$this->ssl = $ssl;
	}

	function connectSsl()
	{
		return $this->ssl;
	}
}
