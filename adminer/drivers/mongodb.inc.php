<?php
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

$drivers["mongodb"] = "MongoDB PHP7 (beta)";

if (isset($_GET["mongodb"])) {
	$possible_drivers = ["mongodb"];
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
				$options = [];
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
			var $num_rows, $_rows = [], $_offset = 0, $_charset = [];

			function __construct($result)
			{
				foreach ($result as $item) {
					$row = [];
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
				$return = [];
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

				return (object)[
					'name' => $name,
					'charsetnr' => $this->_charset[$name],
				];
			}

		}
	}


	class Min_Driver extends Min_SQL
	{
		public $primary = "_id";

		function select($table, $select, $where, $group, $order = [], $limit = 1, $page = 0, $print = false)
		{
			global $connection;
			$select = ($select == ["*"]
				? []
				: array_fill_keys($select, 1)
			);
			if (count($select) && !isset($select['_id'])) {
				$select['_id'] = 0;
			}
			$where = where_to_query($where);
			$sort = [];
			foreach ($order as $val) {
				$val = preg_replace('~ DESC$~', '', $val, 1, $count);
				$sort[$val] = ($count ? -1 : 1);
			}
			if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0) {
				$limit = $_GET['limit'];
			}
			$limit = min(200, max(1, (int)$limit));
			$skip = $page * $limit;
			$query = new Query($where, ['projection' => $select, 'limit' => $limit, 'skip' => $skip, 'sort' => $sort]);

			$results = $connection->_link->executeQuery("{$connection->_db_name}.$table", $query);

			return new Min_Result($results);
		}

		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n")
		{
			global $connection;
			$db = $connection->_db_name;
			$where = sql_query_where_parser($queryWhere);
			$bulk = new BulkWrite([]);
			if (isset($set['_id'])) {
				unset($set['_id']);
			}
			$removeFields = [];
			foreach ($set as $key => $value) {
				if ($value == 'NULL') {
					$removeFields[$key] = 1;
					unset($set[$key]);
				}
			}
			$update = ['$set' => $set];
			if (count($removeFields)) {
				$update['$unset'] = $removeFields;
			}
			$bulk->update($where, $update, ['upsert' => false]);
			$results = $connection->_link->executeBulkWrite("$db.$table", $bulk);
			$connection->affected_rows = $results->getModifiedCount();

			return true;
		}

		function delete($table, $queryWhere, $limit = 0)
		{
			global $connection;
			$db = $connection->_db_name;
			$where = sql_query_where_parser($queryWhere);
			$bulk = new BulkWrite([]);
			$bulk->delete($where, ['limit' => $limit]);
			$results = $connection->_link->executeBulkWrite("$db.$table", $bulk);
			$connection->affected_rows = $results->getDeletedCount();

			return true;
		}

		function insert($table, $set)
		{
			global $connection;
			$db = $connection->_db_name;
			$bulk = new BulkWrite([]);
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
		$return = [];
		$command = new Command(['listDatabases' => 1]);
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
		return [];
	}

	function db_collation($db, $collations)
	{
	}

	function count_tables($databases)
	{
		$return = [];
		return $return;
	}

	function tables_list()
	{
		global $connection;
		$command = new Command(['listCollections' => 1]);
		$results = $connection->_link->executeCommand($connection->_db_name, $command);
		$collections = [];
		foreach ($results as $result) {
			$collections[$result->name] = 'table';
		}

		return $collections;
	}

	function table_status($name = "", $fast = false)
	{
		$return = [];
		foreach (tables_list() as $table => $type) {
			$return[$table] = ["Name" => $table];
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
		$return = [];
		$command = new Command(['listIndexes' => $table]);
		$results = $connection->_link->executeCommand($connection->_db_name, $command);

		foreach ($results as $index) {
			$descs = [];
			$columns = [];
			foreach (get_object_vars($index->key) as $column => $type) {
				$descs[] = ($type == -1 ? '1' : null);
				$columns[] = $column;
			}
			$return[$index->name] = [
				"type" => ($index->name == "_id_" ? "PRIMARY" : (isset($index->unique) ? "UNIQUE" : "INDEX")),
				"columns" => $columns,
				"lengths" => [],
				"descs" => $descs,
			];
		}

		return $return;
	}

	function fields($table)
	{
		$fields = fields_from_edit();
		if (!count($fields)) {
			global $driver;
			$result = $driver->select($table, ["*"], null, null, [], 10);
			while ($row = $result->fetch_assoc()) {
				foreach ($row as $key => $val) {
					$row[$key] = null;
					$fields[$key] = [
						"field" => $key,
						"type" => "string",
						"null" => ($key != $driver->primary),
						"auto_increment" => ($key == $driver->primary),
						"privileges" => [
							"insert" => 1,
							"select" => 1,
							"update" => 1,
						],
					];
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
		return [];
	}

	function fk_support($table_status)
	{
	}

	function engines()
	{
		return [];
	}

	function found_rows($table_status, $where)
	{
		global $connection;
		$where = where_to_query($where);
		$command = new Command(['count' => $table_status['Name'], 'query' => $where]);
		$results = $connection->_link->executeCommand($connection->_db_name, $command);

		return $results->toArray()[0]->n;
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
				$return = $connection->_db->command(["deleteIndexes" => $table, "index" => $name]);
			} else {
				$columns = [];
				foreach ($set as $column) {
					$column = preg_replace('~ DESC$~', '', $column, 1, $count);
					$columns[$column] = ($count ? -1 : 1);
				}
				$return = $connection->_db->selectCollection($table)->ensureIndex(
					$columns,
					[
						"unique" => ($type == "UNIQUE"),
						"name" => $name,
						//! "sparse"
					]
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
		$where = [];
		foreach ($wheres as $whereStr) {
			$where[] = trim($whereStr);
		}
		if (count($wheresOr) == 1) {
			$wheresOr = [];
		} elseif (count($wheresOr) > 1) {
			$where = [];
		}
		return where_to_query($where, $wheresOr);
	}

	function where_to_query($whereAnd = [], $whereOr = [])
	{
		global $operators;
		$data = [];
		foreach (['and' => $whereAnd, 'or' => $whereOr] as $type => $where) {
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
							$val = new UTCDatetime((new \DateTime($val))->getTimestamp() * 1000);
							break;
						case '(date)!=':
							$op = '$ne';
							$val = new UTCDatetime((new \DateTime($val))->getTimestamp() * 1000);
							break;
						case '(date)>':
							$op = '$gt';
							$val = new UTCDatetime((new \DateTime($val))->getTimestamp() * 1000);
							break;
						case '(date)<':
							$op = '$lt';
							$val = new UTCDatetime((new \DateTime($val))->getTimestamp() * 1000);
							break;
						case '(date)>=':
							$op = '$gte';
							$val = new UTCDatetime((new \DateTime($val))->getTimestamp() * 1000);
							break;
						case '(date)<=':
							$op = '$lte';
							$val = new UTCDatetime((new \DateTime($val))->getTimestamp() * 1000);
							break;
						default:
							continue;
					}
					if ($type == 'and') {
						$data['$and'][] = [$col => [$op => $val]];
					} elseif ($type == 'or') {
						$data['$or'][] = [$col => [$op => $val]];
					}

				}
			}
		}

		return $data;
	}

	$jush = "mongodb";
	$operators = [
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
	];
	$functions = [];
	$grouping = [];
	$edit_functions = [["json"]];
}
