<?php

/** Use filter in tables list
 * @author  Jakub Vrana, http://www.vrana.cz/
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerTablesFilter
{

   function tablesPrint($tables)
   {
      ?>
      <script type="text/javascript">
         if (window.addEventListener && sessionStorage) {
            window.addEventListener("DOMContentLoaded", function () {
               if (sessionStorage.getItem('AdminerTablesFilter')) {
                  document.getElementById('tablesFilter').value = sessionStorage.getItem('AdminerTablesFilter');
                  tablesFilter(sessionStorage.getItem('AdminerTablesFilter'));
               }
            }, false);
         }

         function tablesFilter(value) {
            var tables = document.getElementById('tables').getElementsByTagName('span');
            for (var i = tables.length; i--;) {
               var a = tables[i].children[1];
               var text = a.innerText || a.textContent;
               tables[i].className = (text.indexOf(value) == -1 ? 'hide' : '');
               a.innerHTML = text.replace(value, '<b>' + value + '</b>');
            }
            sessionStorage.setItem('AdminerTablesFilter', value);
         }
      </script>
      <p class="jsonly"><input type="text" onkeyup="tablesFilter(this.value);" id="tablesFilter"></p>
      <?php
      echo "<p id='tables'>\n";
      foreach ($tables as $table => $type) {
         echo '<span><a href="'.h(ME).'select='.urlencode($table).'"'.bold($_GET["select"] == $table).">"."</a> ";
         echo '<a href="'.h(ME).'table='.urlencode($table).'"'.bold($_GET["table"] == $table).">".h($table)."</a><br></span>\n";
      }
      return true;
   }
}
