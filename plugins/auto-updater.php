<?php

/**Adminer Auto Updater
 *
 * You should have write permission on adminer.php
 *
 * @link https://www.adminer.org/plugins/#use
 * @author Sadetdin EYILI, https://github.com/sad270/
 * @license https://opensource.org/licenses/mit-license.php MIT
*/
class AdminerAutoUpdater {
    /** @var string */
    const COOKIE_NAME = 'AdminerAutoUpdaterLastCheck';

    /** @var string */
    const FILE_NAME = 'adminer.php';

    /** @var string */
    const VERSION_CHECK_URL = 'https://www.adminer.org/version/?current=';

    /** @var string */
    const ADMINER_DOWNLOAD_URL = 'https://www.adminer.org/latest.php';

    /** @var bool */
    private $isUpdated = false;

    /** @var bool */
    private $isUpdateChecked = false;

    /**
     * AutoUpdater constructor.
     *
     * @param null|string $adminerDownloadUrl your download url must be like https://www.adminer.org/latest[-mysql][-en].php
     * @param null|integer $updateTime in seconds
     */
    function __construct($adminerDownloadUrl = null, $updateTime = null) {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            // if COOKIE is set, version already checked
            return;
        }
        $this->isUpdateChecked = true;

        //update the Cookie
        $updateTime = $updateTime ? $updateTime : 3600*24; //default 1day
        setcookie(self::COOKIE_NAME, true, time()+$updateTime);

        $doc = new DomDocument();
        if (!$doc->loadHtml(file_get_contents(self::VERSION_CHECK_URL . version()))) {
            return;
        }

        $versionTag = $doc->getElementsByTagName('a');
        if (!count($versionTag) > 0) {
            return;
        }

        $lastVersion = $versionTag->item(0)->textContent;
        if (!$lastVersion || $lastVersion == version()) {
            return;
        }

        $this->isUpdated = true;

        $adminerDownloadUrl = $adminerDownloadUrl ? $adminerDownloadUrl : self::ADMINER_DOWNLOAD_URL;
        $curl = curl_init($adminerDownloadUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $data = curl_exec ($curl);
        curl_close ($curl);
        $h = fopen(self::FILE_NAME, "w+");
        fputs($h, $data);
    }

    /**
     * @param $missing
     */
    function navigation($missing) {
        if ($this->isUpdated) {
            echo '<h2>Adminer is updated</h2>';
        } elseif ($this->isUpdateChecked) {
            echo '<h2>Update checked</h2>';
        }
    }
}
