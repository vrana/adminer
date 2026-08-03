<?php
namespace Adminer;

connection()->select_db(adminer()->database());
if (support("scheme")) {
	$schema = get_schema();
	if ($schema != "") {
		set_schema($schema);
	}
}
