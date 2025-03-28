<?php
namespace Adminer;

// this could be interface when "Db extends \mysqli" can have compatible type declarations (PHP 7)
// interfaces can include properties only since PHP 8.4
abstract class SqlDb {
	/** @var string */ public $extension; // extension name
	/** @var string */ public $flavor; // different vendor with the same API, e.g. MariaDB; usually stays empty
	/** @var string */ public $server_info; // server version
	/** @var int */ public $affected_rows; // number of affected rows
	/** @var string */ public $info; // see https://php.net/mysql_info
	/** @var int */ public $errno; // last error code
	/** @var string */ public $error; // last error message
	/** @var Result|bool */ protected $multi; // used for multiquery

	/** Connect to server
	* @return bool
	*/
	abstract function connect(string $server, string $username, string $password);

	/** Quote string to use in SQL
	* @return string escaped string enclosed in '
	*/
	abstract function quote(string $string);

	/** Select database
	* @return bool
	*/
	abstract function select_db(string $database);

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

	/** Fetch next resultset
	* @return bool
	*/
	function next_result() {
		return false;
	}

	/** Get single field from result
	* @return string|bool
	*/
	function result(string $query, int $field = 0) {
		$result = $this->query($query);
		if (!is_object($result)) {
			return false;
		}
		$row = $result->fetch_row();
		return ($row ? $row[$field] : false);
	}
}
