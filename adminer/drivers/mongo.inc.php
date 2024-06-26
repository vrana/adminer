<?php
$drivers["mongo"] = "MongoDB (alpha)";

if (isset($_GET["mongo"])) {
	define("DRIVER", "mongo");

	if (class_exists('MongoDB')) {
		class Min_DB {
			var $extension = "Mongo", $server_info = MongoClient::VERSION, $error, $last_id, $_link, $_db;

			function connect($uri, $options) {
				try {
					$this->_link = new MongoClient($uri, $options);
					if ($options["password"] != "") {
						$options["password"] = "";
						try {
							new MongoClient($uri, $options);
							$this->error = lang('Database does not support password.');
						} catch (Exception $e) {
							// this is what we want
						}
					}
				} catch (Exception $e) {
					$this->error = $e->getMessage();
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
							(is_a($val, 'MongoId') ? "ObjectId(\"$val\")" :
							(is_a($val, 'MongoDate') ? gmdate("Y-m-d H:i:s", $val->sec) . " GMT" :
							(is_a($val, 'MongoBinData') ? $val->bin : //! allow downloading
							(is_a($val, 'MongoRegex') ? "$val" :
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
					->limit($limit != "" ? +$limit : 0)
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

		function get_databases($flush) {
			global $connection;
			$return = array();
			$dbs = $connection->_link->listDBs();
			foreach ($dbs['databases'] as $db) {
				$return[] = $db['name'];
			}
			return $return;
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

		function found_rows($table_status, $where) {
			global $connection;
			//! don't call count_rows()
			return $connection->_db->selectCollection($_GET["select"])->count($where);
		}

		$operators = array("=");

	} elseif (class_exists('MongoDB\Driver\Manager')) {
		class Min_DB {
			var $extension = "MongoDB", $server_info = MONGODB_VERSION, $affected_rows, $error, $last_id;
			/** @var MongoDB\Driver\Manager */
			var $_link;
			var $_db, $_db_name;

			function connect($uri, $options) {
				$class = 'MongoDB\Driver\Manager';
				$this->_link = new $class($uri, $options);
				$this->executeCommand('admin', array('ping' => 1));
			}
			
			function executeCommand($db, $command) {
				$class = 'MongoDB\Driver\Command';
				try {
					return $this->_link->executeCommand($db, new $class($command));
				} catch (Exception $e) {
					$this->error = $e->getMessage();
					return array();
				}
			}
			
			function executeBulkWrite($namespace, $bulk, $counter) {
				try {
					$results = $this->_link->executeBulkWrite($namespace, $bulk);
					$this->affected_rows = $results->$counter();
					return true;
				} catch (Exception $e) {
					$this->error = $e->getMessage();
					return false;
				}
			}

			function query($query) {
				return false;
			}

			function select_db($database) {
				$this->_db_name = $database;
				return true;
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
						if (is_a($val, 'MongoDB\BSON\Binary')) {
							$this->_charset[$key] = 63;
						}
						$row[$key] =
							(is_a($val, 'MongoDB\BSON\ObjectID') ? 'MongoDB\BSON\ObjectID("' . "$val\")" :
							(is_a($val, 'MongoDB\BSON\UTCDatetime') ? $val->toDateTime()->format('Y-m-d H:i:s') :
							(is_a($val, 'MongoDB\BSON\Binary') ? $val->getData() : //! allow downloading
							(is_a($val, 'MongoDB\BSON\Regex') ? "$val" :
							(is_object($val) || is_array($val) ? json_encode($val, 256) : // 256 = JSON_UNESCAPED_UNICODE
							$val // MongoMinKey, MongoMaxKey
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


		class Min_Driver extends Min_SQL {
			public $primary = "_id";

			function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
				global $connection;
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
				if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0) {
					$limit = $_GET['limit'];
				}
				$limit = min(200, max(1, (int) $limit));
				$skip = $page * $limit;
				$class = 'MongoDB\Driver\Query';
				try {
					return new Min_Result($connection->_link->executeQuery("$connection->_db_name.$table", new $class($where, array('projection' => $select, 'limit' => $limit, 'skip' => $skip, 'sort' => $sort))));
				} catch (Exception $e) {
					$connection->error = $e->getMessage();
					return false;
				}
			}

			function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
				global $connection;
				$db = $connection->_db_name;
				$where = sql_query_where_parser($queryWhere);
				$class = 'MongoDB\Driver\BulkWrite';
				$bulk = new $class(array());
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
				return $connection->executeBulkWrite("$db.$table", $bulk, 'getModifiedCount');
			}

			function delete($table, $queryWhere, $limit = 0) {
				global $connection;
				$db = $connection->_db_name;
				$where = sql_query_where_parser($queryWhere);
				$class = 'MongoDB\Driver\BulkWrite';
				$bulk = new $class(array());
				$bulk->delete($where, array('limit' => $limit));
				return $connection->executeBulkWrite("$db.$table", $bulk, 'getDeletedCount');
			}

			function insert($table, $set) {
				global $connection;
				$db = $connection->_db_name;
				$class = 'MongoDB\Driver\BulkWrite';
				$bulk = new $class(array());
				if ($set['_id'] == '') {
					unset($set['_id']);
				}
				$bulk->insert($set);
				return $connection->executeBulkWrite("$db.$table", $bulk, 'getInsertedCount');
			}
		}

		function get_databases($flush) {
			global $connection;
			$return = array();
			foreach ($connection->executeCommand('admin', array('listDatabases' => 1)) as $dbs) {
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
			global $connection;
			$collections = array();
			foreach ($connection->executeCommand($connection->_db_name, array('listCollections' => 1)) as $result) {
				$collections[$result->name] = 'table';
			}
			return $collections;
		}

		function drop_databases($databases) {
			return false;
		}

		function indexes($table, $connection2 = null) {
			global $connection;
			$return = array();
			foreach ($connection->executeCommand($connection->_db_name, array('listIndexes' => $table)) as $index) {
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
			global $driver;
			$fields = fields_from_edit();
			if (!$fields) {
				$result = $driver->select($table, array("*"), null, null, array(), 10);
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
								),
							);
						}
					}
				}
			}
			return $fields;
		}

		function found_rows($table_status, $where) {
			global $connection;
			$where = where_to_query($where);
			$toArray = $connection->executeCommand($connection->_db_name, array('count' => $table_status['Name'], 'query' => $where))->toArray();
			return $toArray[0]->n;
		}

		function sql_query_where_parser($queryWhere) {
			$queryWhere = preg_replace('~^\sWHERE \(?\(?(.+?)\)?\)?$~', '\1', $queryWhere);
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
			global $adminer;
			$data = array();
			foreach (array('and' => $whereAnd, 'or' => $whereOr) as $type => $where) {
				if (is_array($where)) {
					foreach ($where as $expression) {
						list($col, $op, $val) = explode(" ", $expression, 3);
						if ($col == "_id" && preg_match('~^(MongoDB\\\\BSON\\\\ObjectID)\("(.+)"\)$~', $val, $match)) {
							list(, $class, $val) = $match;
							$val = new $class($val);
						}
						if (!in_array($op, $adminer->operators)) {
							continue;
						}
						if (preg_match('~^\(f\)(.+)~', $op, $match)) {
							$val = (float) $val;
							$op = $match[1];
						} elseif (preg_match('~^\(date\)(.+)~', $op, $match)) {
							$dateTime = new DateTime($val);
							$class = 'MongoDB\BSON\UTCDatetime';
							$val = new $class($dateTime->getTimestamp() * 1000);
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

		$operators = array(
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
	
	}

	function table($idf) {
		return $idf;
	}

	function idf_escape($idf) {
		return $idf;
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

	function create_database($db, $collation) {
		return true;
	}

	function last_id() {
		global $connection;
		return $connection->last_id;
	}

	function error() {
		global $connection;
		return h($connection->error);
	}

	function collations() {
		return array();
	}

	function logged_user() {
		global $adminer;
		$credentials = $adminer->credentials();
		return $credentials[1];
	}

	function connect() {
		global $adminer;
		$connection = new Min_DB;
		list($server, $username, $password) = $adminer->credentials();
		$options = array();
		if ($username . $password != "") {
			$options["username"] = $username;
			$options["password"] = $password;
		}
		$db = $adminer->database();
		if ($db != "") {
			$options["db"] = $db;
		}
		if (($auth_source = getenv("MONGO_AUTH_SOURCE"))) {
			$options["authSource"] = $auth_source;
		}
		$connection->connect("mongodb://$server", $options);
		if ($connection->error) {
			return $connection->error;
		}
		return $connection;
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

	function support($feature) {
		return preg_match("~database|indexes|descidx~", $feature);
	}

	function db_collation($db, $collations) {
	}

	function information_schema() {
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

	function engines() {
		return array();
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

	function driver_config() {
		global $operators;
		return array(
			'possible_drivers' => array("mongo", "mongodb"),
			'jush' => "mongo",
			'operators' => $operators,
			'functions' => array(),
			'grouping' => array(),
			'edit_functions' => array(array("json")),
		);
	}
}
