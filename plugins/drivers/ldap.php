<?php
/** Driver for LDAP directories
* - database = naming context, row = entry identified by dn, column = attribute
* - table = entry with children (rows are its children) or structural objectClass (rows are its entries in the whole naming context)
* - the entries with children are listed first, indented under their parents
* - the columns are the MUST attributes of the class plus the attributes populated in the first rows
* - multi-valued attributes are edited one value per line, binary attributes base64-encoded
* - changing dn renames the entry, also to another parent
* - uses the ldap extension, without it or with ext=socket a client speaking the protocol over a socket
* - ldaps:// connects by TLS, plugin login-ssl switches ldap:// to StartTLS and sets its certificates (only for StartTLS in the ldap extension)
* @link https://www.adminer.org/plugins/#use
*/

namespace Adminer;

add_driver("ldap", "LDAP");

if (isset($_GET["ldap"])) {
	define('Adminer\DRIVER', "ldap");

	if (extension_loaded("ldap") && $_GET["ext"] != "socket") {
		/** Client using the ldap extension, an entry is array("dn" => string, "attrs" => array($attribute => list of values)) */
		class LdapClient {
			public $extension = "LDAP";
			private $link;

			/** Connect and bind
			* @param string[]|null $ssl result of adminer()->connectSsl(), it also switches ldap:// to StartTLS
			* @return string error message
			*/
			function open(string $host, int $port, bool $secure, ?array $ssl, string $username, string $password): string {
				$this->link = ldap_connect(($secure ? "ldaps" : "ldap") . "://" . url_host($host) . ":$port");
				ldap_set_option($this->link, LDAP_OPT_PROTOCOL_VERSION, 3);
				ldap_set_option($this->link, LDAP_OPT_REFERRALS, 0);
				ldap_set_option($this->link, LDAP_OPT_NETWORK_TIMEOUT, 10);
				// libldap applies these only to StartTLS, ldaps:// uses its global configuration (ldap.conf, LDAPTLS_CACERT)
				foreach (array("ca" => LDAP_OPT_X_TLS_CACERTFILE, "cert" => LDAP_OPT_X_TLS_CERTFILE, "key" => LDAP_OPT_X_TLS_KEYFILE) as $key => $option) {
					if (idx($ssl, $key) != "") {
						ldap_set_option($this->link, $option, $ssl[$key]);
					}
				}
				if (isset($ssl["verify"]) && !$ssl["verify"]) {
					ldap_set_option($this->link, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
				}
				if (($ssl !== null && !$secure && !@ldap_start_tls($this->link)) || !@ldap_bind($this->link, $username, $password)) {
					return $this->error()[1];
				}
				return '';
			}

			/** Search, keeping the entries of a result which hit a limit
			* @param string $scope "base", "one" or "sub"
			* @param list<string> $attrs
			* @return list<array{dn: string, attrs: list<string>[]}>|false
			*/
			function search(string $base, string $scope, string $filter, array $attrs, int $sizeLimit) {
				$function = ($scope == "base" ? 'ldap_read' : ($scope == "one" ? 'ldap_list' : 'ldap_search'));
				$result = @$function($this->link, $base, $filter, $attrs, 0, $sizeLimit);
				if (!$result) {
					return false;
				}
				$return = array();
				for ($entry = ldap_first_entry($this->link, $result); $entry; $entry = ldap_next_entry($this->link, $entry)) {
					$attributes = array();
					$attr = ldap_first_attribute($this->link, $entry);
					while ($attr !== false) {
						$values = ldap_get_values_len($this->link, $entry, $attr);
						if ($values) {
							unset($values["count"]);
							$attributes[$attr] = $values;
						}
						$attr = ldap_next_attribute($this->link, $entry);
					}
					$return[] = array("dn" => (string) ldap_get_dn($this->link, $entry), "attrs" => $attributes);
				}
				return $return;
			}

			/** @param list<string>[] $attrs */
			function add(string $dn, array $attrs): bool {
				return @ldap_add($this->link, $dn, $attrs);
			}

			/** Replace the values of attributes
			* @param list<string>[] $changes an empty list removes the attribute
			*/
			function modify(string $dn, array $changes): bool {
				$batch = array();
				foreach ($changes as $attribute => $values) {
					$batch[] = ($values
						? array("attrib" => $attribute, "modtype" => LDAP_MODIFY_BATCH_REPLACE, "values" => $values)
						: array("attrib" => $attribute, "modtype" => LDAP_MODIFY_BATCH_REMOVE_ALL)
					);
				}
				return @ldap_modify_batch($this->link, $dn, $batch);
			}

			/** Change the first RDN of an entry and its parent */
			function rename(string $dn, string $rdn, string $parent): bool {
				return @ldap_rename($this->link, $dn, $rdn, $parent, true);
			}

			function delete(string $dn): bool {
				return @ldap_delete($this->link, $dn);
			}

			/** Get the last error
			* @return array{int, string} code and message
			*/
			function error(): array {
				$error = ldap_error($this->link);
				if (@ldap_get_option($this->link, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic) && $diagnostic != "" && $diagnostic != $error) {
					$error .= ": $diagnostic";
				}
				return array(ldap_errno($this->link), $error);
			}
		}

	} else {
		/** Client speaking the subset of LDAP (RFC 4511) needed by the driver over a socket */
		class LdapClient {
			public $extension = "socket";
			/** @var string[] */ static $messages = array(
				1 => "Operations error",
				2 => "Protocol error",
				3 => "Time limit exceeded",
				4 => "Size limit exceeded",
				7 => "Authentication method not supported",
				8 => "Strong(er) authentication required",
				11 => "Administrative limit exceeded",
				13 => "Confidentiality required",
				16 => "No such attribute",
				17 => "Undefined attribute type",
				19 => "Constraint violation",
				20 => "Type or value exists",
				21 => "Invalid syntax",
				32 => "No such object",
				34 => "Invalid DN syntax",
				48 => "Inappropriate authentication",
				49 => "Invalid credentials",
				50 => "Insufficient access",
				51 => "Server is busy",
				52 => "Server is unavailable",
				53 => "Server is unwilling to perform",
				64 => "Naming violation",
				65 => "Object class violation",
				66 => "Operation not allowed on non-leaf",
				67 => "Operation not allowed on RDN",
				68 => "Already exists",
				69 => "Cannot modify object class",
				80 => "Other (e.g., implementation specific) error",
			);
			private $socket;
			private $id = 0;
			private $error = array(0, "");

			function open(string $host, int $port, bool $secure, ?array $ssl, string $username, string $password): string {
				$options = array();
				foreach (array("ca" => "cafile", "cert" => "local_cert", "key" => "local_pk") as $key => $option) {
					if (idx($ssl, $key) != "") {
						$options[$option] = $ssl[$key];
					}
				}
				if (isset($ssl["verify"])) {
					$options["verify_peer"] = $options["verify_peer_name"] = $ssl["verify"];
				}
				$context = stream_context_create(array("ssl" => $options));
				$this->socket = @stream_socket_client("tcp://" . url_host($host) . ":$port", $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);
				if (!$this->socket) {
					return $error;
				}
				if ($ssl !== null && !$secure && $this->call(self::tlv(0x77, self::tlv(0x80, "1.3.6.1.4.1.1466.20037"))) === false) { // StartTLS
					return $this->error[1];
				}
				// the handshake is started here also for ldaps:// because a failure is described only by the warning
				if (($secure || $ssl !== null) && !@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					return (string) idx(error_get_last(), "message");
				}
				$bind = self::tlv(0x60, self::integer(3) . self::tlv(0x04, $username) . self::tlv(0x80, $password));
				return ($this->call($bind) === false ? $this->error[1] : '');
			}

			function search(string $base, string $scope, string $filter, array $attrs, int $sizeLimit) {
				try {
					$filter = self::filter($filter);
				} catch (\InvalidArgumentException $e) {
					$this->error = array(-7, "Bad search filter");
					return false;
				}
				$request = self::tlv(0x04, $base)
					. self::integer(($scope == "base" ? 0 : ($scope == "one" ? 1 : 2)), 0x0A)
					. self::integer(0, 0x0A) // never dereference aliases
					. self::integer($sizeLimit)
					. self::integer(0)
					. self::tlv(0x01, "\0")
					. $filter
					. self::tlv(0x30, self::strings($attrs))
				;
				$entries = $this->call(self::tlv(0x63, $request), array(0, 4, 10, 11)); // size and administrative limit and referral keep the entries like the extension
				if ($entries === false) {
					return false;
				}
				$return = array();
				foreach ($entries as $entry) {
					list($name, $list) = self::items($entry);
					$attributes = array();
					foreach (self::items($list[1]) as $attribute) {
						list($type, $values) = self::items($attribute[1]);
						foreach (self::items($values[1]) as $value) {
							$attributes[$type[1]][] = $value[1];
						}
					}
					$return[] = array("dn" => $name[1], "attrs" => $attributes);
				}
				return $return;
			}

			function add(string $dn, array $attrs): bool {
				$list = "";
				foreach ($attrs as $type => $values) {
					$list .= self::attribute($type, $values);
				}
				return $this->call(self::tlv(0x68, self::tlv(0x04, $dn) . self::tlv(0x30, $list))) !== false;
			}

			function modify(string $dn, array $changes): bool {
				$list = "";
				foreach ($changes as $type => $values) {
					$list .= self::tlv(0x30, self::integer(2, 0x0A) . self::attribute($type, $values)); // replace without values removes the attribute
				}
				return $this->call(self::tlv(0x66, self::tlv(0x04, $dn) . self::tlv(0x30, $list))) !== false;
			}

			function rename(string $dn, string $rdn, string $parent): bool {
				$request = self::tlv(0x04, $dn) . self::tlv(0x04, $rdn) . self::tlv(0x01, "\xFF") . ($parent != "" ? self::tlv(0x80, $parent) : "");
				return $this->call(self::tlv(0x6C, $request)) !== false;
			}

			function delete(string $dn): bool {
				return $this->call(self::tlv(0x4A, $dn)) !== false;
			}

			function error(): array {
				return $this->error;
			}

			/** Send an operation and read the responses up to its result
			* @param list<int> $success result codes treated as success
			* @return list<string>|false contents of the search entries received before the result
			*/
			private function call(string $operation, array $success = array(0)) {
				$message = self::tlv(0x30, self::integer(++$this->id) . $operation);
				$entries = array();
				try {
					if (@fwrite($this->socket, $message) !== strlen($message)) {
						throw new \RuntimeException;
					}
					do {
						list(, list($tag, $response)) = self::items($this->read());
						if ($tag == 0x64) {
							$entries[] = $response;
						}
					} while ($tag == 0x64 || $tag == 0x73); // search references are skipped the same as referrals
				} catch (\RuntimeException $e) {
					$this->error = array(-1, "Can't contact LDAP server");
					return false;
				}
				$result = self::items($response);
				$code = self::number($result[0][1]);
				$diagnostic = $result[2][1];
				$this->error = array($code, idx(self::$messages, $code, "LDAP error $code") . ($diagnostic != "" ? ": $diagnostic" : ""));
				return (in_array($code, $success) ? $entries : false);
			}

			/** Read one BER element from the socket and return its contents */
			private function read(): string {
				$length = ord($this->bytes(2)[1]);
				if ($length & 0x80) {
					$length = self::number($this->bytes($length & 0x7F));
				}
				return $this->bytes($length);
			}

			/** Read an exact number of bytes from the socket */
			private function bytes(int $length): string {
				$return = "";
				while (strlen($return) < $length) {
					$chunk = fread($this->socket, $length - strlen($return));
					if ($chunk === false || $chunk === "") {
						throw new \RuntimeException;
					}
					$return .= $chunk;
				}
				return $return;
			}

			/** Encode one BER element */
			private static function tlv(int $tag, string $value): string {
				$length = strlen($value);
				$long = ltrim(pack("N", $length), "\0");
				return chr($tag) . ($length < 0x80 ? chr($length) : chr(0x80 | strlen($long)) . $long) . $value;
			}

			/** Encode a non-negative INTEGER, or ENUMERATED with $tag 0x0A */
			private static function integer(int $value, int $tag = 0x02): string {
				$bytes = ltrim(pack("N", $value), "\0");
				return self::tlv($tag, ($bytes != "" && ord($bytes[0]) < 0x80 ? $bytes : "\0$bytes"));
			}

			/** Decode a non-negative big-endian number */
			private static function number(string $bytes): int {
				return (int) hexdec(bin2hex($bytes));
			}

			/** Encode OCTET STRINGs
			* @param list<string> $values
			*/
			private static function strings(array $values): string {
				$return = "";
				foreach ($values as $value) {
					$return .= self::tlv(0x04, $value);
				}
				return $return;
			}

			/** Encode an attribute with its values
			* @param list<string> $values
			*/
			private static function attribute(string $type, array $values): string {
				return self::tlv(0x30, self::tlv(0x04, $type) . self::tlv(0x31, self::strings($values)));
			}

			/** Decode the elements of a constructed BER value
			* @return list<array{int, string}> tags and contents
			*/
			private static function items(string $data): array {
				$return = array();
				for ($offset = 0; $offset < strlen($data); $offset += $length) {
					$tag = ord($data[$offset]);
					$length = ord($data[$offset + 1]);
					$offset += 2;
					if ($length & 0x80) {
						$bytes = $length & 0x7F;
						$length = self::number(substr($data, $offset, $bytes));
						$offset += $bytes;
					}
					$return[] = array($tag, substr($data, $offset, $length));
				}
				return $return;
			}

			/** Encode a filter in the string representation of RFC 4515, the extensible match is not supported */
			private static function filter(string $filter, int &$offset = 0): string {
				if (!preg_match('#\((?:([&|!])|([^()=<>~*\\\\]+)([<>~]?=)((?:[^()\\\\]|\\\\[0-9a-f]{2})*)\))#Ai', $filter, $match, 0, $offset)) {
					throw new \InvalidArgumentException;
				}
				$offset += strlen($match[0]);
				if ($match[1] != "") {
					$items = "";
					while (substr($filter, $offset, 1) == "(") {
						$items .= self::filter($filter, $offset);
					}
					if (substr($filter, $offset++, 1) != ")") {
						throw new \InvalidArgumentException;
					}
					return self::tlv(0xA0 + strpos("&|!", $match[1]), $items);
				}
				list(, , $attribute, $operator, $value) = $match;
				if ($operator == "=" && $value == "*") {
					return self::tlv(0x87, $attribute);
				}
				if ($operator == "=" && strpos($value, "*") !== false) {
					$parts = explode("*", $value);
					$substrings = "";
					foreach ($parts as $i => $part) {
						if ($part != "") {
							$substrings .= self::tlv(($i == 0 ? 0x80 : ($i == count($parts) - 1 ? 0x82 : 0x81)), self::unescape($part));
						}
					}
					return self::tlv(0xA4, self::tlv(0x04, $attribute) . self::tlv(0x30, $substrings));
				}
				$tag = idx(array("=" => 0xA3, ">=" => 0xA5, "<=" => 0xA6, "~=" => 0xA8), $operator);
				return self::tlv($tag, self::tlv(0x04, $attribute) . self::tlv(0x04, self::unescape($value)));
			}

			/** Decode the \XX escapes of a filter value */
			private static function unescape(string $value): string {
				return preg_replace_callback('~\\\\([0-9a-f]{2})~i', function (array $match): string {
					return chr(hexdec($match[1]));
				}, $value);
			}
		}
	}

	class Db extends SqlDb {
		const SCAN_LIMIT = 10000; // entries read to list the tables and to sort

		/** @var int[] */ static $binarySyntaxes = array(
			"1.3.6.1.4.1.1466.115.121.1.5" => 1, // Binary
			"1.3.6.1.4.1.1466.115.121.1.8" => 1, // Certificate
			"1.3.6.1.4.1.1466.115.121.1.9" => 1, // Certificate List
			"1.3.6.1.4.1.1466.115.121.1.10" => 1, // Certificate Pair
			"1.3.6.1.4.1.1466.115.121.1.28" => 1, // JPEG
			"1.3.6.1.4.1.1466.115.121.1.40" => 1, // Octet String
		);
		/** @var int[] */ static $binaryAttributes = array("objectguid" => 1, "objectsid" => 1, "usercertificate" => 1, "cacertificate" => 1); // Active Directory doesn't describe them as binary

		public $server_info = "LDAPv3";
		private $client;
		private $root = array();
		private $base = "";
		private $schema;
		private $scan;

		function attach(array $server, string $username, string $password): string {
			$secure = ($server["scheme"] == "ldaps");
			$this->client = new LdapClient;
			$this->extension = $this->client->extension;
			$error = $this->client->open($server["host"] ?: "localhost", intval($server["port"] ?: ($secure ? 636 : 389)), $secure, adminer()->connectSsl(), $username, $password);
			if ($error != "") {
				return $error;
			}
			$entries = $this->search("", "(objectClass=*)", array("namingContexts", "subschemaSubentry", "vendorName", "vendorVersion"), 0, "base");
			$this->root = ($entries ? $entries[0]["attrs"] : array());
			$vendor = trim(implode(" ", array_merge(self::values($this->root, "vendorName"), self::values($this->root, "vendorVersion"))));
			if ($vendor != "") {
				$this->server_info = $vendor;
			}
			return '';
		}

		function select_db(string $database): bool {
			$this->base = $database;
			return true;
		}

		function query(string $query, bool $unbuffered = false) {
			// only the row count in select is a query
			if (preg_match('~^\s*SELECT\s+COUNT\(\*\)\s+FROM\s+("(?:[^"]|"")+")(?:\s+WHERE\s+(.*))?$~is', $query, $match)) {
				$conditions = Driver::parseWhere(Driver::splitConditions(idx($match, 2, "")));
				if ($conditions["impossible"]) {
					return new Result(array(array(0)));
				}
				list($base, $scope, $filter) = $this->searchFor(idf_unescape($match[1]), $conditions);
				$entries = $this->search($base, $filter, array("1.1"), 0, $scope);
				return ($entries === false ? false : new Result(array(array(count($entries)))));
			}
			$this->error = "Only the queries generated by Adminer are supported";
			return false;
		}

		function quote(string $string): string {
			return "'" . str_replace("'", "''", $string) . "'";
		}

		function client(): LdapClient {
			return $this->client;
		}

		/** Remember the last error of the client */
		function lastError(): string {
			list($this->errno, $this->error) = $this->client->error();
			return $this->error;
		}

		/** Get the naming contexts of the server
		* @return list<string>
		*/
		function namingContexts(): array {
			$return = self::values($this->root, "namingContexts");
			sort($return);
			return $return;
		}

		/** Search and remember the error
		* @param ?list<string> $attrs null for all attributes
		* @param string $scope "base", "one" or "sub"
		* @return list<array{dn: string, attrs: list<string>[]}>|false
		*/
		function search(string $base, string $filter, ?array $attrs = null, int $sizeLimit = 0, string $scope = "sub") {
			$return = $this->client->search($base, $scope, $filter, ($attrs !== null ? $attrs : array("*")), $sizeLimit);
			if ($return === false) {
				$this->lastError();
			}
			return $return;
		}

		/** Read an entry
		* @return ?array{dn: string, attrs: list<string>[]}
		*/
		function readEntry(string $dn): ?array {
			$entries = $this->search($dn, "(objectClass=*)", null, 1, "base");
			return ($entries ? $entries[0] : null);
		}

		/** Tell a tree table named by the dn of an entry from a class table named by an objectClass */
		static function isTree(string $table): bool {
			return strpos($table, "=") !== false;
		}

		/** Get the search of a table
		* @param array{filters: list<string>, dn: string, impossible: bool} $conditions result of Driver::parseWhere()
		* @return array{string, string, string} base, scope and filter
		*/
		function searchFor(string $table, array $conditions): array {
			$filter = "(objectClass=" . (self::isTree($table) ? "*" : self::escapeFilter($table)) . ")";
			if ($conditions["filters"]) {
				$filter = "(&$filter" . implode("", $conditions["filters"]) . ")";
			}
			if ($conditions["dn"] != "") {
				return array($conditions["dn"], "base", $filter);
			}
			return (self::isTree($table) ? array($table, "one", $filter) : array($this->base, "sub", $filter));
		}

		/** Parse the subschema
		* @return array{classes: array<string, mixed[]>, attributes: array<string, mixed[]>} definitions by lower-cased names
		*/
		function schema(): array {
			if ($this->schema === null) {
				$this->schema = array("classes" => array(), "attributes" => array());
				$dn = first(self::values($this->root, "subschemaSubentry"));
				$attrs = array("objectClasses", "attributeTypes");
				// some servers return the subschema entry only for (objectClass=*)
				$entries = ($dn != "" ? $this->search($dn, "(objectClass=subschema)", $attrs, 0, "base") ?: $this->search($dn, "(objectClass=*)", $attrs, 0, "base") : array());
				foreach (array("classes" => "objectClasses", "attributes" => "attributeTypes") as $key => $attribute) {
					foreach (self::values($entries ? $entries[0]["attrs"] : array(), $attribute) as $definition) {
						$parsed = self::parseDefinition($definition);
						foreach ($parsed["names"] as $name) {
							$this->schema[$key][strtolower($name)] = $parsed;
						}
					}
				}
			}
			return $this->schema;
		}

		/** Get the values of an attribute regardless of the case used by the server
		* @param list<string>[] $attrs
		* @return list<string>
		*/
		static function values(array $attrs, string $attribute): array {
			foreach ($attrs as $key => $values) {
				if (strtolower($key) == strtolower($attribute)) {
					return $values;
				}
			}
			return array();
		}

		/** Parse an objectClasses or attributeTypes definition
		* @return array{names: list<string>, name: string, sup: list<string>, kind: string, must: list<string>, may: list<string>, syntax: string, single: bool, desc: string}
		*/
		static function parseDefinition(string $definition): array {
			$return = array("names" => array(), "sup" => array(), "kind" => "STRUCTURAL", "must" => array(), "may" => array(), "syntax" => "", "single" => false, "desc" => "");
			if (preg_match("~DESC\\s+'((?:[^']|'')*)'~", $definition, $match)) {
				$return["desc"] = str_replace("''", "'", $match[1]);
				$definition = str_replace($match[0], " ", $definition); // the description may contain any keyword
			}
			foreach (array("names" => "NAME", "sup" => "SUP", "must" => "MUST", "may" => "MAY") as $key => $keyword) {
				if (preg_match("~\\b$keyword\\s+(\\([^)]*\\)|'[^']*'|[\\w.;-]+)~", $definition, $match)) {
					$return[$key] = preg_split('~[\s$()\']+~', $match[1], -1, PREG_SPLIT_NO_EMPTY);
				}
			}
			$return["name"] = (string) first($return["names"]);
			if (preg_match('~\b(STRUCTURAL|AUXILIARY|ABSTRACT)\b~', $definition, $match)) {
				$return["kind"] = $match[1];
			}
			if (preg_match("~\\bSYNTAX\\s+'?([\\d.]+)~", $definition, $match)) {
				$return["syntax"] = $match[1];
			}
			$return["single"] = (bool) preg_match('~\bSINGLE-VALUE\b~', $definition);
			return $return;
		}

		/** Get the definition of an attribute without options such as ;binary or ;lang-cs
		* @return ?mixed[]
		*/
		function attributeType(string $attribute): ?array {
			return idx($this->schema()["attributes"], strtolower(preg_replace('~;.*~', '', $attribute)));
		}

		/** Check whether the values of an attribute are not printable */
		function isBinary(string $attribute): bool {
			$definition = $this->attributeType($attribute);
			return preg_match('~;binary(;|$)~i', $attribute)
				|| isset(self::$binaryAttributes[strtolower(preg_replace('~;.*~', '', $attribute))])
				|| ($definition && isset(self::$binarySyntaxes[$definition["syntax"]]))
			;
		}

		/** Get the most specific structural class of an entry
		* @param list<string> $classes values of objectClass
		*/
		function structuralClass(array $classes): string {
			$candidates = array();
			foreach ($classes as $class) {
				$definition = idx($this->schema()["classes"], strtolower($class));
				if ($definition ? $definition["kind"] == "STRUCTURAL" : strtolower($class) != "top") {
					$candidates[strtolower($class)] = ($definition ? $definition["name"] : $class);
				}
			}
			foreach ($candidates as $class) {
				foreach ($this->superClasses($class) as $parent) {
					if (strtolower($parent) != strtolower($class)) {
						unset($candidates[strtolower($parent)]);
					}
				}
			}
			return (string) reset($candidates);
		}

		/** Get the superclasses of an objectClass
		* @return list<string>
		*/
		function superClasses(string $class): array {
			$return = array();
			$queue = array($class);
			$seen = array(strtolower($class) => 1);
			while ($queue) {
				$definition = idx($this->schema()["classes"], strtolower(array_shift($queue)));
				foreach ($definition ? $definition["sup"] : array() as $parent) {
					if (!isset($seen[strtolower($parent)])) {
						$seen[strtolower($parent)] = 1;
						$return[] = $parent;
						$queue[] = $parent;
					}
				}
			}
			return $return;
		}

		/** Get the objectClass values of an entry of a class, the most specific first
		* @return list<string>
		*/
		function classChain(string $class): array {
			$definition = idx($this->schema()["classes"], strtolower($class));
			$name = ($definition ? $definition["name"] : $class);
			return array_merge(array($name), $this->superClasses($name));
		}

		/** Count the entries of the structural classes and the children of the entries
		* @return array{classes: int[], tree: int[]} the tree is ordered depth first
		*/
		function scan(): array {
			if ($this->scan === null) {
				$classes = array();
				$tree = array();
				if ($this->base != "") {
					$tree[$this->base] = 0;
					foreach ($this->search($this->base, "(objectClass=*)", array("objectClass"), self::SCAN_LIMIT) ?: array() as $entry) {
						$structural = $this->structuralClass(self::values($entry["attrs"], "objectClass"));
						if ($structural != "") {
							$classes[$structural] = idx($classes, $structural, 0) + 1;
						}
						if (strcasecmp($entry["dn"], $this->base)) {
							$parent = Driver::splitDn($entry["dn"])[1];
							$tree[$parent] = idx($tree, $parent, 0) + 1;
						}
					}
					// the limit can stop the search before reaching the ancestors of a read entry
					foreach (array_keys($tree) as $dn) {
						for (; $dn != "" && strcasecmp($dn, $this->base); $dn = Driver::splitDn($dn)[1]) {
							$tree += array($dn => 0);
						}
					}
					$paths = array();
					foreach ($tree as $dn => $count) {
						$paths[$dn] = implode("\0", array_reverse(Driver::rdns($dn)));
					}
					uksort($tree, function (string $a, string $b) use ($paths): int {
						return strnatcasecmp($paths[$a], $paths[$b]);
					});
					ksort($classes);
				}
				$this->scan = array("classes" => $classes, "tree" => $tree);
			}
			return $this->scan;
		}

		/** Get the status of a table
		* @return TableStatus
		*/
		function tableStatus(string $name, bool $fast): array {
			$tree = self::isTree($name);
			if ($fast) {
				// the menu prints the fast status, it shows an entry with children as its RDN indented under its parent
				$depth = ($tree ? count(Driver::rdns($name)) - count(Driver::rdns($this->base)) : 0);
				return array("Name" => ($depth > 0 ? str_repeat("\xC2\xA0\xC2\xA0", $depth) . first(Driver::rdns($name)) : $name));
			}
			$count = idx($this->scan()[$tree ? "tree" : "classes"], $name, 0);
			$definition = ($tree ? null : idx($this->schema()["classes"], strtolower($name)));
			return array("Name" => $name, "Engine" => "", "Rows" => $count, "Comment" => ($definition ? $definition["desc"] : ""));
		}

		/** Get the columns of a table
		* @return Field[]
		*/
		function fieldsFor(string $table): array {
			$schema = $this->schema();
			$tree = self::isTree($table);
			$must = array();
			$may = array();
			foreach ($tree ? array() : $this->classChain($table) as $class) {
				$definition = idx($schema["classes"], strtolower($class));
				if ($definition) {
					$must = array_merge($must, $definition["must"]);
					$may = array_merge($may, $definition["may"]);
				}
			}
			$must_lower = array_flip(array_map('strtolower', $must));
			// a class can allow dozens of attributes, show only those used in the first rows
			$populated = array();
			list($base, $scope, $filter) = $this->searchFor($table, array("filters" => array(), "dn" => ""));
			foreach ($this->search($base, $filter, null, 50, $scope) ?: array() as $entry) {
				foreach ($entry["attrs"] as $key => $values) {
					$populated[strtolower($key)] = $key;
				}
			}
			$names = array("dn" => "dn") + ($tree ? array("objectclass" => "objectClass") : array());
			foreach (array_merge($must, $may) as $attribute) {
				$lower = strtolower($attribute);
				if (!isset($names[$lower]) && (isset($must_lower[$lower]) || isset($populated[$lower]))) {
					$names[$lower] = $attribute;
				}
			}
			$names += $populated; // attributes not announced by the classes, e.g. operational
			if (count($names) == 1 && !idx($schema["classes"], strtolower($table))) {
				return array();
			}
			$return = array();
			foreach ($names as $lower => $name) {
				$attribute = $this->attributeType($name);
				$binary = $this->isBinary($name);
				$type = ($name == "dn" || (!$binary && $attribute && $attribute["single"]) ? "string" : "text");
				$return[$name] = array(
					"field" => $name,
					"type" => $type,
					"full_type" => $type,
					"null" => ($name != "dn" && !isset($must_lower[$lower])),
					"auto_increment" => false,
					"privileges" => array("insert" => 1, "select" => 1, "update" => 1, "where" => 1, "order" => 1),
					"comment" => trim(($attribute ? $attribute["desc"] : "") . ($binary ? " (base64)" : "")),
				);
			}
			return $return;
		}

		/** Turn an entry into a row, multiple values are separated by newlines
		* @param array{dn: string, attrs: list<string>[]} $entry
		* @return string[]
		*/
		function entryToRow(array $entry): array {
			$return = array("dn" => $entry["dn"]);
			foreach ($entry["attrs"] as $attribute => $values) {
				$return[$attribute] = implode("\n", ($this->isBinary($attribute) ? array_map('base64_encode', $values) : $values));
			}
			return $return;
		}

		/** Escape a value in a filter (RFC 4515) */
		static function escapeFilter(string $value): string {
			return strtr($value, array("\\" => "\\5c", "*" => "\\2a", "(" => "\\28", ")" => "\\29", "\0" => "\\00"));
		}
	}

	class Result {
		public $num_rows;
		private $rows = array();
		private $columns;
		private $offset = 0;

		/**
		* @param list<mixed[]> $rows
		* @param ?list<string> $columns null to use the keys of the rows
		*/
		function __construct(array $rows, ?array $columns = null) {
			if ($columns === null) {
				$columns = array();
				foreach ($rows as $row) {
					$columns += $row;
				}
				$columns = array_keys($columns);
			}
			$this->columns = $columns;
			// every row has every column, Adminer also reads them by position
			foreach ($rows as $row) {
				$lower = array_change_key_case($row);
				$complete = array();
				foreach ($columns as $column) {
					$complete[$column] = (array_key_exists($column, $row) ? $row[$column] : idx($lower, strtolower($column))); // the server can use another case
				}
				$this->rows[] = $complete;
			}
			$this->num_rows = count($this->rows);
		}

		function fetch_assoc() {
			$row = current($this->rows);
			next($this->rows);
			return $row;
		}

		function fetch_row() {
			$row = $this->fetch_assoc();
			return ($row ? array_values($row) : false);
		}

		function fetch_field(): \stdClass {
			return (object) array("name" => $this->columns[$this->offset++]);
		}
	}

	class Driver extends SqlDriver {
		static $jush = "ldap";
		static $serverSchemes = array("ldap", "ldaps");
		static $serverPorts = array(389, 636);

		public $primary = "dn";

		function operators(?array $tableStatus): array {
			return array("=", "!=", ">=", "<=", "LIKE %%", "NOT LIKE %%", "IS NULL", "IS NOT NULL");
		}

		function allFields(): array {
			return array(); // the parent implementation would send a SQL query
		}

		function select(string $table, array $select, array $where, array $group, array $order = array(), int $limit = 1, ?int $page = 0, bool $print = false) {
			$conditions = self::parseWhere($where);
			if ($conditions["impossible"]) {
				return new Result(array());
			}
			list($base, $scope, $filter) = $this->conn->searchFor($table, $conditions);
			// LDAP has no offset so the previous pages are read and skipped
			$offset = ($page ? $limit * $page : 0);
			$size_limit = ($order ? Db::SCAN_LIMIT : ($limit ? $offset + $limit : 0)); // sorting only the first rows would pick wrong rows
			$this->query = "base: " . ($base != "" ? $base : "(root)") . "\nscope: $scope\nfilter: $filter";
			$start = microtime(true);
			$entries = $this->conn->search($base, $filter, self::parseSelect($select), $size_limit, $scope);
			if ($print) {
				echo adminer()->selectQuery($this->query, $start, $entries === false);
			}
			if ($entries === false) {
				return false;
			}
			$rows = self::sortRows(array_map(array($this->conn, 'entryToRow'), $entries), $order);
			if ($limit) {
				$rows = array_slice($rows, $offset, $limit);
			}
			return new Result($rows, self::resultColumns($select, $rows));
		}

		function insert(string $table, array $set) {
			$values = self::parseSet($set);
			$dn = trim((string) idx($values, "dn", ""));
			unset($values["dn"]);
			if ($dn == "") {
				$this->conn->error = "The dn column is required to insert an entry.";
				return false;
			}
			$attributes = array();
			foreach ($values as $attribute => $value) {
				$split = $this->splitValues($attribute, $value);
				if ($split) {
					$attributes[$attribute] = $split;
				}
			}
			if (!Db::values($attributes, "objectClass") && !Db::isTree($table)) {
				$attributes["objectClass"] = $this->conn->classChain($table);
			}
			if (!$this->conn->client()->add($dn, $attributes)) {
				$this->conn->lastError();
				return false;
			}
			$this->conn->affected_rows = 1;
			return true;
		}

		function update(string $table, array $set, string $queryWhere, int $limit = 0, string $separator = "\n") {
			$dns = self::parseDns($queryWhere);
			if (count($dns) != 1) {
				$this->conn->error = "Unable to determine which entry to update.";
				return false;
			}
			$dn = $dns[0];
			$entry = $this->conn->readEntry($dn);
			if (!$entry) {
				$this->conn->error = "The entry $dn no longer exists.";
				return false;
			}
			$existing = array_change_key_case($entry["attrs"]);
			$values = self::parseSet($set);
			$new_dn = trim((string) idx($values, "dn", $dn));
			unset($values["dn"]);
			$changes = array();
			foreach ($values as $attribute => $value) {
				$new = $this->splitValues($attribute, $value);
				if ($new !== idx($existing, strtolower($attribute), array())) {
					$changes[$attribute] = $new; // the unchanged attributes are not sent, replacing objectClass would be rejected
				}
			}
			if ($changes && !$this->conn->client()->modify($dn, $changes)) {
				$this->conn->lastError();
				return false;
			}
			if ($new_dn != "" && $new_dn !== $dn) {
				list($rdn, $parent) = self::splitDn($new_dn);
				if (!$this->conn->client()->rename($dn, $rdn, $parent)) {
					$this->conn->lastError();
					return false;
				}
			}
			$this->conn->affected_rows = 1;
			return true;
		}

		function delete(string $table, string $queryWhere, int $limit = 0) {
			$dns = self::parseDns($queryWhere);
			if (!$dns) {
				$this->conn->error = "Unable to determine which entry to delete.";
				return false;
			}
			$this->conn->affected_rows = 0;
			foreach ($dns as $dn) {
				if (!$this->conn->client()->delete($dn)) {
					$this->conn->lastError();
					return false;
				}
				$this->conn->affected_rows++;
			}
			return true;
		}

		function begin() {
			return true; // LDAP has no transactions
		}

		function commit() {
			return true;
		}

		function rollback() {
			return true;
		}

		/** Split a dn to RDNs, keep their escapes
		* @return list<string>
		*/
		static function rdns(string $dn): array {
			preg_match_all('~(?:[^\\\\,]|\\\\.)+~s', $dn, $matches);
			return array_map('ltrim', $matches[0]);
		}

		/** Split a dn to the first RDN and the parent
		* @return array{string, string}
		*/
		static function splitDn(string $dn): array {
			$rdns = self::rdns($dn);
			return array((string) array_shift($rdns), implode(",", $rdns));
		}

		/** Get the dn values of a generated WHERE clause
		* @return list<string>
		*/
		static function parseDns(string $queryWhere): array {
			preg_match_all('~"dn"\s*=\s*\'((?:[^\']|\'\')*)\'~i', $queryWhere, $matches);
			return array_values(array_unique(str_replace("''", "'", $matches[1])));
		}

		/** Split a WHERE clause to the operands of the top-level AND
		* @return list<string>
		*/
		static function splitConditions(string $where): array {
			$where = trim(preg_replace('~^\s*WHERE\s+~i', "", trim($where)));
			$return = array();
			$current = "";
			$depth = 0;
			$quoted = false;
			$length = strlen($where);
			for ($i = 0; $i < $length; $i++) {
				$char = $where[$i];
				if ($quoted) {
					$current .= $char;
					if ($char == "'") {
						if (substr($where, $i + 1, 1) == "'") {
							$current .= $where[++$i]; // '' is an escaped quote
						} else {
							$quoted = false;
						}
					}
					continue;
				}
				if ($char == "'") {
					$quoted = true;
				} elseif ($char == "(") {
					$depth++;
				} elseif ($char == ")") {
					$depth--;
				} elseif (!$depth && strtoupper(substr($where, $i, 5)) == " AND ") {
					$return[] = trim($current);
					$current = "";
					$i += 4;
					continue;
				}
				$current .= $char;
			}
			if (trim($current) != "") {
				$return[] = trim($current);
			}
			return $return;
		}

		/** Unescape a generated SET clause
		* @param string[] $set escaped columns in keys, quoted data in values
		* @return array<string, ?string>
		*/
		static function parseSet(array $set): array {
			$return = array();
			foreach ($set as $key => $val) {
				if ($val === "NULL") {
					$val = null;
				} elseif (preg_match("~^'((?:[^']|'')*)'$~s", $val, $match)) {
					$val = str_replace("''", "'", $match[1]);
				}
				$return[idf_unescape($key)] = $val;
			}
			return $return;
		}

		/** Split an edited value to the values of an attribute
		* @return list<string>
		*/
		private function splitValues(string $attribute, ?string $value): array {
			$binary = $this->conn->isBinary($attribute);
			$return = array();
			foreach (preg_split('~\r\n|\r|\n~', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $val) {
				$return[] = ($binary ? (string) base64_decode($val, true) : $val);
			}
			return $return;
		}

		/** Translate the conditions of Adminer to filters
		* @param list<string> $where
		* @return array{filters: list<string>, dn: string, impossible: bool} dn is lifted from the conditions to the search base
		*/
		static function parseWhere(array $where): array {
			$return = array("filters" => array(), "dn" => "", "impossible" => false);
			foreach ($where as $condition) {
				$condition = trim($condition);
				if ($condition === "1 = 0") {
					$return["impossible"] = true;
					continue;
				}
				$parts = array($condition);
				// searching anywhere produces ("a" = 'x' OR "b" = 'x')
				if (preg_match('~^\((.*)\)$~s', $condition, $match) && preg_match('~\sOR\s~i', $match[1])) {
					$parts = preg_split('~\s+OR\s+~i', $match[1]);
				}
				$filters = array();
				foreach ($parts as $part) {
					$filter = self::conditionToFilter($part, $return);
					if ($filter != "") {
						$filters[] = $filter;
					}
				}
				if ($filters) {
					$return["filters"][] = (count($filters) == 1 ? $filters[0] : "(|" . implode("", $filters) . ")");
				}
			}
			return $return;
		}

		/** Translate a comparison to a filter, dn = is lifted to the search base
		* @param array{filters: list<string>, dn: string, impossible: bool} $state
		*/
		static function conditionToFilter(string $condition, array &$state): string {
			$condition = trim($condition);
			if (preg_match('~^("(?:[^"]|"")+")\s+IS\s+(NOT\s+)?NULL$~i', $condition, $match)) {
				$attribute = Db::escapeFilter(idf_unescape($match[1]));
				return ($match[2] ? "($attribute=*)" : "(!($attribute=*))");
			}
			if (!preg_match('~^("(?:[^"]|"")+")\s*(=|!=|>=|<=|NOT\s+LIKE|LIKE)\s*\'((?:[^\']|\'\')*)\'$~is', $condition, $match)) {
				return "";
			}
			$attribute = idf_unescape($match[1]);
			$operator = strtoupper(preg_replace('~\s+~', " ", $match[2]));
			$value = str_replace("''", "'", $match[3]);
			if (strtolower($attribute) == "dn") {
				if ($operator == "=") {
					$state["dn"] = $value;
				}
				return "";
			}
			$like = preg_match('~LIKE~', $operator);
			$value = ($like ? implode("*", array_map('Adminer\Db::escapeFilter', explode("%", $value))) : Db::escapeFilter($value));
			$filter = "(" . Db::escapeFilter($attribute) . ($like || $operator == "!=" ? "=" : $operator) . "$value)";
			return (preg_match('~^(!=|NOT)~', $operator) ? "(!$filter)" : $filter);
		}

		/** Get the attributes to read
		* @param list<string> $select
		* @return ?list<string> null for all
		*/
		static function parseSelect(array $select): ?array {
			$return = array();
			foreach ($select as $val) {
				if ($val == "*" || !preg_match('~^"(?:[^"]|"")+"$~', $val)) {
					return null;
				}
				$name = idf_unescape($val);
				if (strtolower($name) != "dn") {
					$return[] = $name;
				}
			}
			return ($return ?: array("1.1")); // 1.1 - no attributes, only dn
		}

		/** Get the columns in the order of select, dn first otherwise
		* @param list<string> $select
		* @param list<string[]> $rows
		* @return list<string>
		*/
		static function resultColumns(array $select, array $rows): array {
			$return = array();
			foreach ($select as $val) {
				if ($val == "*" || !preg_match('~^"(?:[^"]|"")+"$~', $val)) {
					$return = array();
					break;
				}
				$return[] = idf_unescape($val);
			}
			if (!$return) {
				$return = array("dn" => 1);
				foreach ($rows as $row) {
					$return += $row;
				}
				$return = array_keys($return);
			}
			return $return;
		}

		/** Sort the rows, a server doesn't have to support the sorting control
		* @param list<string[]> $rows
		* @param list<string> $order
		* @return list<string[]>
		*/
		static function sortRows(array $rows, array $order): array {
			foreach (array_reverse($order) as $val) {
				if (preg_match('~^("(?:[^"]|"")+")(\s+DESC)?$~i', trim($val), $match)) {
					$column = idf_unescape($match[1]);
					$desc = ($match[2] != "");
					usort($rows, function (array $a, array $b) use ($column, $desc): int {
						$return = strnatcasecmp((string) idx($a, $column, ""), (string) idx($b, $column, ""));
						return ($desc ? -$return : $return);
					});
				}
			}
			return $rows;
		}
	}

	function logged_user(): string {
		return $_GET["username"];
	}

	function get_databases(bool $flush): array {
		return connection()->namingContexts();
	}

	function collations(): array {
		return array();
	}

	function db_collation(string $db, array $collations) {
	}

	function information_schema(string $db): bool {
		return false;
	}

	function indexes(string $table, ?Db $connection2 = null): array {
		return array(array("type" => "PRIMARY", "columns" => array("dn")));
	}

	function fields(string $table): array {
		return fields_from_edit() ?: connection()->fieldsFor($table);
	}

	function convert_field(array $field) {
	}

	function unconvert_field(array $field, string $return): string {
		return $return;
	}

	function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " "): string {
		return " $query$where";
	}

	function limit1(string $table, string $query, string $where, string $separator = "\n"): string {
		return limit($query, $where, 1, 0, $separator);
	}

	function idf_escape(string $idf): string {
		return '"' . str_replace('"', '""', $idf) . '"';
	}

	function table(string $idf): string {
		return idf_escape($idf);
	}

	function foreign_keys(string $table): array {
		return array();
	}

	function tables_list(): array {
		$scan = connection()->scan();
		return array_fill_keys(array_merge(array_keys($scan["tree"]), array_keys($scan["classes"])), "table");
	}

	function table_status(string $name = "", bool $fast = false): array {
		$return = array();
		foreach (($name != "" ? array($name => 1) : tables_list()) as $table => $type) {
			$return[$table] = connection()->tableStatus($table, $fast);
		}
		return $return;
	}

	function count_tables(array $databases): array {
		return array();
	}

	function error(): string {
		return h(connection()->error);
	}

	function is_view(array $table_status): bool {
		return false;
	}

	function found_rows(array $table_status, array $where) {
		return null; // Db::query() counts them
	}

	function fk_support(array $table_status): bool {
		return false;
	}

	function last_id($result): string {
		return '';
	}

	function support(string $feature): bool {
		return $feature == "fast_status";
	}
}
