<?php
namespace Adminer;

// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	abstract class PdoDb extends SqlDb {
		protected \PDO $pdo;

		/** Connect to server using DSN
		* @param mixed[] $options
		* @param class-string<\PDO> $class
		* @return string error message
		*/
		function dsn(string $dsn, string $username, string $password, array $options = array(), string $class = 'PDO'): string {
			$options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_SILENT;
			$options[\PDO::ATTR_STATEMENT_CLASS] = array('Adminer\PdoResult');
			try {
				$this->pdo = new $class($dsn, $username, $password, $options);
			} catch (\Exception $ex) {
				return $ex->getMessage();
			}
			$this->server_info = @$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
			return '';
		}

		function quote(string $string): string {
			return $this->pdo->quote($string);
		}

		function query(string $query, bool $unbuffered = false) {
			/** @var Result|bool */
			$result = $this->pdo->query($query);
			$this->error = "";
			if (!$result) {
				return $this->store_error(false);
			}
			$this->store_result($result);
			return $result;
		}

		/** Store the last error of the connection if the operation failed */
		private function store_error(bool $return): bool {
			if (!$return) {
				list(, $this->errno, $this->error) = $this->pdo->errorInfo();
				if (!$this->error) {
					$this->error = lang('Unknown error.');
				}
			}
			return $return;
		}

		function store_result($result = null) {
			if (!$result) {
				$result = $this->multi;
				if (!$result) {
					return false;
				}
			}
			if ($result->columnCount()) {
				$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
				return $result;
			}
			$this->affected_rows = $result->rowCount();
			return true;
		}

		function next_result(): bool {
			/** @var PdoResult|bool */
			$result = $this->multi;
			if (!is_object($result)) {
				return false;
			}
			$result->_offset = 0;
			return @$result->nextRowset(); // @ - PDO_PgSQL doesn't support it
		}

		function inTransaction(): bool {
			// PDO_PgSQL, PDO_MySQL and PDO_SQLite ask the connection, other drivers report only transactions started by PDO
			return $this->pdo->inTransaction();
		}

		function begin(): bool {
			// the API works also where a separate BEGIN doesn't, e.g. MS SQL rolls it back at the end of the batch and Oracle commits each command
			return $this->store_error($this->pdo->beginTransaction());
		}

		function commit(): bool {
			// PDO throws without a transaction, PDO_MySQL reports none after a DDL command committed it implicitly
			return !$this->pdo->inTransaction() || $this->store_error($this->pdo->commit());
		}

		function rollback(): bool {
			return !$this->pdo->inTransaction() || $this->store_error($this->pdo->rollBack());
		}
	}

	class PdoResult extends \PDOStatement {
		public $_offset = 0, $num_rows;

		function fetch_assoc() {
			return $this->fetch_array(\PDO::FETCH_ASSOC);
		}

		function fetch_row() {
			return $this->fetch_array(\PDO::FETCH_NUM);
		}

		private function fetch_array(int $mode) {
			$return = $this->fetch($mode);
			return ($return ? array_map(array($this, 'normalize'), $return) : $return);
		}

		/** Convert the value to the same representation as in the native extensions */
		private function normalize($val) {
			if (is_bool($val)) { // PDO_PgSQL returns booleans, the pgsql extension returns 't' and 'f'
				return (JUSH == 'pgsql' ? ($val ? "t" : "f") : +$val);
			}
			if (PHP_VERSION_ID < 70100 && is_float($val) && is_finite($val)) { // precision -1 is not available, use the shortest representation preserving the value
				for ($precision = 15; $precision < 17; $precision++) { // 15 - any 15 digits survive
					$return = sprintf("%.$precision" . "G", $val);
					if ((float) $return === $val) {
						return $return;
					}
				}
				return sprintf("%.17G", $val); // 17 - enough for any double
			}
			return (is_resource($val) ? stream_get_contents($val) : $val);
		}

		function fetch_field(): \stdClass {
			return (object) $this->getColumnMeta($this->_offset++); // the drivers report the type in native_type, PDO_SQLSRV in sqlsrv:decl_type
		}

		function seek($offset) {
			for ($i=0; $i < $offset; $i++) {
				$this->fetch();
			}
		}
	}
}
