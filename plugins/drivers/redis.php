<?php
//! clone

namespace Adminer;

add_driver("redis", "Redis");

if (isset($_GET["redis"])) {
	define('Adminer\DRIVER', "redis");

	/** Split a command to arguments the same way as redis-cli does
	* @return list<string>|false false for unbalanced quotes
	*/
	function parse_command($command) {
		$escapes = array('n' => "\n", 'r' => "\r", 't' => "\t", 'a' => "\x07", 'b' => "\x08");
		$length = strlen($command);
		$args = array();
		$i = 0;
		while ($i < $length) {
			if (preg_match('~\s~', $command[$i])) {
				$i++;
				continue;
			}
			$arg = '';
			$quote = '';
			for (; $i < $length; $i++) {
				$c = $command[$i];
				if ($quote == '"') {
					if ($c == '\\' && $i + 1 < $length) {
						$c = $command[++$i];
						if ($c == 'x' && $i + 2 < $length && ctype_xdigit(substr($command, $i + 1, 2))) {
							$arg .= chr(hexdec(substr($command, $i + 1, 2)));
							$i += 2;
						} else {
							$arg .= idx($escapes, $c, $c);
						}
					} elseif ($c == '"') {
						$quote = '';
					} else {
						$arg .= $c;
					}
				} elseif ($quote == "'") {
					if ($c == '\\' && $i + 1 < $length && $command[$i + 1] == "'") {
						$arg .= $command[++$i];
					} elseif ($c == "'") {
						$quote = '';
					} else {
						$arg .= $c;
					}
				} elseif (preg_match('~\s~', $c)) {
					break;
				} elseif ($c == '"' || $c == "'") {
					$quote = $c;
				} else {
					$arg .= $c;
				}
			}
			if ($quote != '') {
				return false;
			}
			$args[] = $arg;
		}
		return $args;
	}

	/** Escape an argument as a double quoted string in the redis-cli syntax */
	function quote_arg($arg) {
		static $escapes, $escapes_binary;
		if (!$escapes) {
			// redis-cli doesn't understand octal escapes
			$escapes = array("\\" => '\\\\', '"' => '\"', "\n" => '\n', "\r" => '\r', "\t" => '\t', "\x07" => '\a', "\x08" => '\b', "\x7F" => '\x7f');
			for ($i = 0; $i < 32; $i++) {
				if (!isset($escapes[chr($i)])) {
					$escapes[chr($i)] = sprintf('\x%02x', $i);
				}
			}
			$escapes_binary = $escapes;
			for ($i = 128; $i < 256; $i++) {
				$escapes_binary[chr($i)] = sprintf('\x%02x', $i);
			}
		}
		$arg = (string) $arg;
		// bytes >= 0x80 are escaped only outside UTF-8 where there's no readability to preserve
		return '"' . strtr($arg, (is_utf8($arg) ? $escapes : $escapes_binary)) . '"';
	}

	/** Format arguments as a command in the redis-cli syntax
	* @param list<string> $args
	*/
	function format_command($args) {
		$return = array();
		foreach ($args as $arg) {
			$arg = (string) $arg;
			$return[] = ($arg == '' || !is_utf8($arg) || preg_match('~[\s"\'\\\\\x00-\x1F\x7F]~', $arg) ? quote_arg($arg) : $arg);
		}
		return implode(" ", $return);
	}

	/** Escape a value in the redis-cli syntax if it can't be printed as is
	* @param string|mixed[]|null $val
	* @return string|mixed[]|null
	*/
	function escape_value($val) {
		if (is_array($val)) {
			return array_map('Adminer\escape_value', $val);
		}
		// a printable value starting and ending with a quote is escaped too, otherwise unescape_value() couldn't tell it apart
		return ($val !== null && (!is_utf8($val) || preg_match('~^".*"$~s', $val)) ? quote_arg($val) : $val);
	}

	/** Get a value escaped by escape_value(), keep it intact if it's not a single quoted string */
	function unescape_value($val) {
		if (!preg_match('~^".*"$~s', $val)) {
			return $val;
		}
		$args = parse_command($val);
		return ($args && count($args) == 1 ? $args[0] : $val);
	}

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
			$this->fp = @fsockopen($host, $port, $errno, $error);
			if (!$this->fp) {
				return $error;
			}
			// PING verifies that a server without a password really doesn't require one
			if (!$this->send($password != "" ? array("AUTH", $username, $password) : array("PING"))) {
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
			return quote_arg(unescape_value($string)); // the values are used as arguments of the commands
		}

		function query($query, $unbuffered = false) {
			$this->error = ''; // the error is checked after each command in SQL command
			$args = parse_command($query);
			if ($args === false) {
				$this->error = "Unbalanced quotes";
				return false;
			}
			if (!$args) {
				$this->error = "Query was empty";
				return false;
			}
			$reply = $this->send($args);
			if ($reply === false) {
				return false;
			}
			$name = strtoupper($args[0]);
			$rows = array();
			foreach ((is_array($reply) ? $reply : array($reply)) as $val) {
				$rows[] = array($name => escape_value($val)); // nested arrays are printed as nested tables by select_value()
			}
			return new Result($rows);
		}

		function send($args) {
			return first($this->sendMulti(array($args)));
		}

		/** Send commands in a pipeline
		* @param list<list<string|int>> $commands
		* @return list<mixed> replies in the order of the commands
		*/
		function sendMulti($commands) {
			$cmd = '';
			foreach ($commands as $args) {
				$cmd .= "*" . count($args) . "\r\n";
				foreach ($args as $arg) {
					$cmd .= '$' . strlen($arg) . "\r\n$arg\r\n";
				}
			}
			fwrite($this->fp, $cmd);
			$return = array();
			$count = count($commands);
			for ($i = 0; $i < $count; $i++) {
				$return[] = $this->read(); // all replies must be read even after an error, otherwise the next command would get them
			}
			return $return;
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
		public $num_rows;
		private $result;
		private $fields;

		function __construct($result) {
			$this->result = $result;
			$this->num_rows = count($result);
			$this->fields = array_keys(idx($result, 0, array()));
		}

		function fetch_assoc() {
			$row = current($this->result);
			next($this->result);
			return $row;
		}

		function fetch_row() {
			$row = $this->fetch_assoc();
			return ($row ? array_values($row) : false);
		}

		function fetch_field(): \stdClass {
			$field = current($this->fields);
			next($this->fields);
			return (object) array('name' => $field, 'type' => 15, 'charsetnr' => 0);
		}
	}

	class Driver extends SqlDriver {
		static $jush = "redis";

		public $delimiter = "\n"; // commands are separated by a newline as in redis-cli
		public $operators = array("*");

		/** Get the JUSH module, generated by compile.php from externals/jush/modules/ */
		static function jushModule(): string {
			return <<<'JS'
jush.tr.redis={quo:/"/,apo:/'/};jush.slugs.redis=name=>name.toLowerCase().replace(/\s+/g,'-');jush.build_links2('redis','https://redis.io/docs/latest/commands/$1/',/(^[ \t]*)/,/(\b)/gim,{'$1':/(ACL\s+CAT|ACL\s+DELUSER|ACL\s+DRYRUN|ACL\s+GENPASS|ACL\s+GETUSER|ACL\s+HELP|ACL\s+LIST|ACL\s+LOAD|ACL\s+LOG|ACL\s+SAVE|ACL\s+SETUSER|ACL\s+USERS|ACL\s+WHOAMI|ACL|APPEND|ARCOUNT|ARDEL|ARDELRANGE|ARGET|ARGETRANGE|ARGREP|ARINFO|ARINSERT|ARLASTITEMS|ARLEN|ARMGET|ARMSET|ARNEXT|AROP|ARRING|ARSCAN|ARSEEK|ARSET|ASKING|AUTH|BACKUP\s+ABORT|BACKUP\s+CLEANUP|BACKUP\s+HELP|BACKUP\s+LIST|BACKUP\s+SEAL|BACKUP\s+START|BACKUP\s+STATUS|BACKUP|BGREWRITEAOF|BGSAVE|BITCOUNT|BITFIELD|BITFIELD_RO|BITOP|BITPOS|BLMOVE|BLMOVEM|BLMPOP|BLPOP|BRPOP|BRPOPLPUSH|BZMPOP|BZPOPMAX|BZPOPMIN|CLIENT\s+CACHING|CLIENT\s+GETNAME|CLIENT\s+GETREDIR|CLIENT\s+HELP|CLIENT\s+ID|CLIENT\s+INFO|CLIENT\s+KILL|CLIENT\s+LIST|CLIENT\s+NO-EVICT|CLIENT\s+NO-TOUCH|CLIENT\s+PAUSE|CLIENT\s+REPLY|CLIENT\s+SETINFO|CLIENT\s+SETNAME|CLIENT\s+TRACKING|CLIENT\s+TRACKINGINFO|CLIENT\s+UNBLOCK|CLIENT\s+UNPAUSE|CLIENT|CLUSTER\s+ADDSLOTS|CLUSTER\s+ADDSLOTSRANGE|CLUSTER\s+BUMPEPOCH|CLUSTER\s+COUNT-FAILURE-REPORTS|CLUSTER\s+COUNTKEYSINSLOT|CLUSTER\s+DELSLOTS|CLUSTER\s+DELSLOTSRANGE|CLUSTER\s+FAILOVER|CLUSTER\s+FLUSHSLOTS|CLUSTER\s+FORGET|CLUSTER\s+GETKEYSINSLOT|CLUSTER\s+HELP|CLUSTER\s+INFO|CLUSTER\s+KEYSLOT|CLUSTER\s+LINKS|CLUSTER\s+MEET|CLUSTER\s+MIGRATION|CLUSTER\s+MYID|CLUSTER\s+MYSHARDID|CLUSTER\s+NODES|CLUSTER\s+REPLICAS|CLUSTER\s+REPLICATE|CLUSTER\s+RESET|CLUSTER\s+SAVECONFIG|CLUSTER\s+SET-CONFIG-EPOCH|CLUSTER\s+SETSLOT|CLUSTER\s+SHARDS|CLUSTER\s+SLAVES|CLUSTER\s+SLOT-STATS|CLUSTER\s+SLOTS|CLUSTER\s+SYNCSLOTS|CLUSTER|COMMAND\s+COUNT|COMMAND\s+DOCS|COMMAND\s+GETKEYS|COMMAND\s+GETKEYSANDFLAGS|COMMAND\s+HELP|COMMAND\s+INFO|COMMAND\s+LIST|COMMAND|CONFIG\s+GET|CONFIG\s+HELP|CONFIG\s+RESETSTAT|CONFIG\s+REWRITE|CONFIG\s+SET|CONFIG|COPY|DBSIZE|DEBUG|DECR|DECRBY|DEL|DELEX|DIGEST|DISCARD|DUMP|ECHO|EVAL|EVALSHA|EVALSHA_RO|EVAL_RO|EXEC|EXISTS|EXPIRE|EXPIREAT|EXPIRETIME|FAILOVER|FCALL|FCALL_RO|FLUSHALL|FLUSHDB|FUNCTION\s+DELETE|FUNCTION\s+DUMP|FUNCTION\s+FLUSH|FUNCTION\s+HELP|FUNCTION\s+KILL|FUNCTION\s+LIST|FUNCTION\s+LOAD|FUNCTION\s+RESTORE|FUNCTION\s+STATS|FUNCTION|GEOADD|GEODIST|GEOHASH|GEOPOS|GEORADIUS|GEORADIUSBYMEMBER|GEORADIUSBYMEMBER_RO|GEORADIUS_RO|GEOSEARCH|GEOSEARCHSTORE|GET|GETBIT|GETDEL|GETEX|GETRANGE|GETSET|HDEL|HELLO|HEXISTS|HEXPIRE|HEXPIREAT|HEXPIRETIME|HGET|HGETALL|HGETDEL|HGETEX|HIMPORT\s+DISCARD|HIMPORT\s+DISCARDALL|HIMPORT\s+PREPARE|HIMPORT\s+SET|HIMPORT|HINCRBY|HINCRBYFLOAT|HKEYS|HLEN|HMGET|HMSET|HOTKEYS\s+GET|HOTKEYS\s+HELP|HOTKEYS\s+RESET|HOTKEYS\s+START|HOTKEYS\s+STOP|HOTKEYS|HPERSIST|HPEXPIRE|HPEXPIREAT|HPEXPIRETIME|HPTTL|HRANDFIELD|HSCAN|HSET|HSETEX|HSETNX|HSTRLEN|HTTL|HVALS|INCR|INCRBY|INCRBYFLOAT|INCREX|INFO|KEYS|LASTSAVE|LATENCY\s+DOCTOR|LATENCY\s+GRAPH|LATENCY\s+HELP|LATENCY\s+HISTOGRAM|LATENCY\s+HISTORY|LATENCY\s+LATEST|LATENCY\s+RESET|LATENCY|LCS|LINDEX|LINSERT|LLEN|LMOVE|LMOVEM|LMPOP|LOLWUT|LPOP|LPOS|LPUSH|LPUSHX|LRANGE|LREM|LSET|LTRIM|MEMORY\s+DOCTOR|MEMORY\s+HELP|MEMORY\s+MALLOC-STATS|MEMORY\s+PURGE|MEMORY\s+STATS|MEMORY\s+USAGE|MEMORY|MGET|MIGRATE|MODULE\s+HELP|MODULE\s+LIST|MODULE\s+LOAD|MODULE\s+LOADEX|MODULE\s+UNLOAD|MODULE|MONITOR|MOVE|MSET|MSETEX|MSETNX|MULTI|OBJECT\s+ENCODING|OBJECT\s+FREQ|OBJECT\s+HELP|OBJECT\s+IDLETIME|OBJECT\s+REFCOUNT|OBJECT|PERSIST|PEXPIRE|PEXPIREAT|PEXPIRETIME|PFADD|PFCOUNT|PFDEBUG|PFMERGE|PFSELFTEST|PING|PSETEX|PSUBSCRIBE|PSYNC|PTTL|PUBLISH|PUBSUB\s+CHANNELS|PUBSUB\s+HELP|PUBSUB\s+NUMPAT|PUBSUB\s+NUMSUB|PUBSUB\s+SHARDCHANNELS|PUBSUB\s+SHARDNUMSUB|PUBSUB|PUNSUBSCRIBE|QUIT|RANDOMKEY|READONLY|READWRITE|RENAME|RENAMENX|REPLCONF|REPLICAOF|RESET|RESTORE-ASKING|RESTORE|ROLE|RPOP|RPOPLPUSH|RPUSH|RPUSHX|SADD|SAVE|SCAN|SCARD|SCRIPT\s+DEBUG|SCRIPT\s+EXISTS|SCRIPT\s+FLUSH|SCRIPT\s+HELP|SCRIPT\s+KILL|SCRIPT\s+LOAD|SCRIPT|SDIFF|SDIFFCARD|SDIFFSTORE|SELECT|SENTINEL\s+CKQUORUM|SENTINEL\s+CONFIG|SENTINEL\s+DEBUG|SENTINEL\s+FAILOVER|SENTINEL\s+FLUSHCONFIG|SENTINEL\s+GET-MASTER-ADDR-BY-NAME|SENTINEL\s+HELP|SENTINEL\s+INFO-CACHE|SENTINEL\s+IS-MASTER-DOWN-BY-ADDR|SENTINEL\s+MASTER|SENTINEL\s+MASTERS|SENTINEL\s+MONITOR|SENTINEL\s+MYID|SENTINEL\s+PENDING-SCRIPTS|SENTINEL\s+REMOVE|SENTINEL\s+REPLICAS|SENTINEL\s+RESET|SENTINEL\s+SENTINELS|SENTINEL\s+SET|SENTINEL\s+SIMULATE-FAILURE|SENTINEL\s+SLAVES|SENTINEL|SET|SETBIT|SETEX|SETNX|SETRANGE|SFLUSH|SHUTDOWN|SINTER|SINTERCARD|SINTERSTORE|SISMEMBER|SLAVEOF|SLOWLOG\s+GET|SLOWLOG\s+HELP|SLOWLOG\s+LEN|SLOWLOG\s+RESET|SLOWLOG|SMEMBERS|SMISMEMBER|SMOVE|SORT|SORT_RO|SPOP|SPUBLISH|SRANDMEMBER|SREM|SSCAN|SSUBSCRIBE|STRLEN|SUBSCRIBE|SUBSTR|SUNION|SUNIONCARD|SUNIONSTORE|SUNSUBSCRIBE|SWAPDB|SYNC|TIME|TOUCH|TRIMSLOTS|TTL|TYPE|UNLINK|UNSUBSCRIBE|UNWATCH|WAIT|WAITAOF|WATCH|XACK|XACKDEL|XADD|XAUTOCLAIM|XCFGSET|XCLAIM|XDEL|XDELEX|XGROUP\s+CREATE|XGROUP\s+CREATECONSUMER|XGROUP\s+DELCONSUMER|XGROUP\s+DESTROY|XGROUP\s+HELP|XGROUP\s+SETID|XGROUP|XIDMPRECORD|XINFO\s+CONSUMERS|XINFO\s+GROUPS|XINFO\s+HELP|XINFO\s+STREAM|XINFO|XLEN|XNACK|XPENDING|XRANGE|XREAD|XREADGROUP|XREVRANGE|XSETID|XTRIM|ZADD|ZCARD|ZCOUNT|ZDIFF|ZDIFFSTORE|ZINCRBY|ZINTER|ZINTERCARD|ZINTERSTORE|ZLEXCOUNT|ZMPOP|ZMSCORE|ZPOPMAX|ZPOPMIN|ZRANDMEMBER|ZRANGE|ZRANGEBYLEX|ZRANGEBYSCORE|ZRANGESTORE|ZRANK|ZREM|ZREMRANGEBYLEX|ZREMRANGEBYRANK|ZREMRANGEBYSCORE|ZREVRANGE|ZREVRANGEBYLEX|ZREVRANGEBYSCORE|ZREVRANK|ZSCAN|ZSCORE|ZUNION|ZUNIONSTORE)/,});
JS;
		}

		function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
			$next = $_GET["next"];
			$_GET["next"] = ""; // there is no following page unless SCAN returns a cursor
			if (preg_match('~^key =~', $where[0])) {
				$args = $this->whereKeys($where[0]);
			} elseif ($limit) {
				$args = array("SCAN", ($next != "" ? $next : 0), "COUNT", $limit);
				if ($where) {
					$args[] = "MATCH";
					$args[] = $this->whereKeys($where[0])[0];
				}
				$scan = $this->send($args, $print);
				if (!$scan) {
					return false;
				}
				list($cursor, $args) = $scan;
				$_GET["next"] = ($cursor != "0" ? $cursor : ""); // 0 - the iteration has finished
			} else {
				$args = $this->send(array("KEYS", ($where ? $this->whereKeys($where[0])[0] : "*")), $print);
				if ($args === false) {
					return false;
				}
			}
			$return = array();
			if ($args) {
				$columns = (in_array("*", $select) ? array('key', 'type', 'value') : $select);
				$types = array();
				if (in_array('type', $columns)) {
					$commands = array();
					foreach ($args as $key) {
						$commands[] = array("TYPE", $key);
					}
					$types = $this->conn->sendMulti($commands); // TYPE is not printed, it is an implementation detail of getting the types
					if (in_array(false, $types, true)) {
						return false;
					}
				}
				$values = array();
				if (in_array('value', $columns)) {
					$values = $this->conn->send(array_merge(array("MGET"), $args)); // MGET is not printed, it is an implementation detail of getting the values
					if ($values === false) {
						return false;
					}
				}
				foreach ($args as $i => $key) {
					$row = array('key' => $key, 'type' => $types[$i], 'value' => $values[$i]);
					$selected = array();
					foreach ($columns as $column) { // in this order, Select pairs the printed cells with the selected columns
						$selected[$column] = escape_value($row[$column]);
					}
					$return[] = $selected;
				}
			}
			return new Result($return);
		}

		/** Send a command and optionally print it
		* @param list<string|int> $args
		* @return mixed
		*/
		private function send($args, $print) {
			$start = microtime(true);
			$return = $this->conn->send($args);
			if ($print) {
				echo adminer()->selectQuery(format_command($args), $start, $return === false);
			}
			return $return;
		}

		function hasCStyleEscapes(): bool {
			return true;
		}

		function lineComment(): string {
			return '[^\s\S]'; // Redis has no comments
		}

		function allFields(): array {
			return array(); // the parent implementation would send a SQL query
		}

		function insert($table, $set) {
			return queries("SET " . implode(" ", $set)); // the values are quoted by quote()
		}

		function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
			$args = array();
			$where = $this->where($queryWhere);
			foreach ($where as $key) {
				$args[] = "$key " . $set["value"];
			}
			$this->conn->affected_rows = count($where);
			return !!queries("MSET " . implode(" ", $args)); // not the Result, Select would add its num_rows to the affected rows
		}

		function delete($table, $queryWhere, $limit = 0) {
			$result = queries("DEL " . implode(" ", $this->where($queryWhere)));
			if (!$result) {
				return false;
			}
			$this->conn->affected_rows = $result->fetch_row()[0]; // DEL returns the number of deleted keys
			return true;
		}

		/** Get the keys of a where condition as quoted arguments
		* @return list<string>
		*/
		private function where($queryWhere) {
			preg_match_all('~key . ("(?:\\\\.|[^\\\\"])*+")~', $queryWhere, $matches);
			return $matches[1];
		}

		/** Get the keys of a where condition
		* @return list<string>
		*/
		private function whereKeys($queryWhere) {
			return parse_command(implode(" ", $this->where($queryWhere)));
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
			"type" => array("field" => "type", "privileges" => array("select" => 1)), // the type is determined by the command creating the key
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

	// SELECT is a Redis command so this is called from SQL command
	function explain($connection, $query) {
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
		return preg_match('~^(cursor|single_table|sql)$~', $feature);
	}
}
