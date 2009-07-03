<?php
class AdminerBase {
	
	function name() {
		return lang('Adminer');
	}
	
	function server() {
		return $_GET["server"];
	}
	
	function username() {
		return $_SESSION["usernames"][$_GET["server"]];
	}
	
	function password() {
		return $_SESSION["passwords"][$_GET["server"]];
	}
	
	function table_name($row) {
		return htmlspecialchars($row["Name"]);
	}
	
	function field_name($fields, $key) {
		return htmlspecialchars($key);
	}
	
	function navigation($missing) {
		global $SELF;
		if ($missing != "auth") {
			$databases = get_databases();
			?>
<form action="" method="post">
<p>
<a href="<?php echo htmlspecialchars($SELF); ?>sql="><?php echo lang('SQL command'); ?></a>
<a href="<?php echo htmlspecialchars($SELF); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo lang('Dump'); ?></a>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>" />
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" />
</p>
</form>
<form action="">
<p><?php if (strlen($_GET["server"])) { ?><input type="hidden" name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" /><?php } ?>
<?php if ($databases) { ?>
<select name="db" onchange="this.form.submit();"><option value="">(<?php echo lang('database'); ?>)</option><?php echo optionlist($databases, $_GET["db"]); ?></select>
<?php } else { ?>
<input name="db" value="<?php echo htmlspecialchars($_GET["db"]); ?>" />
<?php } ?>
<?php if (isset($_GET["sql"])) { ?><input type="hidden" name="sql" value="" /><?php } ?>
<?php if (isset($_GET["schema"])) { ?><input type="hidden" name="schema" value="" /><?php } ?>
<?php if (isset($_GET["dump"])) { ?><input type="hidden" name="dump" value="" /><?php } ?>
<input type="submit" value="<?php echo lang('Use'); ?>"<?php echo ($databases ? " class='hidden'" : ""); ?> />
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
						echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . lang('select') . '</a> ';
						echo '<a href="' . htmlspecialchars($SELF) . (isset($row["Rows"]) ? 'table' : 'view') . '=' . urlencode($row["Name"]) . '">' . $this->table_name($row) . "</a><br />\n";
					}
					echo "</p>\n";
				}
				echo '<p><a href="' . htmlspecialchars($SELF) . 'create=">' . lang('Create new table') . "</a></p>\n";
			}
		}
	}
}

$adminer = (class_exists("Adminer") ? new Adminer : new AdminerBase);
