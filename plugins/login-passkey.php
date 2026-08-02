<?php

/** Log in with a passkey holding the passwords
* The passwords are encrypted by a key derived from the passkey by the WebAuthn PRF extension so the key is never stored anywhere.
* Requires a secure context (HTTPS or localhost) and an authenticator supporting the PRF extension, otherwise the login form stays unchanged.
* Store an account with an empty password to log in to a server which doesn't require a password (e.g. SQLite) - the passkey verifies the user instead.
* The passkey is bound to the current hostname so it stops working after moving Adminer to a different domain.
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginPasskey extends Adminer\Plugin {
	protected $credential_id;
	protected $accounts;
	protected $password_hash;

	/** Set the passkey created by the setup form; call without parameters to create the first passkey
	* @param string $credential_id base64url encoded ID of the passkey
	* @param string $accounts base64 encoded encrypted list of accounts
	* @param string $password_hash SHA-256 of the password derived from the passkey, sent for accounts stored without a password
	*/
	function __construct($credential_id = '', $accounts = '', $password_hash = '') {
		$this->credential_id = $credential_id;
		$this->accounts = $accounts;
		$this->password_hash = $password_hash;
	}

	function credentials() {
		if ($this->passwordMatches(Adminer\get_password())) {
			// the server doesn't know the password derived from the passkey so don't send it
			return array(Adminer\SERVER, $_GET["username"], "");
		}
	}

	function login($login, $password) {
		if ($this->passwordMatches($password)) {
			return true; // the passkey has verified the user
		}
	}

	/** Check if the password is the one derived from the passkey
	* @param string|false|null $password
	* @return bool
	*/
	protected function passwordMatches($password) {
		return $this->password_hash != "" && is_string($password) && hash('sha256', $password) === $this->password_hash;
	}

	function loginFormField($name, $heading, $value) {
		if ($name == 'db') { // the last field, the row is printed right above the submit button
			ob_start();
			$this->printRow();
			return $heading . $value . "\n" . ob_get_clean();
		}
	}

	/** Print the login form row with the passkey buttons; it is hidden without JavaScript or without a support for passkeys */
	protected function printRow() {
		// 1 - JSON_HEX_TAG because the values are printed to a script element, 256 - JSON_UNESCAPED_UNICODE
		$lang = json_encode(array(
			'unsupported' => $this->lang('The passkey does not support storing passwords.'),
			'invalid' => $this->lang('The accounts cannot be decrypted by this passkey.'),
		), 1 | 256);
		?>
<tr id='passkey-row' hidden><th>Passkey<td>
<?php if ($this->accounts) { ?>
<input type='button' id='passkey-unlock' value='<?php echo $this->lang('Unlock with passkey'); ?>'>
<?php } ?>
<a href='#passkey-setup' id='passkey-setup-link'><?php echo $this->lang('Set up passkey'); ?></a>
<div id='passkey-message'></div>
<div id='passkey-setup' hidden>
<p><?php echo $this->lang('Fill in the login form and add it to the passkey.'); ?> <input id='passkey-label' size='10' placeholder='<?php echo $this->lang('Label'); ?>'>
<input type='button' id='passkey-add' value='<?php echo $this->lang('Add to passkey'); ?>'>
<p id='passkey-output' hidden><?php echo $this->lang('Add this line to %s:', '<a href="https://www.adminer.org/plugins/#use"' . Adminer\target_blank() . '>adminer-plugins.php</a>') . "\n"; ?>
<textarea id='passkey-config' rows='3' cols='40' readonly></textarea>
</div>
<script<?php echo Adminer\nonce(); ?>>
const passkeyId = <?php echo json_encode($this->credential_id, 1); ?>;
const passkeyAccounts = <?php echo json_encode($this->accounts, 1); ?>;
const passkeyLang = <?php echo $lang; ?>;
const passkeySalt = new TextEncoder().encode('adminer-login-passkey');
let passkeySetUp = null; // {auth, accounts} of the passkey used by the setup form

/** Convert bytes to base64
* @param {Uint8Array} bytes
* @return {string}
*/
function passkeyBase64(bytes) {
	return btoa(String.fromCharCode.apply(null, bytes));
}

/** Convert base64 or base64url to bytes
* @param {string} base64
* @return {Uint8Array}
*/
function passkeyBytes(base64) {
	const binary = atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
	const bytes = new Uint8Array(binary.length);
	for (let i = 0; i < binary.length; i++) {
		bytes[i] = binary.charCodeAt(i);
	}
	return bytes;
}

/** Convert the ID of a passkey to base64url
* @param {ArrayBuffer} rawId
* @return {string}
*/
function passkeyUrl(rawId) {
	return passkeyBase64(new Uint8Array(rawId)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/** Get random bytes
* @param {number} length
* @return {Uint8Array}
*/
function passkeyRandom(length) {
	return crypto.getRandomValues(new Uint8Array(length));
}

/** Convert bytes to a hexadecimal string
* @param {ArrayBuffer} buffer
* @return {string}
*/
function passkeyHex(buffer) {
	return Array.from(new Uint8Array(buffer)).map(byte => (byte < 16 ? '0' : '') + byte.toString(16)).join('');
}

/** Derive the encryption key and the password of servers without a password from the PRF output
* @param {ArrayBuffer} prf
* @param {ArrayBuffer} rawId
* @return {Promise} resolves to {key: CryptoKey, id: string, password: string}
*/
function passkeyDerive(prf, rawId) {
	const password = new Uint8Array(prf.byteLength + 1);
	password.set(new Uint8Array(prf));
	password[prf.byteLength] = 1; // distinguish the password from the encryption key which is the PRF output itself
	return Promise.all([
		crypto.subtle.importKey('raw', prf, {name: 'AES-GCM'}, false, ['encrypt', 'decrypt']),
		crypto.subtle.digest('SHA-256', password).then(passkeyHex),
	]).then(derived => ({key: derived[0], id: passkeyUrl(rawId), password: derived[1]}));
}

/** Get the encryption key from a passkey
* @param {string} id base64url encoded ID of the passkey, empty string to use any
* @return {Promise} resolves to {key: CryptoKey, id: string, password: string}
*/
function passkeyGet(id) {
	return navigator.credentials.get({publicKey: {
		challenge: passkeyRandom(32), // the assertion is not verified, the key is verified by decrypting the accounts
		allowCredentials: (id ? [{type: 'public-key', id: passkeyBytes(id)}] : []),
		userVerification: 'required', // the PRF extension requires it
		extensions: {prf: {eval: {first: passkeySalt}}},
	}}).then(credential => {
		const prf = credential.getClientExtensionResults().prf;
		if (!prf || !prf.results) {
			throw new Error(passkeyLang.unsupported);
		}
		return passkeyDerive(prf.results.first, credential.rawId);
	});
}

/** Create a new passkey
* @return {Promise} resolves to {key: CryptoKey, id: string, password: string}
*/
function passkeyCreate() {
	return navigator.credentials.create({publicKey: {
		challenge: passkeyRandom(32),
		rp: {name: 'Adminer'},
		user: {id: passkeyRandom(16), name: 'Adminer', displayName: 'Adminer'},
		pubKeyCredParams: [{type: 'public-key', alg: -7}, {type: 'public-key', alg: -257}],
		authenticatorSelection: {residentKey: 'preferred', userVerification: 'required'},
		extensions: {prf: {eval: {first: passkeySalt}}},
	}}).then(credential => {
		const prf = credential.getClientExtensionResults().prf;
		if (!prf || !(prf.enabled || prf.results)) {
			throw new Error(passkeyLang.unsupported); // fail now instead of creating a configuration which could never be decrypted
		}
		// older browsers evaluate the PRF only in navigator.credentials.get() which asks for the PIN again
		return (prf.results ? passkeyDerive(prf.results.first, credential.rawId) : passkeyGet(passkeyUrl(credential.rawId)));
	});
}

/** Decrypt the accounts
* @param {CryptoKey} key
* @param {string} accounts base64 encoded initialization vector followed by the ciphertext
* @return {Promise} resolves to an array of accounts
*/
function passkeyDecrypt(key, accounts) {
	const bytes = passkeyBytes(accounts);
	return crypto.subtle.decrypt({name: 'AES-GCM', iv: bytes.slice(0, 12)}, key, bytes.slice(12))
		.then(plain => JSON.parse(new TextDecoder().decode(plain)))
	;
}

/** Encrypt the accounts
* @param {CryptoKey} key
* @param {Array} accounts
* @return {Promise} resolves to a base64 encoded string
*/
function passkeyEncrypt(key, accounts) {
	const iv = passkeyRandom(12);
	return crypto.subtle.encrypt({name: 'AES-GCM', iv: iv}, key, new TextEncoder().encode(JSON.stringify(accounts)))
		.then(cipher => {
			const bytes = new Uint8Array(iv.length + cipher.byteLength);
			bytes.set(iv);
			bytes.set(new Uint8Array(cipher), iv.length);
			return passkeyBase64(bytes);
		})
	;
}

/** Display a message
* @param {string} text
* @param {boolean} [error]
*/
function passkeyMessage(text, error) {
	const div = qs('#passkey-message');
	div.textContent = text;
	div.className = (text ? (error ? 'error' : 'message') : '');
}

/** Display an error of the passkey operation
* @param {Error} error
*/
function passkeyFailure(error) {
	passkeyMessage(error.name == 'OperationError' ? passkeyLang.invalid : (error.message || error.name), true);
}

/** Fill the login form by an account and submit it
* @param {Object} account
* @param {string} password sent instead of an empty password of the account
*/
function passkeyLogin(account, password) {
	const form = qs('#username').form;
	for (const name of ['driver', 'server', 'username', 'password', 'db']) {
		const field = form['auth[' + name + ']'];
		if (field && account[name] !== undefined) {
			// the empty password is replaced because Adminer refuses to log in without a password
			field.value = (name == 'password' && account[name] === '' ? password : account[name]);
			if (name == 'driver') {
				fire(field, 'change');
			}
		}
	}
	qs('input[type=submit]', form).click();
}

/** Display buttons to select one of the accounts
* @param {Array} accounts
* @param {string} password
*/
function passkeyChoose(accounts, password) {
	passkeyMessage('');
	const div = qs('#passkey-message');
	for (const account of accounts) {
		const button = document.createElement('input');
		button.type = 'button';
		button.value = (account.label || account.username);
		button.addEventListener('click', () => passkeyLogin(account, password));
		div.appendChild(button);
		div.appendChild(document.createTextNode(' '));
	}
}

/** Get the account from the login form
* @param {string} label
* @return {Object}
*/
function passkeyAccount(label) {
	const form = qs('#username').form;
	const account = {label: label};
	for (const name of ['driver', 'server', 'username', 'password', 'db']) {
		const field = form['auth[' + name + ']'];
		if (field) {
			account[name] = field.value;
		}
	}
	return account;
}

/** Add the account from the login form to the passkey and print the configuration */
function passkeyAdd() {
	passkeyMessage('');
	const account = passkeyAccount(qs('#passkey-label').value);
	const passkey = (passkeySetUp
		? Promise.resolve(passkeySetUp) // the passkey is already unlocked so more accounts can be added without using it again
		: (passkeyAccounts
			? passkeyGet(passkeyId).then(auth => passkeyDecrypt(auth.key, passkeyAccounts).then(accounts => ({auth: auth, accounts: accounts})))
			: passkeyCreate().then(auth => ({auth: auth, accounts: []}))
		)
	);
	passkey.then(data => {
		passkeySetUp = data;
		data.accounts.push(account);
		return Promise.all([
			passkeyEncrypt(data.auth.key, data.accounts),
			crypto.subtle.digest('SHA-256', new TextEncoder().encode(data.auth.password)).then(passkeyHex),
		]).then(config => {
			qs('#passkey-config').value = "new AdminerLoginPasskey('" + data.auth.id + "', '" + config[0] + "', '" + config[1] + "'),";
			qs('#passkey-output').removeAttribute('hidden');
			qs('#passkey-label').value = '';
			// clear the password so that it is not stored again with an account of a server not requiring it
			qs('#username').form['auth[password]'].value = '';
			passkeyMessage(data.accounts.map(stored => stored.label || stored.username).join(', '));
		});
	}).catch(passkeyFailure);
}

if (window.PublicKeyCredential && window.isSecureContext && window.crypto && crypto.subtle) {
	qs('#passkey-row').removeAttribute('hidden');
	const unlock = qs('#passkey-unlock');
	if (unlock) {
		unlock.addEventListener('click', () => {
			passkeyMessage('');
			passkeyGet(passkeyId)
				.then(auth => passkeyDecrypt(auth.key, passkeyAccounts).then(accounts => (accounts.length == 1
					? passkeyLogin(accounts[0], auth.password)
					: passkeyChoose(accounts, auth.password)
				)))
				.catch(passkeyFailure)
			;
		});
	}
	qs('#passkey-setup-link').addEventListener('click', event => {
		event.preventDefault();
		qs('#passkey-setup').removeAttribute('hidden');
	});
	qs('#passkey-add').addEventListener('click', passkeyAdd);
}
</script>
<?php
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/login-passkey.png";
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Umožňuje přihlášení pomocí passkey, kde jsou uložená hesla',
			'Unlock with passkey' => 'Odemknout pomocí passkey',
			'Set up passkey' => 'Nastavit passkey',
			'Fill in the login form and add it to the passkey.' => 'Vyplňte přihlašovací formulář a přidejte ho do passkey.',
			'Label' => 'Popis',
			'Add to passkey' => 'Přidat do passkey',
			'Add this line to %s:' => 'Přidejte tento řádek do %s:',
			'The passkey does not support storing passwords.' => 'Tato passkey neumí ukládat hesla.',
			'The accounts cannot be decrypted by this passkey.' => 'Touto passkey se přístupy nepodařilo dešifrovat.',
		),
	);
}
