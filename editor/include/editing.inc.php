<?php
function email_header($header) {
	// iconv_mime_encode requires PHP 5, imap_8bit requires IMAP extension
	return "=?UTF-8?B?" . base64_encode($header) . "?="; //! split long lines
}
