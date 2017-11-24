<?php
/** Hash fields ending with an algo name suffix
 *
 * Hash fields ending with "_md5", "_sha1", "sha512_224", "tiger128_3" etc. with the hash() function.
 * Hash fields ending "_crypt" with the crypt() function (for PHP < 5.5).
 * Hash fields ending with "_hash" with the password_hash() function (recommended for PHP 5.5 and above).
 *
 * Enter "<password>[::<salt>]" when editing a column to be hashed with crypt(), ex. "some text::some salt".
 * The salt is optional although strongly recommended.
 * The double colon is used as separator.
 * The salt must comply with the algo to crypt with as per the documentation, see AdminerHashColumn::setCryptHashPattern() for examples.
 * Note that the use of a "_crypt" column is not trivial. It is recommended to use a "_hash" column with PHP 5.5 or above.
 *
* @link https://www.adminer.org/plugins/#use
* @author Michel Corne
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerHashColumn {
    const CRYPT_VALUE_SEPARATOR = '::';

    protected $cryptHashPattern;
    protected $hashAlgoPattern;
    protected $hashAlgos;

    public function __construct() {
        $this->cryptHashPattern = $this->setCryptHashPattern();
        $this->hashAlgos        = $this->getHashAlgos();
        $this->hashAlgoPattern  = $this->setHashAlgoPattern($this->hashAlgos);
    }

    /**
     *
     * @param string $value
     * @return string
     * @throws Exception
     * @see http://php.net/manual/en/function.crypt.php
     */
    protected function crypt($value) {
        $parts = explode(AdminerHashColumn::CRYPT_VALUE_SEPARATOR, $value);
        $value = current($parts);

        if ($salt = next($parts)) {
            $hash = crypt($value, $salt);
        } else {
            $hash = crypt($value);
        }

        if (strlen($hash) < 13) {
            throw new Exception('Failed to crypt()');
        }

        return $hash;
    }

    /**
     *
     * @param string $suffix
     * @return string
     * @throws Exception
     */
    protected function getHashAlgo($suffix) {
        if (! isset($this->hashAlgos[$suffix])) {
            throw new Exception("Hash algo $suffix not available");
        }

        return $this->hashAlgos[$suffix];
    }

    /**
     *
     * @return array
     */
    protected function getHashAlgos() {
        $algos = array();

        foreach (hash_algos() as $algo) {
            // ex: "sha512/224" => "sha512_224", "tiger128,3" => "tiger128_3"
            $suffix = preg_replace('~\W~', '_', $algo);
            $algos[$suffix] = $algo;
        }

        return $algos;
    }

    /**
     *
     * @param string $algo
     * @param string $value
     * @return boolean
     */
    protected function isHashed($algo, $value) {
        if (! ctype_xdigit($value)) {
            // there are non hex digits, this is not a hash value
            return false;
        }

        $hash = hash($algo, 'test');

        if (strlen($value) != strlen($hash)) {
            // the value length is different from the expected hash value length, this is not a hash value
            return false;
        }

        // note that the likelihood that someone enters a value (typically a password) that is actualy a hash is unlikely
        return true;
    }

    /**
     *
     * @param string $value
     * @return string
     * @throws Exception
     * @see http://php.net/manual/en/function.password-hash.php
     */
    protected function passwordHash($value) {
        if (! function_exists('password_hash')) {
            throw new Exception('Function password_hash() not available');
        }

        if (! $hash = password_hash($value, PASSWORD_DEFAULT)) {
            throw new Exception('Failed to password_hash()');
        }

        return $hash;
    }

    /**
     *
     * @param string $field
     * @param string $value
     * @param string $function
     * @return boolean
     */
    public function processInput($field, $value, $function = null) {
        try {
            if (preg_match('~_crypt$~', $field['field'], $match)) {
                if (! preg_match($this->cryptHashPattern, $value)) {
                    $value = $this->crypt($value);
                }
            } elseif (preg_match('~_hash$~', $field['field'], $match)) {
                if (! preg_match($this->cryptHashPattern, $value)) {
                    $value = $this->passwordHash($value);
                }
            } elseif (preg_match($this->hashAlgoPattern, $field['field'], $match)) {
                $algo = $this->getHashAlgo($match[1]);
                if (! $this->isHashed($algo, $value)) {
                    $value = hash($algo, $value);
                }
            }
            return q($value);
        } catch (Exception $ex) {
            connection()->error = $ex->getMessage();
            return false;
        }
    }

    /**
     *
     * return array
     * @see http://php.net/manual/en/function.crypt.php
     * @see https://github.com/psypanda/hashID/blob/master/hashid.py
     */
    protected function setCryptHashPattern() {
        $patterns = array(
            '[./0-9A-Za-z]{13}' ,                                      // CRYPT_STD_DES : "password" + "sa"                            = "sa3tHJ3/KuYvI"
            '_[./0-9A-Za-z]{19}',                                      // CRYPT_EXT_DES : "password" + "_J9..salt"                     = "_J9..saltJW8FtKdEkNM"
            '\$1\$[a-z0-9/.]{0,8}\$[a-z0-9/.]{22}',                    // CRYPT_MD5     : "password" + "$1$salt$"                          = "$1$salt$qJH7.N4xYta3aEG/dfqo/0"
            '(\$2[axy]|\$2)\$[0-9]{2}\$[a-z0-9/.]{53}',                // CRYPT_BLOWFISH: "password" + "$2a$07$saltsaltsaltsaltsaltsa" = "$2a$07$saltsaltsaltsaltsaltsOErRGviAtNkkt0q8Okzbc2v/cXmXtSdm"
            '\$5\$(rounds=[0-9]+\$)?[a-z0-9/.]{0,16}\$[a-z0-9/.]{43}', // CRYPT_SHA256  : "password" + "$5$rounds=5000$salt$"          = "$5$rounds=5000$salt$EOglxGOcgUCAOExL0wFpSldEfLSy3SZ7FSZ.bSm4M51"
            '\$6\$(rounds=[0-9]+\$)?[a-z0-9/.]{0,16}\$[a-z0-9/.]{86}', // CRYPT_SHA512  : "password" + "$6$rounds=5000$salt$"          = "$6$rounds=5000$salt$IxDD3jeSOb5eB1CX5LBsqZFVkJdido3OUILO5Ifz5iwMuTS4XMS130MTSuDDl3aCI6WouIL9AjRbLCelDCy.g."
        );

        $pattern  = implode('|', $patterns);
        $pattern  = "~^($pattern)$~i";

        return $pattern;
    }

    /**
     *
     * @param array $algos
     * @return array
     */
    protected function setHashAlgoPattern($algos) {
        $suffixes = array_keys($algos);
        $pattern  = implode('|', $suffixes);
        $pattern  = "~_($pattern)$~";

        return $pattern;
    }
}
