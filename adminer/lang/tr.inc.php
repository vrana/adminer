<?php
namespace Adminer;

Lang::$translations = array(
	'System' => 'Sistem', // label for database system selection (MySQL, SQLite, ...)
	'Server' => 'Sunucu',
	'Username' => 'Kullanıcı',
	'Password' => 'Parola',
	'Permanent login' => 'Beni hatırla',
	'Login' => 'Giriş',
	'Logout' => 'Çıkış',
	'Logged in as: %s' => '%s olarak giriş yapıldı.',
	'Logout successful.' => 'Oturum başarıyla sonlandı.',
	'Thanks for using Adminer. Consider <a href="https://www.adminer.org/en/donation/">donating</a>.' => 'Adminer kullandığınız için teşekkür ederiz <a href="https://www.adminer.org/en/donation/">bağış yapmayı düşünün</a>.',
	'Invalid credentials.' => 'Geçersiz kimlik bilgileri.',
	'Too many unsuccessful logins, try again in %d minute(s).' => array('Çok fazla oturum açma denemesi yapıldı.', '%d Dakika sonra tekrar deneyiniz.'),
	'Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.' => 'Ana şifrenin süresi doldu. Kalıcı olması için <a href="https://www.adminer.org/en/extension/"%s>%s medodunu</a> kullanın.',
	'Language' => 'Dil',
	'Invalid CSRF token. Submit the form again.' => 'Geçersiz (CSRF) jetonu. Formu tekrar yolla.',
	'If you did not send this request from Adminer, close this page.' => 'Bu isteği Adminer\'den göndermediyseniz bu sayfayı kapatın.',
	'No extension' => 'Uzantı yok',
	'None of the supported PHP extensions (%s) are available.' => 'Desteklenen PHP eklentilerinden (%s) hiçbiri mevcut değil.',
	'Connecting to privileged ports is not allowed.' => 'Ayrıcalıklı bağlantı noktalarına bağlanmaya izin verilmiyor.',
	'Session support must be enabled.' => 'Oturum desteği etkin olmalıdır.',
	'Session expired. Please log in again.' => 'Oturum süresi doldu, lütfen tekrar giriş yapın.',
	'The action will be performed after successful login with the same credentials.' => 'İşlem, aynı kimlik bilgileriyle başarıyla oturum açıldıktan sonra gerçekleştirilecektir.',
	'%s version: %s through PHP extension %s' => '%s sürüm: %s, %s PHP eklentisi ile',
	'Refresh' => 'Tazele',

	'ltr' => 'ltr', // text direction

	'Privileges' => 'İzinler',
	'Create user' => 'Kullanıcı oluştur',
	'User has been dropped.' => 'Kullanıcı silindi.',
	'User has been altered.' => 'Kullanıcı değiştirildi.',
	'User has been created.' => 'Kullanıcı oluşturuldu.',
	'Hashed' => 'Harmanlandı',
	'Column' => 'Kolon',
	'Routine' => 'Yordam',
	'Grant' => 'Yetki Ver',
	'Revoke' => 'Yetki Kaldır',

	'Process list' => 'İşlem listesi',
	'%d process(es) have been killed.' => array('%d işlem sonlandırıldı.', '%d adet işlem sonlandırıldı.'),
	'Kill' => 'Sonlandır',

	'Variables' => 'Değişkenler',
	'Status' => 'Durum',

	'SQL command' => 'SQL komutu',
	'%d query(ies) executed OK.' => array('%d sorgu başarıyla çalıştırıldı.', '%d adet sorgu başarıyla çalıştırıldı.'),
	'Query executed OK, %d row(s) affected.' => 'Sorgu başarıyla çalıştırıldı, %d adet kayıt etkilendi.',
	'No commands to execute.' => 'Çalıştırılacak komut yok.',
	'Error in query' => 'Sorguda hata',
	'Warnings' => 'Uyarılar',
	'%s queries are not supported.' => '%s sorguları desteklenmiyor.',
	'Execute' => 'Çalıştır',
	'Stop on error' => 'Hata oluşursa dur',
	'Show only errors' => 'Sadece hataları göster',
	'%.3f s' => '%.3f s', // sprintf() format for time of the command
	'History' => 'Geçmiş',
	'Clear' => 'Temizle',
	'Edit all' => 'Tümünü düzenle',

	'File upload' => 'Dosya gönder',
	'From server' => 'Sunucudan',
	'Webserver file %s' => '%s web sunucusu dosyası',
	'Run file' => 'Dosyayı çalıştır',
	'File does not exist.' => 'Dosya mevcut değil.',
	'File uploads are disabled.' => 'Dosya gönderimi etkin değil.',
	'Unable to upload a file.' => 'Dosya gönderilemiyor.',
	'Maximum allowed file size is %sB.' => 'İzin verilen dosya boyutu sınırı %sB.',
	'The POST data is too large. Reduce the data or increase the %s configuration directive.' => 'Çok büyük POST verisi, veriyi azaltın ya da %s ayar yönergesini uygun olarak yapılandırın.',
	'You can upload a large SQL file via FTP and import it from the server.' => 'FTP yoluyla büyük bir SQL dosyası yükleyebilir ve sunucudan içe aktarabilirsiniz.',
	'You are offline.' => 'Çevrimdışısınız.',
	'Menu' => 'Menü', // Claude Opus 5

	'Export' => 'Dışarı Aktar',
	'Output' => 'Çıktı',
	'open' => 'aç',
	'save' => 'kaydet',
	'Saving…' => 'Saydediliyor…',
	'Format' => 'Biçim',
	'Data' => 'Veri',

	'Database' => 'Veri Tabanı',
	'DB' => 'DB',
	'Use' => 'Kullan',
	'Select database' => 'Veri tabanı seç',
	'Invalid database.' => 'Geçersiz veri tabanı.',
	'Database has been dropped.' => 'Veri tabanı silindi.',
	'Databases have been dropped.' => 'Veritabanları silindi.',
	'Database has been created.' => 'Veri tabanı oluşturuldu.',
	'Database has been renamed.' => 'Veri tabanının ismi değiştirildi.',
	'Database has been altered.' => 'Veri tabanı değiştirildi.',
	'Alter database' => 'Veri tabanını değiştir',
	'Create database' => 'Veri tabanı oluştur',
	'Database schema' => 'Veri tabanı şeması',

	'Permanent link' => 'Kalıcı bağlantı', // link to current database schema layout

	',' => ' ', // thousands separator - must contain single byte
	'0123456789' => '0123456789',
	'Engine' => 'Motor',
	'Collation' => 'Karşılaştırma',
	'Data Length' => 'Veri Uzunluğu',
	'Index Length' => 'İndex Uzunluğu',
	'Data Free' => 'Boş Veri',
	'Rows' => 'Kayıtlar',
	'%d in total' => 'toplam %d',
	'Analyze' => 'Çözümle',
	'Optimize' => 'Optimize Et',
	'Vacuum' => 'Vakumla',
	'Check' => 'Denetle',
	'Repair' => 'Tamir Et',
	'Truncate' => 'Boşalt',
	'Tables have been truncated.' => 'Tablolar boşaltıldı.',
	'Move to another database' => 'Başka veri tabanına taşı',
	'Move' => 'Taşı',
	'Tables have been moved.' => 'Tablolar taşındı.',
	'Copy' => 'Kopyala',
	'Tables have been copied.' => 'Tablolar kopyalandı.',

	'Routines' => 'Yordamlar',
	'Routine has been called, %d row(s) affected.' => array('Yordam çağrıldı, %d adet kayıt etkilendi.', 'Yordam çağrıldı, %d kayıt etkilendi.'),
	'Call' => 'Çağır',
	'Parameter name' => 'Parametre adı',
	'Create procedure' => 'Yöntem oluştur',
	'Create function' => 'Fonksiyon oluştur',
	'Routine has been dropped.' => 'Yordam silindi.',
	'Routine has been altered.' => 'Yordam değiştirildi.',
	'Routine has been created.' => 'Yordam oluşturuldu.',
	'Alter function' => 'Fonksyionu değiştir',
	'Alter procedure' => 'Yöntemi değiştir',
	'Return type' => 'Geri dönüş türü',

	'Events' => 'Olaylar',
	'Event has been dropped.' => 'Olay silindi.',
	'Event has been altered.' => 'Olay değiştirildi.',
	'Event has been created.' => 'Olay oluşturuldu.',
	'Alter event' => 'Olayı değiştir',
	'Create event' => 'Olay oluştur',
	'At given time' => 'Verilen zamanda',
	'Every' => 'Her zaman',
	'Schedule' => 'Takvimli',
	'Start' => 'Başla',
	'End' => 'Son',
	'On completion preserve' => 'Tamamlama koruması',

	'Tables' => 'Tablolar',
	'Tables and views' => 'Tablolar ve görünümler',
	'All' => 'Tümü', // Claude Opus 5
	'Table' => 'Tablo',
	'No tables.' => 'Tablo yok.',
	'Alter table' => 'Tabloyu değiştir',
	'Create table' => 'Tablo oluştur',
	'Table has been dropped.' => 'Tablo silindi.',
	'Tables have been dropped.' => 'Tablolar silindi.',
	'Tables have been optimized.' => 'Tablolar en uygun hale getirildi.',
	'Table has been altered.' => 'Tablo değiştirildi.',
	'Table has been created.' => 'Tablo oluşturuldu.',
	'Table name' => 'Tablo adı',
	'Show structure' => 'Yapıyı göster',
	'engine' => 'motor',
	'collation' => 'karşılaştırma',
	'Column name' => 'Kolon adı',
	'Type' => 'Tür',
	'Length' => 'Uzunluk',
	'Auto Increment' => 'Otomatik Artır',
	'Options' => 'Seçenekler',
	'Comment' => 'Yorum',
	'Default value' => 'Varsayılan değer',
	'Default values' => 'Varsayılan değerler',
	'Drop' => 'Sil',
	'Drop %s?' => 'Sil %s?',
	'Are you sure?' => 'Emin misiniz?',
	'Size' => 'Boyut',
	'Compute' => 'Hesapla',
	'Remove' => 'Sil',
	'Maximum number of allowed fields exceeded. Please increase %s.' => 'İzin verilen en fazla alan sayısı aşıldı. Lütfen %s değerlerini artırın.',

	'Partition by' => 'Bununla bölümle',
	'Partitions' => 'Bölümler',
	'Partition name' => 'Bölüm adı',
	'Values' => 'Değerler',

	'View' => 'Görünüm',
	'Materialized view' => 'Materialized Görünüm',
	'View has been dropped.' => 'Görünüm silindi.',
	'View has been altered.' => 'Görünüm değiştirildi.',
	'View has been created.' => 'Görünüm oluşturuldu.',
	'Alter view' => 'Görünümü değiştir',
	'Create view' => 'Görünüm oluştur',

	'Indexes' => 'İndeksler',
	'Indexes have been altered.' => 'İndeksler değiştirildi.',
	'Alter indexes' => 'İndeksleri değiştir',
	'Add next' => 'Bundan sonra ekle',
	'Index Type' => 'İndex Türü',
	'length' => 'uzunluğu',
	'operator class' => 'operatör sınıfı', // Claude Fable 5

	'Foreign keys' => 'Dış anahtarlar',
	'Foreign key has been dropped.' => 'Dış anahtar silindi.',
	'Foreign key has been altered.' => 'Dış anahtar değiştirildi.',
	'Foreign key has been created.' => 'Dış anahtar oluşturuldu.',
	'Target table' => 'Hedef tablo',
	'Change' => 'Değiştir',
	'Source' => 'Kaynak',
	'Target' => 'Hedef',
	'Add column' => 'Kolon ekle',
	'Alter' => 'Değiştir',
	'Alter foreign key' => 'Dış anahtarı değiştir', // Claude Opus 5
	'Create foreign key' => 'Dış anahtar oluştur', // Claude Opus 5
	'ON DELETE' => 'ON DELETE (Hedefteki Kayıt Silinirse)',
	'ON UPDATE' => 'ON UPDATE (Hedefteki Kayıt Değiştirilirse)',
	'Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.' => 'Kaynak ve hedef kolonlar aynı veri türünde olmalı, hedef kolonlarda dizin bulunmalı ve başvurulan veri mevcut olmalı.',

	'Triggers' => 'Tetikler',
	'Trigger has been dropped.' => 'Tetik silindi.',
	'Trigger has been altered.' => 'Tetik değiştirildi.',
	'Trigger has been created.' => 'Tetik oluşturuldu.',
	'Alter trigger' => 'Tetiği değiştir',
	'Create trigger' => 'Tetik oluştur',
	'Time' => 'Zaman',
	'Event' => 'Olay',
	'Name' => 'Ad',

	'select' => 'seç',
	'Select' => 'Seç',
	'Select data' => 'Veri seç',
	'Functions' => 'Fonksiyonlar',
	'Aggregation' => 'Kümeleme',
	'Search' => 'Ara',
	'anywhere' => 'hiçbir yerde',
	'Search data in tables' => 'Tablolarda veri ara',
	'Sort' => 'Sırala',
	'descending' => 'Azalan',
	'Limit' => 'Limit',
	'Limit rows' => 'Satır Limiti',
	'Text length' => 'Metin Boyutu',
	'Action' => 'İşlem',
	'Full table scan' => 'Tam tablo taraması',
	'Unable to select the table' => 'Tablo seçilemedi',
	'No rows.' => 'Kayıt yok.',
	'%d / ' => '%d / ',
	'%d row(s)' => array('%d kayıt', '%d adet kayıt'),
	'Page' => 'Sayfa',
	'last' => 'son',
	'Load more data' => 'Daha fazla veri yükle',
	'Loading…' => 'Yükleniyor…',
	'Whole result' => 'Tüm sonuç',
	'%d byte(s)' => '%d bayt',

	'Import' => 'İçeri Aktar',
	'%d row(s) have been imported.' => array('%d kayıt içeri aktarıldı.', '%d adet kayıt içeri aktarıldı.'),
	'File must be in UTF-8 encoding.' => 'Dosya UTF-8 kodlamasında olmalıdır.',

	'All rows on this page' => 'Bu sayfadaki tüm satırlar', // Claude Opus 5
	'Modify' => 'Düzenle', // in-place editing in select
	'Ctrl+click on a value to modify it.' => 'Bir değeri değiştirmek için üzerine Ctrl+tıklayın.',
	'Use the edit link to modify this value.' => 'Değeri değiştirmek için düzenleme bağlantısını kullanın.',

	'Item%s has been inserted.' => 'Kayıt%s eklendi.', // %s can contain auto-increment value
	'Item has been deleted.' => 'Kayıt silindi.',
	'Item has been updated.' => 'Kayıt güncellendi.',
	'%d item(s) have been affected.' => array('%d kayıt etkilendi.', '%d adet kayıt etkilendi.'),
	'New item' => 'Yeni kayıt',
	'original' => 'orijinal',
	'empty' => 'boş', // label for value '' in enum data type
	'edit' => 'düzenle',
	'Edit' => 'Düzenle',
	'Insert' => 'Ekle',
	'Save' => 'Kaydet',
	'Save and continue editing' => 'Kaydet ve düzenlemeye devam et',
	'Save and insert next' => 'Kaydet ve sonrakini ekle',
	'Selected' => 'Seçildi',
	'Clone' => 'Kopyala',
	'Delete' => 'Sil',
	'You have no privileges to update this table.' => 'Bu tabloyu güncellemek için yetkiniz yok.',

	// data type descriptions
	'Numbers' => 'Sayılar',
	'Date and time' => 'Tarih ve zaman',
	'Strings' => 'Dizge',
	'Binary' => 'İkili',
	'Lists' => 'Listeler',
	'Network' => 'Ağ',
	'Geometry' => 'Geometri',
	'Relations' => 'İlişkiler',

	'Editor' => 'Düzenleyici',
	'$1-$3-$5' => '$6.$4.$1', // date format in Editor: $1 yyyy, $2 yy, $3 mm, $4 m, $5 dd, $6 d
	'[yyyy]-mm-dd' => 'g.a.[yyyy]', // hint for date format - use language equivalents for day, month and year shortcuts
	'HH:MM:SS' => 'SS:DD:ss', // hint for time format - use language equivalents for hour, minute and second shortcuts
	'now' => 'şimdi',
	'yes' => 'evet',
	'no' => 'hayır',

	'File exists.' => 'Dosya zaten mevcut.', // general SQLite error in create, drop or rename database
	'Please use one of these file extensions: %s.' => '%s uzantılarından birini kullanın.',

	// PostgreSQL and MS SQL schema support
	'Alter schema' => 'Şemayı değiştir',
	'Create schema' => 'Şema oluştur',
	'Schema has been dropped.' => 'Şema silindi.',
	'Schema has been created.' => 'Şema oluşturuldu.',
	'Schema has been altered.' => 'Şema değiştirildi.',
	'Schema' => 'Şema',
	'Invalid schema.' => 'Geçersiz şema.',

	// PostgreSQL sequences support
	'Sequences' => 'Diziler',
	'Create sequence' => 'Dizi oluştur',
	'Sequence has been dropped.' => 'Dizi silindi.',
	'Sequence has been created.' => 'Dizi oluşturuldu.',
	'Sequence has been altered.' => 'Dizi değiştirildi.',
	'Alter sequence' => 'Diziyi değiştir',

	// PostgreSQL user-defined types support
	'User types' => 'Kullanıcı türleri',
	'Create type' => 'Tür oluştur',
	'Type has been dropped.' => 'Tür silindi.',
	'Type has been created.' => 'Tür oluşturuldu.',
	'Type has been altered.' => 'Tür değiştirildi.', // Claude Opus 5
	'Alter type' => 'Türü değiştir',
	'Check has been dropped.' => 'Kontrol silindi.', // Claude Fable 5
	'Check has been altered.' => 'Kontrol değiştirildi.', // Claude Fable 5
	'Check has been created.' => 'Kontrol oluşturuldu.', // Claude Fable 5
	'Alter check' => 'Kontrolü değiştir', // Claude Fable 5
	'Create check' => 'Kontrol oluştur', // Claude Fable 5
	'overwrite' => 'üzerine yaz', // Claude Fable 5
	'Algorithm' => 'Algoritma', // Claude Fable 5
	'Columns' => 'Kolonlar', // Claude Fable 5
	'Condition' => 'Koşul', // Claude Fable 5
	'Inherits from' => 'Miras aldığı tablolar', // Claude Fable 5
	'Checks' => 'Kontroller', // Claude Fable 5
	'Inherited by' => 'Miras alan tablolar', // Claude Fable 5
	'hostname[:port] or :socket' => 'hostname[:port] veya :socket', // Claude Fable 5
	'Adminer does not support accessing a database without a password.' => 'Adminer parolasız bir veri tabanına erişimi desteklemez.', // Claude Fable 5
	'Invalid server.' => 'Geçersiz sunucu.', // Claude Fable 5
	'There is a space in the entered password, which might be the cause.' => 'Girilen parolada boşluk var, sorunun nedeni bu olabilir.', // Claude Fable 5
	'Loaded plugins' => 'Yüklenen eklentiler', // Claude Fable 5
	'screenshot' => 'ekran görüntüsü', // Claude Fable 5
	'Increase %s.' => '%s değerini artırın.', // Claude Fable 5
	'Unknown error.' => 'Bilinmeyen hata.', // Claude Fable 5
	'%s must <a%s>return an array</a>.' => '%s <a%s>bir dizi döndürmelidir</a>.', // Claude Fable 5
	'<a%s>Configure</a> %s in %s.' => '%2$s ögesini %3$s içinde <a%1$s>yapılandırın</a>.', // Claude Fable 5
	'Every plugin must <a%s>be an object</a>.' => 'Her eklenti <a%s>bir nesne olmalıdır</a>.', // Claude Opus 5
	'Disable %s or enable the %s or %s extension.' => '%s eklentisini devre dışı bırakın veya %s ya da %s eklentilerini etkinleştirin.', // Claude Fable 5
	'The database does not support passwords.' => 'Veri tabanı parolayı desteklemez.', // Claude Fable 5
);

// run `php ../../lang.php tr` to update this file
