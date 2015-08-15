<?php
$drivers["mongo"] = "MongoDB (beta)";

if (isset($_GET["mongo"])) {
	$possible_drivers = array("mongo");
	define("DRIVER", "mongo");

	if (class_exists('MongoDB')) {
		class Min_DB {
			var $extension = "Mongo", $error, $last_id, $_link, $_db;

			function connect($server, $username, $password) {
				global $adminer;
				$db = $adminer->database();
				$options = array();
				if ($username != "") {
					$options["username"] = $username;
					$options["password"] = $password;
				}
				if ($db != "") {
					$options["db"] = $db;
				}
				try {
					$this->_link = @new MongoClient("mongodb://$server", $options);
					return true;
				} catch (Exception $ex) {
					$this->error = $ex->getMessage();
					return false;
				}
			}
			
			function query($query) {
				return false;
			}

			function select_db($database) {
				try {
					$this->_db = $this->_link->selectDB($database);
					return true;
				} catch (Exception $ex) {
					$this->error = $ex->getMessage();
					return false;
				}
			}

			function quote($string) {
				return $string;
			}

		}

		class Min_Result {
			var $num_rows, $_rows = array(), $_offset = 0, $_charset = array();

			function __construct($result) {
				foreach ($result as $item) {
					$row = array();
					foreach ($item as $key => $val) {
						if (is_a($val, 'MongoBinData')) {
							$this->_charset[$key] = 63;
						}
						$row[$key] =
							(is_a($val, 'MongoId') ? 'ObjectId("' . strval($val) . '")' :
							(is_a($val, 'MongoDate') ? gmdate("Y-m-d H:i:s", $val->sec) . " GMT" :
							(is_a($val, 'MongoBinData') ? $val->bin : //! allow downloading
							(is_a($val, 'MongoRegex') ? strval($val) :
							(is_object($val) ? get_class($val) : // MongoMinKey, MongoMaxKey
							$val
						)))));
					}
					$this->_rows[] = $row;
					foreach ($row as $key => $val) {
						if (!isset($this->_rows[0][$key])) {
							$this->_rows[0][$key] = null;
						}
					}
				}
				$this->num_rows = count($this->_rows);
			}

			function fetch_assoc() {
				$row = current($this->_rows);
				if (!$row) {
					return $row;
				}
				$return = array();
				foreach ($this->_rows[0] as $key => $val) {
					$return[$key] = $row[$key];
				}
				next($this->_rows);
				return $return;
			}

			function fetch_row() {
				$return = $this->fetch_assoc();
				if (!$return) {
					return $return;
				}
				return array_values($return);
			}

			function fetch_field() {
				$keys = array_keys($this->_rows[0]);
				$name = $keys[$this->_offset++];
				return (object) array(
					'name' => $name,
					'charsetnr' => $this->_charset[$name],
				);
			}

		}
	}



	class Min_Driver extends Min_SQL {
		public $primary = "_id";
		
		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
			$select = ($select == array("*")
				? array()
				: array_fill_keys($select, true)
			);
			$sort = array();
			foreach ($order as $val) {
				$val = preg_replace('~ DESC$~', '', $val, 1, $count);
				$sort[$val] = ($count ? -1 : 1);
			}
			return new Min_Result($this->_conn->_db->selectCollection($table)
				->find(array(), $select)
				->sort($sort)
				->limit(+$limit)
				->skip($page * $limit)
			);
		}
		
		function insert($table, $set) {
			try {
				$return = $this->_conn->_db->selectCollection($table)->insert($set);
				$this->_conn->errno = $return['code'];
				$this->_conn->error = $return['err'];
				$this->_conn->last_id = $set['_id'];
				return !$return['err'];
			} catch (Exception $ex) {
				$this->_conn->error = $ex->getMessage();
				return false;
			}
		}
	}



	function connect() {
		global $adminer;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			return $connection;
		}
		return $connection->error;
	}

	function error() {
		global $connection;
		return h($connection->error);
	}

	function logged_user() {
		global $adminer;
		$credentials = $adminer->credentials();
		return $credentials[1];
	}

	function get_databases($flush) {
		global $connection;
		$return = array();
		$dbs = $connection->_link->listDBs();
		foreach ($dbs['databases'] as $db) {
			$return[] = $db['name'];
		}
		return $return;
	}

	function collations() {
		return array();
	}

	function db_collation($db, $collations) {
	}

	function count_tables($databases) {
		global $connection;
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = count($connection->_link->selectDB($db)->getCollectionNames(true));
		}
		return $return;
	}

	function tables_list() {
		global $connection;
		return array_fill_keys($connection->_db->getCollectionNames(true), 'table');
	}

	function table_status($name = "", $fast = false) {
		$return = array();
		foreach (tables_list() as $table => $type) {
			$return[$table] = array("Name" => $table);
			if ($name == $table) {
				return $return[$table];
			}
		}
		return $return;
	}

	function information_schema() {
	}

	function is_view($table_status) {
	}

	function drop_databases($databases) {
		global $connection;
		foreach ($databases as $db) {
			$response = $connection->_link->selectDB($db)->drop();
			if (!$response['ok']) {
				return false;
			}
		}
		return true;
	}

	function indexes($table, $connection2 = null) {
		global $connection;
		$return = array();
		foreach ($connection->_db->selectCollection($table)->getIndexInfo() as $index) {
			$descs = array();
			foreach ($index["key"] as $column => $type) {
				$descs[] = ($type == -1 ? '1' : null);
			}
			$return[$index["name"]] = array(
				"type" => ($index["name"] == "_id_" ? "PRIMARY" : ($index["unique"] ? "UNIQUE" : "INDEX")),
				"columns" => array_keys($index["key"]),
				"lengths" => array(),
				"descs" => $descs,
			);
		}
		return $return;
	}

	function fields($table) {
		return fields_from_edit();
	}

	function convert_field($field) {
	}

	function unconvert_field($field, $return) {
		return $return;
	}

	function foreign_keys($table) {
		return array();
	}

	function fk_support($table_status) {
	}

	function engines() {
		return array();
	}

	function found_rows($table_status, $where) {
		global $connection;
		//! don't call count_rows()
		return $connection->_db->selectCollection($_GET["select"])->count($where);
	}

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		global $connection;
		if ($table == "") {
			$connection->_db->createCollection($name);
			return true;
		}
	}

	function drop_tables($tables) {
		global $connection;
		foreach ($tables as $table) {
			$response = $connection->_db->selectCollection($table)->drop();
			if (!$response['ok']) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		global $connection;
		foreach ($tables as $table) {
			$response = $connection->_db->selectCollection($table)->remove();
			if (!$response['ok']) {
				return false;
			}
		}
		return true;
	}

	function alter_indexes($table, $alter) {
		global $connection;
		foreach ($alter as $val) {
			list($type, $name, $set) = $val;
			if ($set == "DROP") {
				$return = $connection->_db->command(array("deleteIndexes" => $table, "index" => $name));
			} else {
				$columns = array();
				foreach ($set as $column) {
					$column = preg_replace('~ DESC$~', '', $column, 1, $count);
					$columns[$column] = ($count ? -1 : 1);
				}
				$return = $connection->_db->selectCollection($table)->ensureIndex($columns, array(
					"unique" => ($type == "UNIQUE"),
					"name" => $name,
					//! "sparse"
				));
			}
			if ($return['errmsg']) {
				$connection->error = $return['errmsg'];
				return false;
			}
		}
		return true;
	}
	
	function last_id() {
		global $connection;
		return $connection->last_id;
	}

	function table($idf) {
		return $idf;
	}

	function idf_escape($idf) {
		return $idf;
	}

	function support($feature) {
		return preg_match("~database|indexes~", $feature);
	}

	$jush = "mongo";
	$operators = array("=");
	$functions = array();
	$grouping = array();
	$edit_functions = array(array("json"));
}
