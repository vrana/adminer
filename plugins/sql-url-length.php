<?php

/** Limit the length of URLs with the query from SQL command, some servers reject them
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlUrlLength extends Adminer\Plugin {
	protected $length;

	/**
	* @param int $length maximum length of the URL including the origin, 0 to never put the query to the URL
	*/
	function __construct($length = 1000) {
		$this->length = $length;
	}

	function sqlUrlLength() {
		return $this->length;
	}

	protected $translations = array(
		'cs' => array('' => 'Omezí délku URL s dotazem z SQL příkazu, některé servery je odmítají'),
		'de' => array('' => 'Begrenzt die Länge der URLs mit der Abfrage aus dem SQL-Befehl, manche Server lehnen sie ab'), // Claude Opus 5.5
		'hr' => array('' => 'Ograničava duljinu URL-ova s upitom iz SQL naredbe, neki ih poslužitelji odbijaju'), // Claude Opus 5.5
		'ja' => array('' => 'SQLコマンドのクエリを含むURLの長さを制限（一部のサーバーはこれを拒否するため）'), // Claude Opus 5.5
		'pl' => array('' => 'Ogranicza długość adresów URL z zapytaniem z polecenia SQL, niektóre serwery je odrzucają'), // Claude Opus 5.5
		'ro' => array('' => 'Limitează lungimea URL-urilor cu interogarea din comanda SQL, unele servere le resping'), // Claude Opus 5.5
		'sk' => array('' => 'Obmedzí dĺžku URL s dotazom z SQL príkazu, niektoré servery ich odmietajú'), // Claude Opus 5.5
		'zh' => array('' => '限制包含 SQL 命令查询的 URL 长度，某些服务器会拒绝此类 URL'), // Claude Opus 5.5
	);
}
