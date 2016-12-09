<?php

/** Detect transaction in SQL and setup "Stop on error" checkbox
* @link https://www.adminer.org/plugins/#use
* @author SailorMax, http://www.sailormax.net/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlCommandTransaction
{
	function head()
	{
?>
		<script>
		document.addEventListener("DOMContentLoaded", function(event)
		{
			var query_els = document.getElementsByName("query");
			if (!query_els.length)
				return;

			var error_stop_els = document.getElementsByName("error_stops");
			if (!error_stop_els.length)
				return;

			var error_stop = error_stop_els[0];
			error_stop.addEventListener("change", function()
			{
				this["myManualChanged"] = true;
			});

			var reStartTransaction = /^\s*START\s+TRANSACTION\s*;/i;
			var funcSetupStopOnErrorCheckbox = function()
			{
				if ((error_stop["myManualChanged"] == true) || error_stop.checked)
					return;

				if (reStartTransaction.test(this.value))
				{
					error_stop.checked = true;
					error_stop["myManualChanged"] = true;
				}
			};

			query_els[0].addEventListener("input", funcSetupStopOnErrorCheckbox);
			query_els[0].addEventListener("keyup", funcSetupStopOnErrorCheckbox);
		});
		</script>
<?php
	}
}