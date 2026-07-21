<?php
namespace Adminer;

Lang::$translations = array(
	'System' => 'Sistem', // label for database system selection (MySQL, SQLite, ...)
	'Server' => 'Server',
	'Username' => 'Pengguna',
	'Password' => 'Sandi',
	'Permanent login' => 'Masuk permanen',
	'Login' => 'Masuk',
	'Logout' => 'Keluar',
	'Logged as: %s' => 'Masuk sebagai: %s',
	'Logout successful.' => 'Berhasil keluar.',
	'Invalid credentials.' => 'Akses tidak sah.',
	'Language' => 'Bahasa',
	'Invalid CSRF token. Send the form again.' => 'Token CSRF tidak sah. Kirim ulang formulir.',
	'No extension' => 'Ekstensi tidak ada',
	'None of the supported PHP extensions (%s) are available.' => 'Ekstensi PHP yang didukung (%s) tidak ada.',
	'Session support must be enabled.' => 'Dukungan sesi harus aktif.',
	'Session expired, please login again.' => 'Sesi habis, silakan masuk lagi.',
	'%s version: %s through PHP extension %s' => 'Versi %s: %s dengan ekstensi PHP %s',
	'Refresh' => 'Segarkan',

	'ltr' => 'ltr', // text direction - 'ltr' or 'rtl'

	'Privileges' => 'Privilese',
	'Create user' => 'Buat pengguna',
	'User has been dropped.' => 'Pengguna berhasil dihapus.',
	'User has been altered.' => 'Pengguna berhasil diubah.',
	'User has been created.' => 'Pengguna berhasil dibuat.',
	'Hashed' => 'Hashed*',
	'Column' => 'Kolom',
	'Routine' => 'Rutin',
	'Grant' => 'Beri',
	'Revoke' => 'Tarik',

	'Process list' => 'Daftar proses',
	'%d process(es) have been killed.' => '%d proses berhasil dihentikan.',
	'Kill' => 'Hentikan',

	'Variables' => 'Variabel',
	'Status' => 'Status',

	'SQL command' => 'Perintah SQL',
	'%d query(s) executed OK.' => '%d kueri berhasil dijalankan.',
	'Query executed OK, %d row(s) affected.' => 'Kueri berhasil, %d baris terpengaruh.',
	'No commands to execute.' => 'Tidak ada perintah untuk dijalankan.',
	'Error in query' => 'Galat dalam kueri',
	'Execute' => 'Jalankan',
	'Stop on error' => 'Hentikan jika galat',
	'Show only errors' => 'Hanya tampilkan galat',
	'%.3f s' => '%.3f s', // sprintf() format for time of the command
	'History' => 'Riwayat',
	'Clear' => 'Bersihkan',

	'Edit all' => 'Sunting semua',
	'File upload' => 'Unggah berkas',
	'From server' => 'Dari server',
	'Webserver file %s' => 'Berkas server web %s',
	'Run file' => 'Jalankan berkas',
	'File does not exist.' => 'Berkas tidak ada.',
	'File uploads are disabled.' => 'Pengunggahan berkas dimatikan.',
	'Unable to upload a file.' => 'Tidak dapat mengunggah berkas.',
	'Maximum allowed file size is %sB.' => 'Besar berkas yang diizinkan adalah %sB.',
	'Too big POST data. Reduce the data or increase the %s configuration directive.' => 'Data POST terlalu besar. Kurangi data atau perbesar direktif konfigurasi %s.',

	'Export' => 'Ekspor',
	'Output' => 'Hasil',
	'open' => 'buka',
	'save' => 'simpan',
	'Format' => 'Format',
	'Data' => 'Data',

	'Database' => 'Basis data',
	'Use' => 'Gunakan',
	'Select database' => 'Pilih basis data',
	'Invalid database.' => 'Basis data tidak sah.',
	'Database has been dropped.' => 'Basis data berhasil dihapus.',
	'Databases have been dropped.' => 'Basis data berhasil dihapus.',
	'Database has been created.' => 'Basis data berhasil dibuat.',
	'Database has been renamed.' => 'Basis data berhasil diganti namanya.',
	'Database has been altered.' => 'Basis data berhasil diubah.',
	'Alter database' => 'Ubah basis data',
	'Create database' => 'Buat basis data',
	'Database schema' => 'Skema basis data',

	'Permanent link' => 'Pranala permanen', // link to current database schema layout

	',' => '.', // thousands separator - must contain single byte
	'0123456789' => '0123456789',
	'Engine' => 'Mesin',
	'Collation' => 'Kolasi',
	'Data Length' => 'Panjang Data',
	'Index Length' => 'Panjang Indeks',
	'Data Free' => 'Data Bebas',
	'Rows' => 'Baris',
	'%d in total' => '%d total',
	'Analyze' => 'Analisis',
	'Optimize' => 'Optimalkan',
	'Check' => 'Periksa',
	'Repair' => 'Perbaiki',
	'Truncate' => 'Kosongkan',
	'Tables have been truncated.' => 'Tabel berhasil dikosongkan.',
	'Move to other database' => 'Pindahkan ke basis data lain',
	'Move' => 'Pindahkan',
	'Tables have been moved.' => 'Tabel berhasil dipindahkan.',
	'Copy' => 'Salin',
	'Tables have been copied.' => 'Tabel berhasil disalin.',

	'Routines' => 'Rutin',
	'Routine has been called, %d row(s) affected.' => 'Rutin telah dipanggil, %d baris terpengaruh.',
	'Call' => 'Panggilan',
	'Parameter name' => 'Nama parameter',
	'Create procedure' => 'Buat prosedur',
	'Create function' => 'Buat fungsi',
	'Routine has been dropped.' => 'Rutin berhasil dihapus.',
	'Routine has been altered.' => 'Rutin berhasil diubah.',
	'Routine has been created.' => 'Rutin berhasil dibuat.',
	'Alter function' => 'Ubah fungsi',
	'Alter procedure' => 'Ubah prosedur',
	'Return type' => 'Jenis pengembalian',

	'Events' => 'Even',
	'Event has been dropped.' => 'Even berhasil dihapus.',
	'Event has been altered.' => 'Even berhasil diubah.',
	'Event has been created.' => 'Even berhasil dibuat.',
	'Alter event' => 'Ubah even',
	'Create event' => 'Buat even',
	'At given time' => 'Pada waktu tertentu',
	'Every' => 'Setiap',
	'Schedule' => 'Jadwal',
	'Start' => 'Mulai',
	'End' => 'Selesai',
	'On completion preserve' => 'Pertahankan saat selesai',

	'Tables' => 'Tabel',
	'Tables and views' => 'Tabel dan tampilan',
	'Table' => 'Tabel',
	'No tables.' => 'Tidak ada tabel.',
	'Alter table' => 'Ubah tabel',
	'Create table' => 'Buat tabel',
	'Table has been dropped.' => 'Tabel berhasil dihapus.',
	'Tables have been dropped.' => 'Tabel berhasil dihapus.',
	'Tables have been optimized.' => 'Tabel berhasil dioptimalkan.',
	'Table has been altered.' => 'Tabel berhasil diubah.',
	'Table has been created.' => 'Tabel berhasil dibuat.',
	'Table name' => 'Nama tabel',
	'Show structure' => 'Lihat struktur',
	'engine' => 'mesin',
	'collation' => 'kolasi',
	'Column name' => 'Nama kolom',
	'Type' => 'Jenis',
	'Length' => 'Panjang',
	'Auto Increment' => 'Inkrementasi Otomatis',
	'Options' => 'Opsi',
	'Comment' => 'Komentar',
	'Default values' => 'Nilai bawaan',
	'Drop' => 'Hapus',
	'Are you sure?' => 'Anda yakin?',
	'Remove' => 'Hapus',
	'Maximum number of allowed fields exceeded. Please increase %s.' => 'Sudah lebih dumlah ruas maksimum yang diizinkan. Harap naikkan %s.',

	'Partition by' => 'Partisi menurut',
	'Partitions' => 'Partisi',
	'Partition name' => 'Nama partisi',
	'Values' => 'Nilai',

	'View' => 'Tampilan',
	'View has been dropped.' => 'Tampilan berhasil dihapus.',
	'View has been altered.' => 'Tampilan berhasil diubah.',
	'View has been created.' => 'Tampilan berhasil dibuat.',
	'Alter view' => 'Ubah tampilan',
	'Create view' => 'Buat tampilan',

	'Indexes' => 'Indeks',
	'Indexes have been altered.' => 'Indeks berhasil diubah.',
	'Alter indexes' => 'Ubah indeks',
	'Add next' => 'Tambah setelahnya',
	'Index Type' => 'Jenis Indeks',
	'length' => 'panjang',

	'Foreign keys' => 'Kunci asing',
	'Foreign key' => 'Kunci asing',
	'Foreign key has been dropped.' => 'Kunci asing berhasil dihapus.',
	'Foreign key has been altered.' => 'Kunci asing berhasil diubah.',
	'Foreign key has been created.' => 'Kunci asing berhasil dibuat.',
	'Target table' => 'Tabel sasaran',
	'Change' => 'Ubah',
	'Source' => 'Sumber',
	'Target' => 'Sasaran',
	'Add column' => 'Tambah kolom',
	'Alter' => 'Ubah',
	'Add foreign key' => 'Tambah kunci asing',
	'ON DELETE' => 'ON DELETE',
	'ON UPDATE' => 'ON UPDATE',
	'Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.' => 'Kolom sumber dan sasaran harus memiliki jenis data yang sama. Kolom sasaran harus memiliki indeks dan data rujukan harus ada.',

	'Triggers' => 'Pemicu',
	'Add trigger' => 'Tambah pemicu',
	'Trigger has been dropped.' => 'Pemicu berhasil dihapus.',
	'Trigger has been altered.' => 'Pemicu berhasil diubah.',
	'Trigger has been created.' => 'Pemicu berhasil dibuat.',
	'Alter trigger' => 'Ubah pemicu',
	'Create trigger' => 'Buat pemicu',
	'Time' => 'Waktu',
	'Event' => 'Even',
	'Name' => 'Nama',

	'select' => 'pilih',
	'Select' => 'Pilih',
	'Select data' => 'Pilih data',
	'Functions' => 'Fungsi',
	'Aggregation' => 'Agregasi',
	'Search' => 'Cari',
	'anywhere' => 'di mana pun',
	'Search data in tables' => 'Cari data dalam tabel',
	'Sort' => 'Urutkan',
	'descending' => 'menurun',
	'Limit' => 'Batas',
	'Text length' => 'Panjang teks',
	'Action' => 'Tindakan',
	'Full table scan' => 'Pindai tabel lengkap',
	'Unable to select the table' => 'Gagal memilih tabel',
	'No rows.' => 'Tidak ada baris.',
	'%d row(s)' => '%d baris',
	'Page' => 'Halaman',
	'last' => 'terakhir',
	'Whole result' => 'Seluruh hasil',
	'%d byte(s)' => '%d bita',

	'Import' => 'Impor',
	'%d row(s) have been imported.' => '%d baris berhasil diimpor.',

	'Use edit link to modify this value.' => 'Gunakan pranala suntingan untuk mengubah nilai ini.', // in-place editing in select

	'Item%s has been inserted.' => 'Entri%s berhasil disisipkan.', // %s can contain auto-increment value
	'Item has been deleted.' => 'Entri berhasil dihapus.',
	'Item has been updated.' => 'Entri berhasil diperbarui.',
	'%d item(s) have been affected.' => '%d entri terpengaruh.',
	'New item' => 'Entri baru',
	'original' => 'asli',
	'empty' => 'kosong', // label for value '' in enum data type
	'edit' => 'sunting',
	'Edit' => 'Sunting',
	'Insert' => 'Sisipkan',
	'Save' => 'Simpan',
	'Save and continue edit' => 'Simpan dan lanjut menyunting',
	'Save and insert next' => 'Simpan dan sisipkan berikutnya',
	'Clone' => 'Gandakan',
	'Delete' => 'Hapus',

	// data type descriptions
	'Numbers' => 'Angka',
	'Date and time' => 'Tanggal dan waktu',
	'Strings' => 'String',
	'Binary' => 'Binari',
	'Lists' => 'Daftar',
	'Network' => 'Jaringan',
	'Geometry' => 'Geometri',
	'Relations' => 'Relasi',

	'Editor' => 'Editor',
	'$1-$3-$5' => '$1-$3-$5', // date format in Editor: $1 yyyy, $2 yy, $3 mm, $4 m, $5 dd, $6 d
	'[yyyy]-mm-dd' => '[yyyy]-mm-dd', // hint for date format - use language equivalents for day, month and year shortcuts
	'HH:MM:SS' => 'HH:MM:SS', // hint for time format - use language equivalents for hour, minute and second shortcuts
	'now' => 'now',
	'yes' => 'yes',
	'no' => 'no',

	'File exists.' => 'Berkas sudah ada.', // general SQLite error in create, drop or rename database
	'Please use one of the extensions %s.' => 'Harap gunakan salah satu ekstensi %s.',

	// PostgreSQL and MS SQL schema support
	'Alter schema' => 'Ubah skema',
	'Create schema' => 'Buat skema',
	'Schema has been dropped.' => 'Skema berhasil dihapus.',
	'Schema has been created.' => 'Skema berhasil dibuat.',
	'Schema has been altered.' => 'Skema berhasil diubah.',
	'Schema' => 'Skema',
	'Invalid schema.' => 'Skema tidak sah.',

	// PostgreSQL sequences support
	'Sequences' => 'Deret',
	'Create sequence' => 'Buat deret',
	'Sequence has been dropped.' => 'Deret berhasil dihapus.',
	'Sequence has been created.' => 'Deret berhasil dibuat.',
	'Sequence has been altered.' => 'Deret berhasil diubah.',
	'Alter sequence' => 'Ubah deret',

	// PostgreSQL user-defined types support
	'User types' => 'Jenis pengguna',
	'Create type' => 'Buat jenis',
	'Type has been dropped.' => 'Jenis berhasil dihapus.',
	'Type has been created.' => 'Jenis berhasil dibuat.',
	'Alter type' => 'Ubah jenis',
	'Check has been dropped.' => 'Pemeriksaan berhasil dihapus.', // Claude Fable 5
	'Check has been altered.' => 'Pemeriksaan berhasil diubah.', // Claude Fable 5
	'Check has been created.' => 'Pemeriksaan berhasil dibuat.', // Claude Fable 5
	'Alter check' => 'Ubah pemeriksaan', // Claude Fable 5
	'Create check' => 'Buat pemeriksaan', // Claude Fable 5
	'Drop %s?' => 'Hapus %s?', // Claude Fable 5
	'Vacuum' => 'Bersihkan', // Claude Fable 5
	'Selected' => 'Terpilih', // Claude Fable 5
	'overwrite' => 'timpa', // Claude Fable 5
	'DB' => 'DB', // Claude Fable 5
	'Algorithm' => 'Algoritme', // Claude Fable 5
	'Columns' => 'Kolom', // Claude Fable 5
	'Condition' => 'Kondisi', // Claude Fable 5
	'Ctrl+click on a value to modify it.' => 'Ctrl+klik pada nilai untuk mengubahnya.', // Claude Fable 5
	'File must be in UTF-8 encoding.' => 'Berkas harus dalam pengodean UTF-8.', // Claude Fable 5
	'Modify' => 'Ubah', // Claude Fable 5
	'Load more data' => 'Muat lebih banyak data', // Claude Fable 5
	'Loading' => 'Memuat', // Claude Fable 5
	'%s queries are not supported.' => 'Kueri %s tidak didukung.', // Claude Fable 5
	'Warnings' => 'Peringatan', // Claude Fable 5
	'%d / ' => '%d / ', // Claude Fable 5
	'Limit rows' => 'Batas baris', // Claude Fable 5
	'Inherits from' => 'Mewarisi dari', // Claude Fable 5
	'Checks' => 'Pemeriksaan', // Claude Fable 5
	'Inherited by' => 'Diwarisi oleh', // Claude Fable 5
	'hostname[:port] or :socket' => 'hostname[:port] atau :socket', // Claude Fable 5
	'Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.' => 'Adminer tidak mendukung akses basis data tanpa sandi, <a href="https://www.adminer.org/en/password/"%s>informasi lebih lanjut</a>.', // Claude Fable 5
	'Default value' => 'Nilai bawaan', // Claude Fable 5
	'Too many unsuccessful logins, try again in %d minute(s).' => 'Terlalu banyak upaya masuk yang gagal, coba lagi dalam %d menit.', // Claude Fable 5
	'Thanks for using Adminer, consider <a href="https://www.adminer.org/en/donation/">donating</a>.' => 'Terima kasih telah menggunakan Adminer, pertimbangkan untuk <a href="https://www.adminer.org/en/donation/">berdonasi</a>.', // Claude Fable 5
	'Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> %s method to make it permanent.' => 'Sandi utama kedaluwarsa. <a href="https://www.adminer.org/en/extension/"%s>Implementasikan</a> metode %s agar permanen.', // Claude Fable 5
	'The action will be performed after successful login with the same credentials.' => 'Tindakan akan dilakukan setelah berhasil masuk dengan kredensial yang sama.', // Claude Fable 5
	'Invalid server.' => 'Server tidak valid.', // Claude Fable 5
	'Connecting to privileged ports is not allowed.' => 'Koneksi ke port istimewa tidak diizinkan.', // Claude Fable 5
	'There is a space in the input password which might be the cause.' => 'Ada spasi pada sandi yang dimasukkan yang mungkin menjadi penyebabnya.', // Claude Fable 5
	'If you did not send this request from Adminer then close this page.' => 'Jika Anda tidak mengirim permintaan ini dari Adminer, tutup halaman ini.', // Claude Fable 5
	'You can upload a big SQL file via FTP and import it from server.' => 'Anda dapat mengunggah berkas SQL besar melalui FTP dan mengimpornya dari server.', // Claude Fable 5
	'Size' => 'Ukuran', // Claude Fable 5
	'Compute' => 'Hitung', // Claude Fable 5
	'Loaded plugins' => 'Plugin yang dimuat', // Claude Fable 5
	'screenshot' => 'tangkapan layar', // Claude Fable 5
	'You are offline.' => 'Anda sedang luring.', // Claude Fable 5
	'Increase %s.' => 'Naikkan %s.', // Claude Fable 5
	'You have no privileges to update this table.' => 'Anda tidak memiliki hak istimewa untuk memperbarui tabel ini.', // Claude Fable 5
	'Saving' => 'Menyimpan', // Claude Fable 5
	'Unknown error.' => 'Kesalahan tidak dikenal.', // Claude Fable 5
	'%s must <a%s>return an array</a>.' => '%s harus <a%s>mengembalikan array</a>.', // Claude Fable 5
	'<a%s>Configure</a> %s in %s.' => '<a%s>Konfigurasikan</a> %s di %s.', // Claude Fable 5
	'Disable %s or enable %s or %s extensions.' => 'Nonaktifkan ekstensi %s atau aktifkan ekstensi %s atau %s.', // Claude Fable 5
	'Database does not support password.' => 'Basis data tidak mendukung sandi.', // Claude Fable 5
);

// run `php ../../lang.php id` to update this file
