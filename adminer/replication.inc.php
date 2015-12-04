<?php
page_header(lang('Replication'));

echo "<p><b>" . lang('Master status') . "</b>" . doc_link(array("sql" => "show-master-status.html")) . "\n";
$master_replication_status = replication_status("MASTER");
echo "<table cellspacing='0'>\n";
foreach ($master_replication_status[0] as $key => $val) {
	echo "<tr>";
	echo "<th>" . h($key);
	echo "<td>" . nbsp($val);
}
echo "</table>\n";

$slave_replication_status = replication_status("SLAVE");
if (!empty($slave_replication_status)) {
	echo "<p><b>" . lang('Slave status') . "</b>" . doc_link(array("sql" => "show-slave-status.html")) . "\n";
	foreach ($slave_replication_status as $slave) {
		echo "<table cellspacing='0'>\n";
		foreach ($slave as $key => $val) {
			echo "<tr>";
			echo "<th>" . h($key);
			echo "<td>" . nbsp($val);
		}
		echo "</table>\n";
	}
}
