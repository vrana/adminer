<?php
namespace Adminer;

// this could be interface when "Db extends \mysqli" can have compatible type declarations (PHP 7)
// interfaces can include properties only since PHP 8.4
abstract class SqlDb {
	/** @var Db */ static $instance;

	public string $extension; // extension name
	public string $flavor = ''; // different vendor with the same API, e.g. MariaDB; usually stays empty
	public string $server_info; // server version
	public int $affected_rows; // number of affected rows
	public string $info; // see https://php.net/mysql_info
	public int $errno; // last error code
	public string $error; // last error message
	/** @var Result|bool */ protected $multi; // used for multiquery

	/** Connect to server
	* @return string error message
	*/
	abstract function attach(?string $server, string $username, string $password): string;

	/** Quote string to use in SQL
	* @return string escaped string enclosed in '
	*/
	abstract function quote(string $string): string;

	/** Select database */
	abstract function select_db(string $database): bool;

	/** Send query
	* @return Result|bool
	*/
	abstract function query(string $query, bool $unbuffered = false);

	/** Send query with more resultsets
	* @return Result|bool
	*/
	function multi_query(string $query) {
		return $this->multi = $this->query($query);
	}

	/** Get current resultset
	* @return Result|bool
	*/
	function store_result() {
		return $this->multi;
	}

	/** Fetch next resultset */
	function next_result(): bool {
		return false;
	}
}
