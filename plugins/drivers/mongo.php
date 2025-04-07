<?php
namespace Adminer;

add_driver("mongo", "MongoDB (alpha)");

if (isset($_GET["mongo"])) {
	define('Adminer\DRIVER', "mongo");

	if (class_exists('MongoDB\Driver\Manager')) {
		class Db extends SqlDb {
			public $extension = "MongoDB", $server_info = MONGODB_VERSION, $last_id;
			/** @var \MongoDB\Driver\Manager */ public $_link;
			public $_db, $_db_name;

			function attach($server, $username, $password): string {
				$options = array();
				if ($username . $password != "") {
					$options["username"] = $username;
					$options["password"] = $password;
				}
				$db = adminer()->database();
				if ($db != "") {
					$options["db"] = $db;
				}
				if (($auth_source = getenv("MONGO_AUTH_SOURCE"))) {
					$options["authSource"] = $auth_source;
				}
				$this->_link = new \MongoDB\Driver\Manager("mongodb://$server", $options);
				$this->executeDbCommand($options["db"], array('ping' => 1));
				return '';
			}

			function executeCommand($command) {
				return $this->executeDbCommand($this->_db_name, $command);
			}

			function executeDbCommand($db, $command) {
				try {
					return $this->_link->executeCommand($db, new \MongoDB\Driver\Command($command));
				} catch (\Exception $e) {
					$this->error = $e->getMessage();
					return array();
				}
			}

			function executeBulkWrite($namespace, $bulk, $counter) {
				try {
					$results = $this->_link->executeBulkWrite($namespace, $bulk);
					$this->affected_rows = $results->$counter();
					return true;
				} catch (\Exception $e) {
					$this->error = $e->getMessage();
					return false;
				}
			}

			function query($query, $unbuffered = false) {
				return false;
			}

			function select_db($database) {
				$this->_db_name = $database;
				return true;
			}

			function quote($string): string {
				return $string;
			}
		}

		class Result {
			public $num_rows;
			private $rows = array(), $offset = 0, $charset = array();

			function __construct($result) {
				foreach ($result as $item) {
					$row = array();
					foreach ($item as $key => $val) {
						if (is_a($val, 'MongoDB\BSON\Binary')) {
							$this->charset[$key] = 63;
						}
						$row[$key] =
							(is_a($val, 'MongoDB\BSON\ObjectID') ? 'MongoDB\BSON\ObjectID("' . "$val\")" :
							(is_a($val, 'MongoDB\BSON\UTCDatetime') ? $val->toDateTime()->format('Y-m-d H:i:s') :
							(is_a($val, 'MongoDB\BSON\Binary') ? $val->getData() : //! allow downloading
							(is_a($val, 'MongoDB\BSON\Regex') ? "$val" :
							(is_object($val) || is_array($val) ? json_encode($val, 256) : // 256 = JSON_UNESCAPED_UNICODE
							$val))))) // MongoMinKey, MongoMaxKey
						;
					}
					$this->rows[] = $row;
					foreach ($row as $key => $val) {
						if (!isset($this->rows[0][$key])) {
							$this->rows[0][$key] = null;
						}
					}
				}
				$this->num_rows = count($this->rows);
			}

			function fetch_assoc() {
				$row = current($this->rows);
				if (!$row) {
					return $row;
				}
				$return = array();
				foreach ($this->rows[0] as $key => $val) {
					$return[$key] = $row[$key];
				}
				next($this->rows);
				return $return;
			}

			function fetch_row() {
				$return = $this->fetch_assoc();
				if (!$return) {
					return $return;
				}
				return array_values($return);
			}

			function fetch_field(): \stdClass {
				$keys = array_keys($this->rows[0]);
				$name = $keys[$this->offset++];
				return (object) array(
					'name' => $name,
					'type' => 15,
					'charsetnr' => $this->charset[$name],
				);
			}
		}



		function get_databases($flush) {
			$return = array();
			foreach (connection()->executeCommand(array('listDatabases' => 1)) as $dbs) {
				foreach ($dbs->databases as $db) {
					$return[] = $db->name;
				}
			}
			return $return;
		}

		function count_tables($databases) {
			$return = array();
			return $return;
		}

		function tables_list() {
			$collections = array();
			foreach (connection()->executeCommand(array('listCollections' => 1)) as $result) {
				$collections[$result->name] = 'table';
			}
			return $collections;
		}

		function drop_databases($databases) {
			return false;
		}

		function indexes($table, $connection2 = null) {
			$return = array();
			foreach (connection()->executeCommand(array('listIndexes' => $table)) as $index) {
				$descs = array();
				$columns = array();
				foreach (get_object_vars($index->key) as $column => $type) {
					$descs[] = ($type == -1 ? '1' : null);
					$columns[] = $column;
				}
				$return[$index->name] = array(
					"type" => ($index->name == "_id_" ? "PRIMARY" : (isset($index->unique) ? "UNIQUE" : "INDEX")),
					"columns" => $columns,
					"lengths" => array(),
					"descs" => $descs,
				);
			}
			return $return;
		}

		function fields($table) {
			$driver = driver();
			$fields = fields_from_edit();
			if (!$fields) {
				$result = $driver->select($table, array("*"), array(), array(), array(), 10);
				if ($result) {
					while ($row = $result->fetch_assoc()) {
						foreach ($row as $key => $val) {
							$row[$key] = null;
							$fields[$key] = array(
								"field" => $key,
								"type" => "string",
								"null" => ($key != $driver->primary),
								"auto_increment" => ($key == $driver->primary),
								"privileges" => array(
									"insert" => 1,
									"select" => 1,
									"update" => 1,
									"where" => 1,
									"order" => 1,
								),
							);
						}
					}
				}
			}
			return $fields;
		}

		function found_rows($table_status, $where) {
			$where = where_to_query($where);
			$toArray = connection()->executeCommand(array('count' => $table_status['Name'], 'query' => $where))->toArray();
			return $toArray[0]->n;
		}

		function sql_query_where_parser($queryWhere) {
			$queryWhere = preg_replace('~^\s*WHERE\s*~', "", $queryWhere);
			while ($queryWhere[0] == "(") {
				$queryWhere = preg_replace('~^\((.*)\)$~', "$1", $queryWhere);
			}

			$wheres = explode(' AND ', $queryWhere);
			$wheresOr = explode(') OR (', $queryWhere);
			$where = array();
			foreach ($wheres as $whereStr) {
				$where[] = trim($whereStr);
			}
			if (count($wheresOr) == 1) {
				$wheresOr = array();
			} elseif (count($wheresOr) > 1) {
				$where = array();
			}
			return where_to_query($where, $wheresOr);
		}

		function where_to_query($whereAnd = array(), $whereOr = array()) {
			$data = array();
			foreach (array('and' => $whereAnd, 'or' => $whereOr) as $type => $where) {
				if (is_array($where)) {
					foreach ($where as $expression) {
						list($col, $op, $val) = explode(" ", $expression, 3);
						if ($col == "_id" && preg_match('~^(MongoDB\\\\BSON\\\\ObjectID)\("(.+)"\)$~', $val, $match)) {
							list(, $class, $val) = $match;
							$val = new $class($val);
						}
						if (!in_array($op, adminer()->operators())) {
							continue;
						}
						if (preg_match('~^\(f\)(.+)~', $op, $match)) {
							$val = (float) $val;
							$op = $match[1];
						} elseif (preg_match('~^\(date\)(.+)~', $op, $match)) {
							$dateTime = new \DateTime($val);
							$val = new \MongoDB\BSON\UTCDatetime($dateTime->getTimestamp() * 1000);
							$op = $match[1];
						}
						switch ($op) {
							case '=':
								$op = '$eq';
								break;
							case '!=':
								$op = '$ne';
								break;
							case '>':
								$op = '$gt';
								break;
							case '<':
								$op = '$lt';
								break;
							case '>=':
								$op = '$gte';
								break;
							case '<=':
								$op = '$lte';
								break;
							case 'regex':
								$op = '$regex';
								break;
							default:
								continue 2;
						}
						if ($type == 'and') {
							$data['$and'][] = array($col => array($op => $val));
						} elseif ($type == 'or') {
							$data['$or'][] = array($col => array($op => $val));
						}
					}
				}
			}
			return $data;
		}
	}



	class Driver extends SqlDriver {
		static $extensions = array("mongodb");
		static $jush = "mongo";

		public $insertFunctions = array("json");

		public $operators = array(
			"=",
			"!=",
			">",
			"<",
			">=",
			"<=",
			"regex",
			"(f)=",
			"(f)!=",
			"(f)>",
			"(f)<",
			"(f)>=",
			"(f)<=",
			"(date)=",
			"(date)!=",
			"(date)>",
			"(date)<",
			"(date)>=",
			"(date)<=",
		);

		public $primary = "_id";

		static function connect($server, $username, $password) {
			if ($server == "") {
				$server = "localhost:27017";
			}
			return parent::connect($server, $username, $password);
		}

		function select($table, array $select, array $where, array $group, array $order = array(), $limit = 1, $page = 0, $print = false) {
			$select = ($select == array("*")
				? array()
				: array_fill_keys($select, 1)
			);
			if (count($select) && !isset($select['_id'])) {
				$select['_id'] = 0;
			}
			$where = where_to_query($where);
			$sort = array();
			foreach ($order as $val) {
				$val = preg_replace('~ DESC$~', '', $val, 1, $count);
				$sort[$val] = ($count ? -1 : 1);
			}
			$limit = min(200, max(1, $limit));
			$skip = $page * $limit;
			try {
				return new Result($this->conn->_link->executeQuery($this->conn->_db_name . ".$table", new \MongoDB\Driver\Query($where, array('projection' => $select, 'limit' => $limit, 'skip' => $skip, 'sort' => $sort))));
			} catch (\Exception $e) {
				$this->conn->error = $e->getMessage();
				return false;
			}
		}

		function update($table, array $set, $queryWhere, $limit = 0, $separator = "\n") {
			$db = $this->conn->_db_name;
			$where = sql_query_where_parser($queryWhere);
			$bulk = new \MongoDB\Driver\BulkWrite(array());
			if (isset($set['_id'])) {
				unset($set['_id']);
			}
			$removeFields = array();
			foreach ($set as $key => $value) {
				if ($value == 'NULL') {
					$removeFields[$key] = 1;
					unset($set[$key]);
				}
			}
			$update = array('$set' => $set);
			if (count($removeFields)) {
				$update['$unset'] = $removeFields;
			}
			$bulk->update($where, $update, array('upsert' => false));
			return $this->conn->executeBulkWrite("$db.$table", $bulk, 'getModifiedCount');
		}

		function delete($table, $queryWhere, $limit = 0) {
			$db = $this->conn->_db_name;
			$where = sql_query_where_parser($queryWhere);
			$bulk = new \MongoDB\Driver\BulkWrite(array());
			$bulk->delete($where, array('limit' => $limit));
			return $this->conn->executeBulkWrite("$db.$table", $bulk, 'getDeletedCount');
		}

		function insert($table, array $set) {
			$db = $this->conn->_db_name;
			$bulk = new \MongoDB\Driver\BulkWrite(array());
			if ($set['_id'] == '') {
				unset($set['_id']);
			}
			$bulk->insert($set);
			return $this->conn->executeBulkWrite("$db.$table", $bulk, 'getInsertedCount');
		}
	}



	function table($idf) {
		return $idf;
	}

	function idf_escape($idf) {
		return $idf;
	}

	function table_status($name = "", $fast = false) {
		$return = array();
		foreach (($name != "" ? array($name => 1) : tables_list()) as $table => $type) {
			$return[$table] = array("Name" => $table, "Engine" => "");
		}
		return $return;
	}

	function create_database($db, $collation) {
		return true;
	}

	function last_id($result) {
		return connection()->last_id;
	}

	function error() {
		return h(connection()->error);
	}

	function collations() {
		return array();
	}

	function logged_user() {
		$credentials = adminer()->credentials();
		return $credentials[1];
	}

	function alter_indexes($table, $alter) {
		foreach ($alter as $val) {
			list($type, $name, $set) = $val;
			if ($set == "DROP") {
				$return = connection()->_db->command(array("deleteIndexes" => $table, "index" => $name));
			} else {
				$columns = array();
				foreach ($set as $column) {
					$column = preg_replace('~ DESC$~', '', $column, 1, $count);
					$columns[$column] = ($count ? -1 : 1);
				}
				$return = connection()->_db->selectCollection($table)->ensureIndex($columns, array(
					"unique" => ($type == "UNIQUE"),
					"name" => $name,
					//! "sparse"
				));
			}
			if ($return['errmsg']) {
				connection()->error = $return['errmsg'];
				return false;
			}
		}
		return true;
	}

	function support($feature) {
		return preg_match("~database|indexes|descidx~", $feature);
	}

	function db_collation($db, $collations) {
	}

	function information_schema($db) {
	}

	function is_view($table_status) {
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

	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		if ($table == "") {
			connection()->_db->createCollection($name);
			return true;
		}
	}

	function drop_tables($tables) {
		foreach ($tables as $table) {
			$response = connection()->_db->selectCollection($table)->drop();
			if (!$response['ok']) {
				return false;
			}
		}
		return true;
	}

	function truncate_tables($tables) {
		foreach ($tables as $table) {
			$response = connection()->_db->selectCollection($table)->remove();
			if (!$response['ok']) {
				return false;
			}
		}
		return true;
	}
}
