<?php

/** Require One-Time Password at login
 * @link https://www.adminer.org/plugins/otp/
 * @author Jakub Vrana, https://www.vrana.cz/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerLoginOtp
{
	/** @var string */
	private $secret;

	/**
	 * @param string $secret Decoded secret, e.g. base64_decode("ENCODED_SECRET").
	 */
	public function __construct($secret) {
		$this->secret = $secret;

		if (isset($_POST["auth"])) {
			$_SESSION["otp"] = (string)$_POST["auth"]["otp"];
		}
	}

	/**
	 * @param string $name
	 * @param string $heading
	 * @param string $value
	 *
	 * @return string|null
	 */
	public function loginFormField($name, $heading, $value) {
		if ($name != "password") return null;

		return $heading . $value .
			"<tr><th><abbr title='" . lang('One Time Password') . "' lang='en'>OTP</abbr></th>" .
			"<td><input type='text' name='auth[otp]' value='" . h($_SESSION["otp"]) . "' " .
			"size='6' autocomplete='one-time-code' inputmode='numeric' maxlength='6' pattern='\d{6}'/></td>" .
			"</tr>\n";
	}

	/**
	 * @param string $login
	 * @param string $password
	 *
	 * @return string|null
	 */
	public function login($login, $password) {
		if (!isset($_SESSION["otp"])) return null;

		$timeSlot = floor(time() / 30);

		foreach (array(0, -1, 1) as $skew) {
			if ($_SESSION["otp"] == $this->getOtp($timeSlot + $skew)) {
				restart_session();
				unset($_SESSION["otp"]);
				stop_session();

				return null;
			}
		}

		return lang('Invalid OTP code.');
	}

	/**
	 * @param int $timeSlot
	 *
	 * @return int
	 */
	private function getOtp($timeSlot) {
		$data = str_pad(pack("N", $timeSlot), 8, "\0", STR_PAD_LEFT);
		$hash = hash_hmac("sha1", $data, $this->secret, true);
		$offset = ord(substr($hash, -1)) & 0xF;
		$unpacked = unpack("N", substr($hash, $offset, 4));

		return ($unpacked[1] & 0x7FFFFFFF) % 1e6;
	}
}
