<?php

class ProxysqlSaveLoad {

	function __construct() {
		if ($_POST["saveload"]) {
			$this->postitem = $_POST["item"];
			$this->posttarget = $_POST["target"];
			$this->postaction = $_POST["saveload"];
			unset($_POST);
			unset($_SERVER["REQUEST_METHOD"]);
		}	
	}

	function dumpData($table, $style, $query) {
		$args = func_get_args();
		$args[2] = str_replace("FROM ", " FROM " . idf_escape(currentDB()) . "." , $query);
		$adminerPlugin = new AdminerPlugin;
		$adminerPlugin->_callParent(__FUNCTION__, $args);	
		return true;
	}

	function homepage() {
		if ($this->postitem && $this->posttarget && $this->postaction){
			$query = $this->postaction . " " . $this->postitem . " TO " . $this->posttarget;
			if(!query_redirect($query, $_SERVER['REQUEST_URI'], $this->postaction . " OK")){
				echo "<div class=error>ERROR $query</div>";
			}
		}

		$adminer = adminer();
		if (count($adminer->databases()) == 5 && $adminer->database() == "main") {
			echo "<form action='#' method='post'>\n";
			echo "<p>ProxySQL: ";
			echo (html_select("item", ["MYSQL SERVERS", "MYSQL VARIABLES", "MYSQL QUERY RULES", "MYSQL USERS"]));
			echo " TO ";
			echo (html_select("target", ["RUNTIME", "MEMORY", "DISK"]));
			echo " <input type='submit' name='saveload' value='SAVE'>";
			echo " <input type='submit' name='saveload' value='LOAD'>";
			echo "</form>\n";
		}
	}	
}
