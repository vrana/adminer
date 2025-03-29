<?php
namespace Adminer;

/** Encode e-mail header in UTF-8 */
function email_header(string $header): string {
	// iconv_mime_encode requires iconv, imap_8bit requires IMAP extension
	return "=?UTF-8?B?" . base64_encode($header) . "?="; //! split long lines
}

/** Send e-mail in UTF-8
* @param array{error?:list<int>, type?:list<string>, name?:list<string>, tmp_name?:list<string>} $files
*/
function send_mail(string $email, string $subject, string $message, string $from = "", array $files = array()): bool {
	$eol = PHP_EOL;
	$message = str_replace("\n", $eol, wordwrap(str_replace("\r", "", "$message\n")));
	$boundary = uniqid("boundary");
	$attachments = "";
	foreach ((array) $files["error"] as $key => $val) {
		if (!$val) {
			$attachments .= "--$boundary$eol"
				. "Content-Type: " . str_replace("\n", "", $files["type"][$key]) . $eol
				. "Content-Disposition: attachment; filename=\"" . preg_replace('~["\n]~', '', $files["name"][$key]) . "\"$eol"
				. "Content-Transfer-Encoding: base64$eol$eol"
				. chunk_split(base64_encode(file_get_contents($files["tmp_name"][$key])), 76, $eol) . $eol
			;
		}
	}
	$beginning = "";
	$headers = "Content-Type: text/plain; charset=utf-8$eol" . "Content-Transfer-Encoding: 8bit";
	if ($attachments) {
		$attachments .= "--$boundary--$eol";
		$beginning = "--$boundary$eol$headers$eol$eol";
		$headers = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
	}
	$headers .= $eol . "MIME-Version: 1.0$eol" . "X-Mailer: Adminer Editor"
		. ($from ? $eol . "From: " . str_replace("\n", "", $from) : "") //! should escape display name
	;
	return mail($email, email_header($subject), $beginning . $message . $attachments, $headers);
}

/** Check whether the column looks like boolean
* @param Field $field single field returned from fields()
*/
function like_bool(array $field): bool {
	return preg_match("~bool|(tinyint|bit)\\(1\\)~", $field["full_type"]);
}
