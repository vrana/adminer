<?php

/** Disable tables
 * @link https://www.adminer.org/plugins/#use
 * @author Andrea Mariani, https://www.fasys.it/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerDisableTables {

    function tableName($tableStatus) {
        // tables without comments would return empty string and will be ignored by Adminer
        $disabledTables = [
            'tableName1' => true,
            'tableName2' => true,
            'tableName3' => true,
            //...
        ];

        $select = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_STRING);
        if(isset($select) && $disabledTables[$select]) die(Adminer\h('Access Denied.'));

        if($disabledTables[$tableStatus['Name']]){
            return false;
        }

        return Adminer\h($tableStatus['Name']);
        // tables without comments would return empty string and will be ignored by Adminer
        //return Adminer\h($tableStatus['Comment']);
    }

}
