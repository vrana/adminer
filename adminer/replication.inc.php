<?php
page_header(lang('Replication'));

echo "<p><b>Master status</b>\n";
$masterReplicationStatus = replication_status("MASTER");
echo "<table cellspacing='0'>\n";
foreach ($masterReplicationStatus[0] as $key => $val) {
    echo "<tr>";
    echo "<th><code>" . $key . "</code>";
    echo "<td>" . nbsp($val);
}
echo "</table>\n";

$slaveReplicationStatus = replication_status("SLAVE");
if (!empty($slaveReplicationStatus)) {
    echo "<p><b>Slave status</b>\n";
    echo "<table cellspacing='0'>\n";
    foreach ($slaveReplicationStatus[0] as $key => $val) {
        echo "<tr>";
        echo "<th><code class='jush-" . $jush . "status'>" . h($key) . "</code>";
        echo "<td>" . nbsp($val);
    }
    echo "</table>\n";
}
