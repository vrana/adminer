<?php

/** Display constant list of servers in login form
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginServers {
	/** @access protected */
	var $servers, $driver;
	
	/** Set supported servers
	* @param array array($domain) or array($domain => $description) or array($category => array())
	* @param string
	*/
	function AdminerLoginServers($servers, $driver = "server") {
		$this->servers = $servers;
		$this->driver = $driver;
	}
	
	function login($login, $password) {
		// check if server is allowed
		foreach ($this->servers as $key => $val) {
			$servers = $val;
			if (!is_array($val)) {
				$servers = array($key => $val);
			}
			foreach ($servers as $k => $v) {
				if ((is_string($k) ? $k : $v) == SERVER) {
					return;
				}
			}
		}
		return false;
	}
	
	function loginForm() {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Server'); ?><td><input type="hidden" name="auth[driver]" value="<?php echo $this->driver; ?>"><select name="auth[server]"><?php echo optionlist($this->servers, SERVER); ?></select>
<tr><th><?php echo lang('Username'); ?><td><input id="username" name="auth[username]" value="<?php echo h($_GET["username"]);  ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="auth[password]">
</table>
<p><input type="submit" value="<?php echo lang('Login'); ?>">
<?php
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
		return true;
	}
	
}
