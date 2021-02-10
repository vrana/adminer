<?php

/** Require One-Time Password at login
* @link https://www.adminer.org/plugins/otp/
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginOtp {
	/** @access protected */
	var $secret;
	
	/** 
	* @param string decoded secret, e.g. base32_decode("SECRET")
	*/
	function __construct($secret) {
		$this->secret = $secret;
		if ($_POST["auth"]) {
			$_SESSION["otp"] = (string) $_POST["auth"]["otp"];
		}
	}
	
	function loginFormField($name, $heading, $value) {
		if ($name == 'password') {
			return $heading . $value
				. "<tr><th><acronym title='One Time Password' lang='en'>OTP</acronym>"
				. "<td><input type='number' name='auth[otp]' value='" . h($_SESSION["otp"]) . "' size='6' autocomplete='off'>\n"
			;
		}
	}

	function login($login, $password) {
		if (isset($_SESSION["otp"])) {
			$timeSlot = floor(time() / 30);
			foreach (array(0, -1, 1) as $skew) {
				if ($_SESSION["otp"] == $this->getOtp($timeSlot + $skew)) {
					restart_session();
					unset($_SESSION["otp"]);
					stop_session();
					return;
				}
			}
			return 'Invalid OTP.';
		}
	}
	
	function getOtp($timeSlot) {
		$data = str_pad(pack('N', $timeSlot), 8, "\0", STR_PAD_LEFT);
		$hash = hash_hmac('sha1', $data, $this->secret, true);
		$offset = ord(substr($hash, -1)) & 0xF;
		$unpacked = unpack('N', substr($hash, $offset, 4));
		return ($unpacked[1] & 0x7FFFFFFF) % 1e6;
	}
}
