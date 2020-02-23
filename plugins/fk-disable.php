<?php

/**
 * Allow Disable foreign keys
 * @author Andrea Mariani, fasys.it
 */
class AdminerFkDisable
{
    private function deleteAllBetween($beginning, $end, $string) {
        $beginningPos = strpos($string, $beginning);
        $endPos = strpos($string, $end);
        if ($beginningPos === false || $endPos === false) {
            return $string;
        }

        $textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);
        return $this->deleteAllBetween($beginning, $end, str_replace($textToDelete, '', $string)); // recursion to ensure all occurrences are replaced
    }

    public function head(){
        $sql = filter_input(INPUT_GET, 'sql');
        if (!isset($sql)) {
            return;
        }

        $query = trim(filter_input(INPUT_POST, 'query'));

        if(filter_input(INPUT_POST, 'fk_disable')){
            if($query) {
                $query = trim($this->deleteAllBetween("-- FK:D0", "-- FK:D1", $query));

                $_POST['query'] = "-- FK:D0\nSET FOREIGN_KEY_CHECKS=0;\n-- FK:D1\n\n{$query}\n\n-- FK:D0\n;SET FOREIGN_KEY_CHECKS=1;\n-- FK:D1";
            }
            $fk_disable_checked = ($_POST['fk_disable']) ? 'checked="checked"' : "";
        }

        ?>

        <script<?php echo nonce();?> type="text/javascript">

            function domReady(fn) {
                document.addEventListener("DOMContentLoaded", fn);
                if (document.readyState === "interactive" || document.readyState === "complete" ) {
                    fn();
                }
            }

            domReady(() => {
                document.querySelectorAll('#form p')[1].insertAdjacentHTML('beforeend', '<label><input type="checkbox" name="fk_disable" value="1" <?= $fk_disable_checked ?> /><?= lang('Disable Foreign Keys') ?></label>')
            })

        </script>
        <?php
    }
}
