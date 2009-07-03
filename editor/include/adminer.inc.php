<?php
class AdminerBase {
	
	function name() {
		return lang('Editor');
	}
	
	function server() {
		return "";
	}
	
	function username() {
		return "";
	}
	
	function password() {
		return "";
	}
	
	function table_name($row) {
		return htmlspecialchars(strlen($row["Comment"]) ? $row["Comment"] : $row["Name"]);
	}
	
	function field_name($fields, $key) {
		return htmlspecialchars(strlen($fields[$key]["comment"]) ? $fields[$key]["comment"] : $key);
	}
	
	function navigation($missing) {
		global $SELF;
		if ($missing != "auth") {
			?>
<form action="" method="post">
<p>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>" />
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" />
</p>
</form>
<?php
			if ($missing != "db" && strlen($_GET["db"])) {
				$table_status = table_status();
				if (!$table_status) {
					echo "<p class='message'>" . lang('No tables.') . "</p>\n";
				} else {
					echo "<p>\n";
					foreach ($table_status as $row) {
						echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . $this->table_name($row) . "</a><br />\n";
					}
					echo "</p>\n";
				}
			}
		}
	}
}

$adminer = (class_exists("Adminer") ? new Adminer : new AdminerBase);
