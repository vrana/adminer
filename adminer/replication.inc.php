<?php
page_header(lang('Replication'));

echo "<h3>" . lang('Master status') . doc_link(array("sql" => "show-master-status.html")) . "</h3>\n";
$master_replication_status = replication_status("MASTER");
if (!$master_replication_status) {
	echo "<p class='message'>" . lang('No rows.') . "\n";
} else {
	echo "<table cellspacing='0'>\n";
	foreach ($master_replication_status[0] as $key => $val) {
		echo "<tr>";
		echo "<th>" . h($key);
		echo "<td>" . nbsp($val);
	}
	echo "</table>\n";
}

$slave_replication_status = replication_status("SLAVE");
if ($slave_replication_status) {
	echo "<h3>" . lang('Slave status') . doc_link(array("sql" => "show-slave-status.html")) . "</h3>\n";
	foreach ($slave_replication_status[0] as $slave) {
		echo "<table cellspacing='0'>\n";
		foreach ($slave as $key => $val) {
			echo "<tr>";
			echo "<th>" . h($key);
			echo "<td>" . nbsp($val);
		}
		echo "</table>\n";
	}
}
