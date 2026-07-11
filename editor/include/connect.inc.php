<?php
namespace Adminer;

connection()->select_db(adminer()->database());
if (support("scheme")) {
	set_schema(get_schema());
}
