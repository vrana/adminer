<?php

/** Cluster invalid login attempts by the last part of X-Forwarded-For (useful if Adminer runs behind a reverse proxy)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginReverseProxy extends Adminer\Plugin {

	function bruteForceKey() {
		// the address of the proxy tells the proxies apart and is used alone if the header is missing, e.g. when the request doesn't come through the proxy
		$forwarded_for = preg_replace('~.*,\s*~', '', strval($_SERVER["HTTP_X_FORWARDED_FOR"]));
		return $_SERVER["REMOTE_ADDR"] . ($forwarded_for != "" ? ", $forwarded_for" : "");
	}

	protected $translations = array(
		'cs' => array('' => 'Sdružuje neúspěšné pokusy o přihlášení podle poslední části X-Forwarded-For (užitečné, pokud Adminer běží za reverzní proxy)'),
		'de' => array('' => 'Fasst ungültige Anmeldeversuche nach dem letzten Teil von X-Forwarded-For zusammen (nützlich, wenn Adminer hinter einem Reverse Proxy läuft)'), // Claude Opus 5
		'pl' => array('' => 'Grupuje nieudane próby logowania według ostatniej części X-Forwarded-For (przydatne, gdy Adminer działa za odwrotnym proxy)'), // Claude Opus 5
		'ro' => array('' => 'Grupează încercările de autentificare eșuate după ultima parte din X-Forwarded-For (util dacă Adminer rulează în spatele unui reverse proxy)'), // Claude Opus 5
		'ja' => array('' => 'X-Forwarded-For の末尾部分で不正なログイン試行をまとめる (Adminer をリバースプロキシの背後で動かす場合に便利)'), // Claude Opus 5
		'sk' => array('' => 'Zoskupuje neúspešné pokusy o prihlásenie podľa poslednej časti X-Forwarded-For (užitočné, pokiaľ Adminer beží za reverznou proxy)'), // Claude Opus 5
		'zh' => array('' => '按 X-Forwarded-For 的最后一段归类无效的登录尝试（Adminer 运行在反向代理后面时有用）'),
	);
}
