<?php
namespace Adminer;

// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	abstract class PdoDb extends SqlDb {
		protected \PDO $pdo;

		/** Connect to server using DSN
		* @param mixed[] $options
		* @return string error message
		*/
		function dsn(string $dsn, string $username, string $password, array $options = array()): string {
			$options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_SILENT;
			$options[\PDO::ATTR_STATEMENT_CLASS] = array('Adminer\PdoResult');
			try {
				$this->pdo = new \PDO($dsn, $username, $password, $options);
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
				list(, $this->errno, $this->error) = $this->pdo->errorInfo();
				if (!$this->error) {
					$this->error = lang('Unknown error.');
				}
				return false;
			}
			$this->store_result($result);
			return $result;
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
			return ($return ? array_map(array($this, 'unresource'), $return) : $return);
		}

		private function unresource($val) {
			return (is_resource($val) ? stream_get_contents($val) : $val);
		}

		function fetch_field(): \stdClass {
			$row = (object) $this->getColumnMeta($this->_offset++);
			$type = $row->pdo_type;
			$row->type = ($type == \PDO::PARAM_INT ? 0 : 15);
			$row->charsetnr = ($type == \PDO::PARAM_LOB || (isset($row->flags) && in_array("blob", (array) $row->flags)) ? 63 : 0);
			return $row;
		}

		function seek($offset) {
			for ($i=0; $i < $offset; $i++) {
				$this->fetch();
			}
		}
	}
}
