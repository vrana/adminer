<?php
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

$drivers["mongodb"] = "MongoDB PHP7 (beta)";

if (isset($_GET["mongodb"])) {
	$possible_drivers = array("mongodb");
	define("DRIVER", "mongodb");

	if (class_exists('MongoDb\Driver\Manager')) {
		class Min_DB
		{
			var $extension = "MongoDb", $error, $last_id;
			/** @var Manager */
			var $_link;
			var $_db, $_db_name;

			function connect($server, $username, $password)
			{
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
					$this->_link = new MongoDb\Driver\Manager("mongodb://$server", $options);

					return true;
				} catch (Exception $ex) {
					$this->error = $ex->getMessage();

					return false;
				}
			}

			function query($query)
			{
				return false;
			}

			function select_db($database)
			{
				try {
					$this->_db_name = $database;

					return true;
				} catch (Exception $ex) {
					$this->error = $ex->getMessage();

					return false;
				}
			}

			function quote($string)
			{
				return $string;
			}

		}

		class Min_Result
		{
			var $num_rows, $_rows = array(), $_offset = 0, $_charset = array();

			function __construct($result)
			{
				foreach ($result as $item) {
					$row = array();
					foreach ($item as $key => $val) {
						if (is_a($val, 'MongoDB\BSON\Binary')) {
							$this->_charset[$key] = 63;
						}
						$row[$key] =
							(is_a($val, 'MongoDB\BSON\ObjectID') ? 'ObjectId("' . strval($val) . '")' :
								(is_a($val, 'MongoDB\BSON\UTCDatetime') ? $val->toDateTime()->format('Y-m-d H:i:s') :
									(is_a($val, 'MongoDB\BSON\Binary') ? $val->bin : //! allow downloading
										(is_a($val, 'MongoDB\BSON\Regex') ? strval($val) :
											(is_object($val) ? json_encode(
												$val,
												JSON_UNESCAPED_UNICODE
											) : // MongoMinKey, MongoMaxKey
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
				$this->num_rows = $result->count;
			}

			function fetch_assoc()
			{
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

			function fetch_row()
			{
				$return = $this->fetch_assoc();
				if (!$return) {
					return $return;
				}

				return array_values($return);
			}

			function fetch_field()
			{
				$keys = array_keys($this->_rows[0]);
				$name = $keys[$this->_offset++];

				return (object)array(
					'name' => $name,
					'charsetnr' => $this->_charset[$name],
				);
			}

		}
	}


	class Min_Driver extends Min_SQL
	{
		public $primary = "_id";

		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false)
		{
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
			$limit = min(200, max(1, (int)$limit));
			$skip = $page * $limit;
			$query = new Query($where, array('projection' => $select, 'limit' => $limit, 'skip' => $skip, 'sort' => $sort));

			$results = $connection->_link->executeQuery("{$connection->_db_name}.$table", $query);

			return new Min_Result($results);
		}

		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n")
		{
			global $connection;
			$db = $connection->_db_name;
			$where = sql_query_where_parser($queryWhere);
			$bulk = new BulkWrite(array());
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
			$results = $connection->_link->executeBulkWrite("$db.$table", $bulk);
			$connection->affected_rows = $results->getModifiedCount();

			return true;
		}

		function delete($table, $queryWhere, $limit = 0)
		{
			global $connection;
			$db = $connection->_db_name;
			$where = sql_query_where_parser($queryWhere);
			$bulk = new BulkWrite(array());
			$bulk->delete($where, array('limit' => $limit));
			$results = $connection->_link->executeBulkWrite("$db.$table", $bulk);
			$connection->affected_rows = $results->getDeletedCount();

			return true;
		}

		function insert($table, $set)
		{
			global $connection;
			$db = $connection->_db_name;
			$bulk = new BulkWrite(array());
			if (isset($set['_id']) && empty($set['_id'])) {
				unset($set['_id']);
			}
			$bulk->insert($set);
			$results = $connection->_link->executeBulkWrite("$db.$table", $bulk);
			$connection->affected_rows = $results->getInsertedCount();

			return true;
		}
	}


	function connect()
	{
		global $adminer;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			return $connection;
		}

		return $connection->error;
	}

	function error()
	{
		global $connection;

		return h($connection->error);
	}

	function logged_user()
	{
		global $adminer;
		$credentials = $adminer->credentials();

		return $credentials[1];
	}

	function get_databases($flush)
	{
		/** @var $connection Min_DB */
		global $connection;
		$return = array();
		$command = new Command(array('listDatabases' => 1));
		$results = $connection->_link->executeCommand('admin', $command);
		foreach ($results as $dbs) {
			foreach ($dbs->databases as $db) {
				$return[] = $db->name;
			}
		}

		return $return;
	}

	function collations()
	{
		return array();
	}

	function db_collation($db, $collations)
	{
	}

	function count_tables($databases)
	{
		$return = array();
		return $return;
	}

	function tables_list()
	{
		global $connection;
		$command = new Command(array('listCollections' => 1));
		$results = $connection->_link->executeCommand($connection->_db_name, $command);
		$collections = array();
		foreach ($results as $result) {
			$collections[$result->name] = 'table';
		}

		return $collections;
	}

	function table_status($name = "", $fast = false)
	{
		$return = array();
		foreach (tables_list() as $table => $type) {
			$return[$table] = array("Name" => $table);
			if ($name == $table) {
				return $return[$table];
			}
		}

		return $return;
	}

	function information_schema()
	{
	}

	function is_view($table_status)
	{
	}

	function drop_databases($databases)
	{
		return false;
	}

	function indexes($table, $connection2 = null)
	{
		global $connection;
		$return = array();
		$command = new Command(array('listIndexes' => $table));
		$results = $connection->_link->executeCommand($connection->_db_name, $command);

		foreach ($results as $index) {
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

	function fields($table)
	{
		$fields = fields_from_edit();
		if (!count($fields)) {
			global $driver;
			$result = $driver->select($table, array("*"), null, null, array(), 10);
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

		return $fields;
	}

	function convert_field($field)
	{
	}

	function unconvert_field($field, $return)
	{
		return $return;
	}

	function foreign_keys($table)
	{
		return array();
	}

	function fk_support($table_status)
	{
	}

	function engines()
	{
		return array();
	}

	function found_rows($table_status, $where)
	{
		global $connection;
		$where = where_to_query($where);
		$command = new Command(array('count' => $table_status['Name'], 'query' => $where));
		$results = $connection->_link->executeCommand($connection->_db_name, $command);

		$toArray = $results->toArray();
		return $toArray[0]->n;
	}

	function alter_table(
		$table,
		$name,
		$fields,
		$foreign,
		$comment,
		$engine,
		$collation,
		$auto_increment,
		$partitioning
	)
	{
		global $connection;
		if ($table == "") {
			$connection->_db->createCollection($name);

			return true;
		}
		return false;
	}

	function drop_tables($tables)
	{
		global $connection;
		foreach ($tables as $table) {
			$response = $connection->_db->selectCollection($table)->drop();
			if (!$response['ok']) {
				return false;
			}
		}

		return true;
	}

	function truncate_tables($tables)
	{
		global $connection;
		foreach ($tables as $table) {
			$response = $connection->_db->selectCollection($table)->remove();
			if (!$response['ok']) {
				return false;
			}
		}

		return true;
	}

	function alter_indexes($table, $alter)
	{
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
				$return = $connection->_db->selectCollection($table)->ensureIndex(
					$columns,
					array(
						"unique" => ($type == "UNIQUE"),
						"name" => $name,
						//! "sparse"
					)
				);
			}
			if ($return['errmsg']) {
				$connection->error = $return['errmsg'];

				return false;
			}
		}

		return true;
	}

	function last_id()
	{
		global $connection;

		return $connection->last_id;
	}

	function table($idf)
	{
		return $idf;
	}

	function idf_escape($idf)
	{
		return $idf;
	}

	function support($feature)
	{
		return preg_match("~database|indexes~", $feature);
	}

	function sql_query_where_parser($queryWhere)
	{
		$queryWhere = trim(preg_replace('/WHERE[\s]?[(]?\(?/', '', $queryWhere));
		$queryWhere = preg_replace('/\)\)\)$/', ')', $queryWhere);
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

	function where_to_query($whereAnd = array(), $whereOr = array())
	{
		global $operators;
		$data = array();
		foreach (array('and' => $whereAnd, 'or' => $whereOr) as $type => $where) {
			if (is_array($where)) {
				foreach ($where as $expression) {
					list($col, $op, $val) = explode(" ", $expression, 3);
					if ($col == "_id") {
						$val = str_replace('ObjectId("', "", $val);
						$val = str_replace('")', "", $val);
						$val = new ObjectID($val);
					}
					if (!in_array($op, $operators)) {
						continue;
					}
					$dateTime = (new \DateTime($val));
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
						case '(f)=':
							$op = '$eq';
							$val = (float)$val;
							break;
						case '(f)!=':
							$op = '$ne';
							$val = (float)$val;
							break;
						case '(f)>':
							$op = '$gt';
							$val = (float)$val;
							break;
						case '(f)<':
							$op = '$lt';
							$val = (float)$val;
							break;
						case '(f)>=':
							$op = '$gte';
							$val = (float)$val;
							break;
						case '(f)<=':
							$op = '$lte';
							$val = (float)$val;
							break;
						case '(date)=':
							$op = '$eq';
							$val = new UTCDatetime($dateTime->getTimestamp() * 1000);
							break;
						case '(date)!=':
							$op = '$ne';
							$val = new UTCDatetime($dateTime->getTimestamp() * 1000);
							break;
						case '(date)>':
							$op = '$gt';
							$val = new UTCDatetime($dateTime->getTimestamp() * 1000);
							break;
						case '(date)<':
							$op = '$lt';
							$val = new UTCDatetime($dateTime->getTimestamp() * 1000);
							break;
						case '(date)>=':
							$op = '$gte';
							$val = new UTCDatetime($dateTime->getTimestamp() * 1000);
							break;
						case '(date)<=':
							$op = '$lte';
							$val = new UTCDatetime($dateTime->getTimestamp() * 1000);
							break;
						default:
							continue;
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

	$jush = "mongodb";
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
	$functions = array();
	$grouping = array();
	$edit_functions = array(array("json"));
}
