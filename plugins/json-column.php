<?php

/** Display JSON & SERIALIZED values as table in edit
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @author Martin Zeman (Zemistr), http://www.zemistr.eu/
* @author Andrea Mariani, https://www.fasys.it/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerJsonColumn {

    private function _isSerialized( $data, $strict = true ) {
        // if it isn't a string, it isn't serialized.
        if ( ! is_string( $data ) ) {
            return false;
        }
        $data = trim( $data );
        if ( 'N;' == $data ) {
            return true;
        }
        if ( strlen( $data ) < 4 ) {
            return false;
        }
        if ( ':' !== $data[1] ) {
            return false;
        }
        if ( $strict ) {
            $lastc = substr( $data, -1 );
            if ( ';' !== $lastc && '}' !== $lastc ) {
                return false;
            }
        } else {
            $semicolon = strpos( $data, ';' );
            $brace     = strpos( $data, '}' );
            // Either ; or } must exist.
            if ( false === $semicolon && false === $brace ) {
                return false;
            }
            // But neither must be in the first X characters.
            if ( false !== $semicolon && $semicolon < 3 ) {
                return false;
            }
            if ( false !== $brace && $brace < 4 ) {
                return false;
            }
        }
        $token = $data[0];
        switch ( $token ) {
            case 's':
                if ( $strict ) {
                    if ( '"' !== substr( $data, -2, 1 ) ) {
                        return false;
                    }
                } elseif ( false === strpos( $data, '"' ) ) {
                    return false;
                }
            // or else fall through
            case 'a':
            case 'O':
                return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
        }
        return false;
    }

	private function _testJson($value) {
		if ((substr($value, 0, 1) == '{' || substr($value, 0, 1) == '[') && ($json = json_decode($value, true))) {
			return $json;
		}
		elseif($this->_isSerialized($value)){
		    return unserialize($value);
        }
		return $value;
	}

	private function _buildTable($json) {
		echo '<table cellspacing="0" style="margin:2px; font-size:100%;">';
		foreach ($json as $key => $val) {
			echo '<tr>';
			echo '<th>' . h($key) . '</th>';
			echo '<td>';
			if (is_scalar($val) || $val === null) {
				if (is_bool($val)) {
					$val = $val ? 'true' : 'false';
				} elseif ($val === null) {
					$val = 'null';
				} elseif (!is_numeric($val)) {
					$val = '"' . h(addcslashes($val, "\r\n\"")) . '"';
				}
				echo '<code class="jush-js">' . $val . '</code>';
			} else {
				$this->_buildTable($val);
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	function editInput($table, $field, $attrs, $value) {
		$json = $this->_testJson($value);
		if ($json !== $value) {
			$this->_buildTable($json);
		}
	}
}
