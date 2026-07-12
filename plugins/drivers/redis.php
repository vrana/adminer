<?php
//! clone
//! SQL command

namespace Adminer;

add_driver("redis", "Redis");

if (isset($_GET["redis"])) {
	define('Adminer\DRIVER', "redis");

	class Db extends SqlDb {
		public $extension = "socket";
		private $fp;

		function attach($server, $username, $password): string {
			if ($server == "") {
				$server = "127.0.0.1";
			}
			if (!strpos($server, ":")) {
				$server .= ":6379";
			}
			list($host, $port) = host_port($server);
			$this->fp = fsockopen($host, $port, $errno, $error);
			if (!$this->fp) {
				return $error;
			}
			if ($password != "" && !$this->send(array("AUTH", $username, $password))) {
				return $this->error;
			}
			if (DB == "" && preg_match('~redis_version:(.+)~', $this->send(array("INFO", "server")), $match)) {
				$this->server_info = $match[1];
			}
			return '';
		}

		function select_db($database) {
			return $this->send(array("SELECT", $database));
		}

		function quote($string): string {
			return "'" . addcslashes($string, "\\'") . "'";
		}

		function query($query, $unbuffered = false) {
			return false;
		}

		function send($args) {
			$cmd = "*" . count($args) . "\r\n";
			foreach ($args as $arg) {
				$cmd .= '$' . strlen($arg) . "\r\n$arg\r\n";
			}
			fwrite($this->fp, $cmd);
			return $this->read();
		}

		function read() {
			$line = fgets($this->fp);
			if ($line === false) {
				$this->error = error_get_last()['message'];
				return false;
			}
			$type = $line[0];
			$payload = substr($line, 1, -2);
			switch ($type) {
				case '+': // simple string
				case ':': // integer
					return $payload;
				case '-': // error
					$this->error = $payload;
					return false;
				case '$': // bulk string
					$len = $payload;
					if ($len == -1) {
						return null;
					}
					$data = '';
					while (strlen($data) < $len) {
						$block = fread($this->fp, $len - strlen($data));
						if ($block === false) {
							$this->error = error_get_last()['message'];
							return false;
						}
						$data .= $block;
					}
					fgets($this->fp); // discard \r\n
					return $data;
				case '*': // array
					$count = $payload;
					if ($count == -1) {
						return null;
					}
					$arr = array();
					for ($i = 0; $i < $count; $i++) {
						$arr[] = $this->read();
					}
					return $arr;
				default:
					$this->error = "Unknown response type: $type";
					return false;
			}
		}
	}

	class Result {
		private $result;

		function __construct($result) {
			$this->result = $result;
		}

		function fetch_assoc() {
			$row = current($this->result);
			next($this->result);
			return $row;
		}
	}

	class Driver extends SqlDriver {
		static $jush = "redis";

		public $operators = array("*");

		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
			if (preg_match('~^key =~', $where[0])) {
				$args = $this->where($where[0]);
			} elseif ($limit) {
				$args = array("SCAN", $_GET["next"] ?: 0, "COUNT", $limit);
				if ($where) {
					$args[] = "MATCH";
					$args[] = $this->where($where[0])[0];
				}
				list($_GET["next"], $args) = $this->conn->send($args);
			} else {
				$args = $this->conn->send(array("KEYS", ($where ? $this->where($where[0])[0] : "*")));
			}
			$return = array();
			if ($args) {
				array_unshift($args, "MGET");
				foreach ($this->conn->send($args) as $i => $val) {
					$return[] = array('key' => $args[$i + 1], 'value' => $val);
				}
			}
			return new Result($return);
		}

		function insert($table, $set) {
			$args = array("SET");
			foreach ($set as $val) {
				$args[] = stripslashes(substr($val, 1, -1));
			}
			return $this->conn->send($args);
		}

		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
			$args = array("MSET");
			$where = $this->where($queryWhere);
			foreach ($where as $key) {
				$args[] = $key;
				$args[] = stripslashes(substr($set["value"], 1, -1));
			}
			$this->conn->affected_rows = count($where);
			return $this->conn->send($args);
		}

		function delete($table, $queryWhere, $limit = 0) {
			$args = $this->where($queryWhere);
			array_unshift($args, "DEL");
			$this->conn->affected_rows = $this->conn->send($args);
			return true;
		}

		private function where($queryWhere) {
			preg_match_all("~key . '((\\\\.|[^\\\\'])*+)'~", $queryWhere, $matches);
			return array_map('stripslashes', $matches[1]);
		}
	}

	function logged_user() {
		return $_GET["username"];
	}

	function get_databases($flush) {
		return array_map('strval', range(0, connection()->send(array("CONFIG", "GET", "databases"))[1] - 1));
	}

	function collations() {
		return array();
	}

	function db_collation($db, $collations) {
	}

	function information_schema($db) {
	}

	function indexes($table, $connection2 = null) {
		return array(array('type' => 'PRIMARY', 'columns' => array('key')));
	}

	function fields($table) {
		return array(
			"key" => array("field" => "key", "privileges" => array("select" => 1, "where" => 1, "insert" => 1)),
			"value" => array("field" => "value", "privileges" => array("select" => 1, "insert" => 1, "update" => 1)),
		);
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return $query;
	}

	function idf_escape($idf) {
		return $idf;
	}

	function table($idf) {
		return idf_escape($idf);
	}

	function foreign_keys($table) {
		return array();
	}

	function tables_list() {
		return array('data' => 'table');
	}

	function table_status($name = "", $fast = false) {
		return array('data' => array('Name' => 'data'));
	}

	function count_tables($databases) {
		return array_fill_keys($databases, 1);
	}

	function error() {
		return h(connection()->error);
	}

	function is_view($table_status) {
		return false;
	}

	function found_rows($table_status, $where) {
		return null;
	}

	function fk_support($table_status) {
		return false;
	}

	function last_id($result): string {
		return '';
	}

	function support($feature) {
		return false;
	}
}
