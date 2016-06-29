<?php

/** Use <input type="checkbox"> for bool
 * @link https://www.adminer.org/plugins/#use
 * @author Adam Kuśmierz, http://kusmierz.be/
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerEditBoolean {

    function editInput($table, $field, $attrs, $value) {
        if (preg_match('~bool~', $field["type"])) {
            return "<input type='hidden' name='fields[$field[field]]' value='0'>" .
                "<input type='checkbox'" . (in_array(strtolower($value), array('1', 't', 'true', 'y', 'yes', 'on')) ? " checked='checked'" : "") . " value='1' name='fields[$field[field]]' $attrs>";
        }
    }

}
