<?php

/** Allow sending e-mails to addresses in table
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSelectEmail extends Adminer\Plugin {

	function selectEmailPrint($emailFields, $columns) {
		if ($emailFields) {
			Adminer\print_fieldset("email", $this->lang('E-mail'), $_POST["email_append"]);
			echo "<div" . Adminer\on('keydown', 'submitKeydown', 'email') . ">\n";
			echo Adminer\script("function emailFileChange() { const el = this.cloneNode(true); this.onchange = null; el.onchange = emailFileChange; el.value = ''; this.parentNode.appendChild(el); }");
			echo "<p>" . $this->lang('From') . ": <input name='email_from' value='" . Adminer\h($_POST ? $_POST["email_from"] : $_COOKIE["adminer_email"]) . "'>\n";
			echo $this->lang('Subject') . ": <input name='email_subject' value='" . Adminer\h($_POST["email_subject"]) . "'>\n";
			echo "<p><textarea name='email_message' rows='15' cols='75'>" . Adminer\h($_POST["email_message"] . ($_POST["email_append"] ? '{$' . "$_POST[email_addition]}" : "")) . "</textarea>\n";
			echo "<p" . Adminer\on('keydown', 'submitKeydown', 'email_append') . ">" . Adminer\html_select("email_addition", $columns, $_POST["email_addition"])
				. " <input type='submit' name='email_append' value='" . $this->lang('Insert') . "'>\n"; //! JavaScript
			echo "<p>" . $this->lang('Attachments') . ": <input type='file' name='email_files[]'>" . Adminer\script("qsl('input').onchange = emailFileChange;");
			echo "<p>" . (count($emailFields) == 1 ? Adminer\input_hidden("email_field", key($emailFields)) : Adminer\html_select("email_field", $emailFields));
			echo "<input type='submit' name='email' value='" . $this->lang('Send') . "'" . Adminer\confirm() . ">";
			echo "</div>\n";
			echo "</div></fieldset>\n";
			return true;
		}
	}

	function selectEmailProcess($where, $foreignKeys) {
		if ($_POST["email_append"]) {
			return true;
		}
		if ($_POST["email"]) {
			$sent = 0;
			if ($_POST["all"] || $_POST["check"]) {
				$field = Adminer\idf_escape($_POST["email_field"]);
				$subject = $_POST["email_subject"];
				$message = $_POST["email_message"];
				preg_match_all('~\{\$([a-z0-9_]+)\}~i', "$subject.$message", $matches); // allows {$name} in subject or message
				$rows = Adminer\get_rows(
					"SELECT DISTINCT $field" . ($matches[1] ? ", " . implode(", ", array_map('Adminer\idf_escape', array_unique($matches[1]))) : "") . " FROM " . Adminer\table($_GET["select"])
					. " WHERE $field IS NOT NULL AND $field != ''"
					. ($where ? " AND " . implode(" AND ", $where) : "")
					. ($_POST["all"] ? "" : " AND ((" . implode(") OR (", array_map('Adminer\where_check', (array) $_POST["check"])) . "))")
				);
				$fields = Adminer\fields($_GET["select"]);
				foreach (Adminer\adminer()->rowDescriptions($rows, $foreignKeys) as $row) {
					$replace = array('{\\' => '{'); // allow literal {$name}
					foreach ($matches[1] as $val) {
						$replace['{$' . "$val}"] = Adminer\adminer()->editVal($row[$val], $fields[$val]);
					}
					$email = $row[$_POST["email_field"]];
					if (Adminer\is_mail($email) && $this->sendMail($email, strtr($subject, $replace), strtr($message, $replace), $_POST["email_from"], $_FILES["email_files"])) {
						$sent++;
					}
				}
			}
			Adminer\cookie("adminer_email", $_POST["email_from"]);
			Adminer\redirect(Adminer\remove_from_uri(), $this->lang('%d e-mail(s) have been sent.', $sent));
		}
		return false;
	}

	/** Encode e-mail header in UTF-8 */
	private function emailHeader($header) {
		// iconv_mime_encode requires iconv, imap_8bit requires IMAP extension
		return "=?UTF-8?B?" . base64_encode($header) . "?="; //! split long lines
	}

	/** Send e-mail in UTF-8
	* @param array{error?:list<int>, type?:list<string>, name?:list<string>, tmp_name?:list<string>} $files
	*/
	private function sendMail($email, $subject, $message, $from = "", array $files = array()) {
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
		return mail($email, $this->emailHeader($subject), $beginning . $message . $attachments, $headers);
	}

	protected $translations = array(
		'en' => array(
			'%d e-mail(s) have been sent.' => array('%d e-mail has been sent.', '%d e-mails have been sent.'),
		),
		'ar' => array(
			'E-mail' => 'البريد الإلكتروني',
			'From' => 'من',
			'Subject' => 'الموضوع',
			'Send' => 'إرسال',
			'%d e-mail(s) have been sent.' => 'تم إرسال %d رسالة.',
			'Attachments' => 'ملفات مرفقة',
			'Insert' => 'إنشاء',
		),
		'bg' => array(
			'E-mail' => 'E-mail',
			'From' => 'От',
			'Subject' => 'Тема',
			'Attachments' => 'Прикачени',
			'Send' => 'Изпращане',
			'%d e-mail(s) have been sent.' => array('%d писмо беше изпратено.', '%d писма бяха изпратени.'),
			'Insert' => 'Вмъкване',
		),
		'bn' => array(
			'E-mail' => '​​ই-মেইল',
			'From' => 'থেকে',
			'Subject' => 'বিষয়',
			'Send' => 'পাঠান',
			'%d e-mail(s) have been sent.' => array('%d ইমেইল(গুলি) পাঠানো হয়েছে।', '%d ইমেইল(গুলি) পাঠানো হয়েছে।'),
			'Attachments' => 'সংযুক্তিগুলো',
			'Insert' => 'সংযোজন',
		),
		'bs' => array(
			'E-mail' => 'El. pošta',
			'From' => 'Od',
			'Subject' => 'Naslov',
			'Attachments' => 'Prilozi',
			'Send' => 'Pošalji',
			'%d e-mail(s) have been sent.' => array('%d poruka el. pošte je poslata.', '%d poruke el. pošte su poslate.', '%d poruka el. pošte je poslato.'),
			'Insert' => 'Umetni',
		),
		'ca' => array(
			'E-mail' => 'Correu electrònic',
			'From' => 'De',
			'Subject' => 'Assumpte',
			'Send' => 'Envia',
			'%d e-mail(s) have been sent.' => array('S\'ha enviat %d correu electrònic.', 'S\'han enviat %d correus electrònics.'),
			'Attachments' => 'Adjuncions',
			'Insert' => 'Insereix',
		),
		'cs' => array(
			'' => 'Umožňuje posílat e-maily na adresy v tabulce',
			'E-mail' => 'E-mail',
			'From' => 'Odesílatel',
			'Subject' => 'Předmět',
			'Attachments' => 'Přílohy',
			'Send' => 'Odeslat',
			'%d e-mail(s) have been sent.' => array('Byl odeslán %d e-mail.', 'Byly odeslány %d e-maily.', 'Bylo odesláno %d e-mailů.'),
			'Insert' => 'Vložit',
		),
		'da' => array(
			'E-mail' => 'E-mail',
			'From' => 'Fra',
			'Subject' => 'Titel',
			'Attachments' => 'Vedhæft',
			'Send' => 'Send',
			'%d e-mail(s) have been sent.' => array('%d email sendt.', '%d emails sendt.'),
			'Insert' => 'Indsæt',
		),
		'de' => array(
			'E-mail' => 'E-Mail',
			'From' => 'Von',
			'Subject' => 'Betreff',
			'Send' => 'Abschicken',
			'%d e-mail(s) have been sent.' => array('%d E-Mail abgeschickt.', '%d E-Mails abgeschickt.'),
			'Attachments' => 'Anhänge',
			'Insert' => 'Einfügen',
		),
		'el' => array(
			'E-mail' => 'E-mail',
			'From' => 'Από',
			'Subject' => 'Θέμα',
			'Attachments' => 'Συνημμένα',
			'Send' => 'Αποστολή',
			'%d e-mail(s) have been sent.' => array('%d e-mail απεστάλη.', '%d e-mail απεστάλησαν.'),
			'Insert' => 'Εισαγωγή',
		),
		'es' => array(
			'E-mail' => 'Email',
			'From' => 'De',
			'Subject' => 'Asunto',
			'Send' => 'Enviar',
			'%d e-mail(s) have been sent.' => array('%d email enviado.', '%d emails enviados.'),
			'Attachments' => 'Adjuntos',
			'Insert' => 'Agregar',
		),
		'et' => array(
			'E-mail' => 'E-post',
			'From' => 'Kellelt',
			'Subject' => 'Pealkiri',
			'Send' => 'Saada',
			'%d e-mail(s) have been sent.' => 'Saadetud kirju: %d.',
			'Attachments' => 'Manused',
			'Insert' => 'Sisesta',
		),
		'fa' => array(
			'E-mail' => 'پست الکترونیک',
			'From' => 'فرستنده',
			'Subject' => 'موضوع',
			'Attachments' => 'پیوست ها',
			'Send' => 'ارسال',
			'%d e-mail(s) have been sent.' => array('%d ایمیل ارسال شد.', '%d ایمیل ارسال شد.'),
			'Insert' => 'درج',
		),
		'fi' => array(
			'E-mail' => 'S-posti',
			'From' => 'Lähettäjä',
			'Subject' => 'Aihe',
			'Attachments' => 'Liitteet',
			'Send' => 'Lähetä',
			'%d e-mail(s) have been sent.' => array('% sähköpostiviestiä lähetetty.', '% sähköpostiviestiä lähetetty.'),
			'Insert' => 'Lisää',
		),
		'fr' => array(
			'E-mail' => 'Courriel',
			'From' => 'De',
			'Subject' => 'Sujet',
			'Send' => 'Envoyer',
			'%d e-mail(s) have been sent.' => array('%d message a été envoyé.', '%d messages ont été envoyés.'),
			'Attachments' => 'Pièces jointes',
			'Insert' => 'Insérer',
		),
		'gl' => array(
			'E-mail' => 'Email',
			'From' => 'De',
			'Subject' => 'Asunto',
			'Send' => 'Enviar',
			'%d e-mail(s) have been sent.' => array('%d email enviado.', '%d emails enviados.'),
			'Attachments' => 'Adxuntos',
			'Insert' => 'Inserir',
		),
		'he' => array(
			'E-mail' => 'דוא"ל',
			'From' => 'מ:',
			'Subject' => 'נושא',
			'Send' => 'שלח',
			'%d e-mail(s) have been sent.' => '%d הודעות דוא"ל נשלחו',
			'Attachments' => 'קבצים מצורפים',
			'Insert' => 'הכנס',
		),
		'hu' => array(
			'E-mail' => 'E-mail',
			'From' => 'Feladó',
			'Subject' => 'Tárgy',
			'Send' => 'Küldés',
			'%d e-mail(s) have been sent.' => array('%d e-mail elküldve.', '%d e-mail elküldve.', '%d e-mail elküldve.'),
			'Attachments' => 'Csatolmány',
			'Insert' => 'Beszúr',
		),
		'id' => array(
			'E-mail' => 'Surel',
			'From' => 'Dari',
			'Subject' => 'Judul',
			'Attachments' => 'Lampiran',
			'Send' => 'Kirim',
			'%d e-mail(s) have been sent.' => '%d surel berhasil dikirim.',
			'Insert' => 'Sisipkan',
		),
		'it' => array(
			'E-mail' => 'E-mail',
			'From' => 'Da',
			'Subject' => 'Oggetto',
			'Send' => 'Invia',
			'%d e-mail(s) have been sent.' => array('%d e-mail inviata.', '%d e-mail inviate.'),
			'Attachments' => 'Allegati',
			'Insert' => 'Inserisci',
		),
		'ja' => array(
			'' => 'テーブルに含まれるアドレスにメールを送信',
			'E-mail' => 'メール',
			'From' => '差出人',
			'Subject' => '題名',
			'Send' => '送信',
			'%d e-mail(s) have been sent.' => '%d メールを送信しました。',
			'Attachments' => '添付ファイル',
			'Insert' => '挿入',
		),
		'ka' => array(
			'E-mail' => 'ელ. ფოსტა',
			'From' => 'ავტორი:',
			'Subject' => 'თემა',
			'Send' => 'გაგზავნა',
			'%d e-mail(s) have been sent.' => 'გაიგზავნა %d წერილი.',
			'Attachments' => 'მიმაგრებული ფაილები',
			'Insert' => 'ჩასმა',
		),
		'ko' => array(
			'%d e-mail(s) have been sent.' => '%d개 메일을 보냈습니다.',
			'Attachments' => '첨부 파일',
			'E-mail' => '메일',
			'From' => '보낸 사람',
			'Send' => '보내기',
			'Subject' => '제목',
			'Insert' => '삽입',
		),
		'lt' => array(
			'E-mail' => 'El. paštas',
			'From' => 'Nuo',
			'Subject' => 'Antraštė',
			'Attachments' => 'Priedai',
			'Send' => 'Siųsti',
			'%d e-mail(s) have been sent.' => array('Išsiųstas %d laiškas.', 'Išsiųsti %d laiškai.', 'Išsiųsta %d laiškų.'),
			'Insert' => 'Įrašyti',
		),
		'lv' => array(
			'E-mail' => 'Epasts',
			'From' => 'No',
			'Subject' => 'Tēma',
			'Send' => 'Sūtīt',
			'%d e-mail(s) have been sent.' => array('Nosūtīts %d epasts.', 'Nosūtīti %d epasti.', 'Nosūtīti %d epasti.'),
			'Attachments' => 'Pielikumi',
			'Insert' => 'Ievietot',
		),
		'ms' => array(
			'E-mail' => 'Emel',
			'From' => 'Dari',
			'Subject' => 'Subjek',
			'Attachments' => 'Lampiran',
			'Send' => 'Hantar',
			'%d e-mail(s) have been sent.' => '%d emel telah dihantar.',
			'Insert' => 'Masukkan',
		),
		'nl' => array(
			'E-mail' => 'E-mail',
			'From' => 'Van',
			'Subject' => 'Onderwerp',
			'Send' => 'Verzenden',
			'%d e-mail(s) have been sent.' => array('%d e-mail verzonden.', '%d e-mails verzonden.'),
			'Attachments' => 'Bijlagen',
			'Insert' => 'Toevoegen',
		),
		'no' => array(
			'E-mail' => 'E-post',
			'From' => 'Fra',
			'Subject' => 'Tittel',
			'Attachments' => 'Vedlegg',
			'Send' => 'Send',
			'%d e-mail(s) have been sent.' => array('%d epost sendt.', '%d eposter sendt.'),
			'Insert' => 'Sett inn',
		),
		'pl' => array(
			'E-mail' => 'E-mail',
			'From' => 'Nadawca',
			'Subject' => 'Temat',
			'Attachments' => 'Załączniki',
			'Send' => 'Wyślij',
			'%d e-mail(s) have been sent.' => array('Wysłano %d e-mail.', 'Wysłano %d e-maile.', 'Wysłano %d e-maili.'),
			'Insert' => 'Dodaj',
		),
		'pt-br' => array(
			'E-mail' => 'E-mail',
			'From' => 'De',
			'Subject' => 'Assunto',
			'Send' => 'Enviar',
			'%d e-mail(s) have been sent.' => array('%d email foi enviado.', '%d emails foram enviados.'),
			'Attachments' => 'Anexos',
			'Insert' => 'Inserir',
		),
		'pt' => array(
			'E-mail' => 'E-mail',
			'From' => 'De',
			'Subject' => 'Assunto',
			'Send' => 'Enviar',
			'%d e-mail(s) have been sent.' => array('%d email enviado.', '%d emails enviados.'),
			'Attachments' => 'Anexos',
			'Insert' => 'Inserir',
		),
		'ro' => array(
			'E-mail' => 'Poșta electronică',
			'From' => 'De la',
			'Subject' => 'Pentru',
			'Send' => 'Trimite',
			'%d e-mail(s) have been sent.' => array('A fost trimis %d mail.', 'Au fost trimise %d mail-uri.'),
			'Attachments' => 'Fișiere atașate',
			'Insert' => 'Inserează',
		),
		'ru' => array(
			'E-mail' => 'Эл. почта',
			'From' => 'От',
			'Subject' => 'Тема',
			'Send' => 'Послать',
			'%d e-mail(s) have been sent.' => array('Было отправлено %d письмо.', 'Было отправлено %d письма.', 'Было отправлено %d писем.'),
			'Attachments' => 'Прикреплённые файлы',
			'Insert' => 'Вставить',
		),
		'sk' => array(
			'E-mail' => 'E-mail',
			'From' => 'Odosielateľ',
			'Subject' => 'Predmet',
			'Send' => 'Odoslať',
			'%d e-mail(s) have been sent.' => array('Bol odoslaný %d e-mail.', 'Boli odoslané %d e-maily.', 'Bolo odoslaných %d e-mailov.'),
			'Attachments' => 'Prílohy',
			'Insert' => 'Vložiť',
		),
		'sl' => array(
			'E-mail' => 'E-mail',
			'From' => 'Od',
			'Subject' => 'Zadeva',
			'Attachments' => 'Priponke',
			'Send' => 'Pošlji',
			'%d e-mail(s) have been sent.' => array('Poslan je %d e-mail.', 'Poslana sta %d e-maila.', 'Poslani so %d e-maili.', 'Poslanih je %d e-mailov.'),
			'Insert' => 'Vstavi',
		),
		'sr' => array(
			'E-mail' => 'Ел. пошта',
			'From' => 'Од',
			'Subject' => 'Наслов',
			'Attachments' => 'Прилози',
			'Send' => 'Пошаљи',
			'%d e-mail(s) have been sent.' => array('%d порука ел. поште је послата.', '%d поруке ел. поште су послате.', '%d порука ел. поште је послато.'),
			'Insert' => 'Уметни',
		),
		'sv' => array(
			'E-mail' => 'Email',
			'From' => 'Från',
			'Subject' => 'Ämne',
			'Attachments' => 'Bilagor',
			'Send' => 'Skicka',
			'%d e-mail(s) have been sent.' => array('%d email har blivit skickat.', '%d email har blivit skickade.'),
			'Insert' => 'Infoga',
		),
		'ta' => array(
			'E-mail' => 'மின்ன‌ஞ்ச‌ல்',
			'From' => 'அனுப்புனர்',
			'Subject' => 'பொருள்',
			'Send' => 'அனுப்பு',
			'%d e-mail(s) have been sent.' => array('%d மின்ன‌ஞ்ச‌ல் அனுப்ப‌ப‌ட்ட‌து.', '%d மின்ன‌ஞ்ச‌ல்க‌ள் அனுப்ப‌ப்ப‌ட்ட‌ன‌.'),
			'Attachments' => 'இணைப்புக‌ள்',
			'Insert' => 'புகுத்து',
		),
		'th' => array(
			'E-mail' => 'อีเมล์',
			'From' => 'จาก',
			'Subject' => 'หัวข้อ',
			'Send' => 'ส่ง',
			'%d e-mail(s) have been sent.' => 'มี %d อีเมล์ ถูกส่งออกแล้ว.',
			'Attachments' => 'ไฟล์แนบ',
			'Insert' => 'เพิ่ม',
		),
		'tr' => array(
			'E-mail' => 'E-posta',
			'From' => 'Gönderen',
			'Subject' => 'Konu',
			'Attachments' => 'Ekler',
			'Send' => 'Gönder',
			'%d e-mail(s) have been sent.' => array('%d e-posta gönderildi.', '%d adet e-posta gönderildi.'),
			'Insert' => 'Ekle',
		),
		'uk' => array(
			'E-mail' => 'E-mail',
			'From' => 'Від',
			'Subject' => 'Заголовок',
			'Attachments' => 'Додатки',
			'Send' => 'Надіслати',
			'%d e-mail(s) have been sent.' => array('Було надіслано %d повідомлення.', 'Було надіслано %d повідомлення.', 'Було надіслано %d повідомлень.'),
			'Insert' => 'Вставити',
		),
		'uz' => array(
			'E-mail' => 'E-pochta',
			'From' => 'Kimdan',
			'Subject' => 'Mavzu',
			'Attachments' => 'Ilovalar',
			'Send' => 'Yuborish',
			'%d e-mail(s) have been sent.' => array('%d e-pochta yuborildi.', '%d e-pochtalar yuborildi.'),
			'Insert' => 'Kiritish',
		),
		'vi' => array(
			'E-mail' => 'Địa chỉ email',
			'From' => 'Người gửi',
			'Subject' => 'Chủ đề',
			'Attachments' => 'Đính kèm',
			'Send' => 'Gửi',
			'%d e-mail(s) have been sent.' => '%d thư đã gửi.',
			'Insert' => 'Thêm',
		),
		'zh-tw' => array(
			'E-mail' => '電子郵件',
			'From' => '來自',
			'Subject' => '主旨',
			'Attachments' => '附件',
			'Send' => '寄出',
			'%d e-mail(s) have been sent.' => '已寄出 %d 封郵件。',
			'Insert' => '新增',
		),
		'zh' => array(
			'E-mail' => '电子邮件',
			'From' => '来自',
			'Subject' => '主题',
			'Attachments' => '附件',
			'Send' => '发送',
			'%d e-mail(s) have been sent.' => '%d 封邮件已发送。',
			'Insert' => '插入',
		),
		'hr' => array(
			'' => 'Slanje e-pošte odabranim recima',
			'E-mail' => 'E-pošta',
			'From' => 'Od',
			'Subject' => 'Predmet',
			'Attachments' => 'Privici',
			'Send' => 'Pošalji',
			'%d e-mail(s) have been sent.' => array('%d e-mail je poslan.', '%d e-maila su poslana.', '%d e-mailova je poslano.'),
			'Insert' => 'Unesi',
		),
	);
}
