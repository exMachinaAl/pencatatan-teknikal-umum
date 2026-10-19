# Audit Tahap 2 — V4.2 Security & Stabilization

Tanggal pemeriksaan: 19 Oktober 2026  
Metode penerapan: perubahan langsung pada direktori proyek, tanpa ZIP atau upgrader.

## Ruang lingkup

Tahap ini melanjutkan V4.2 dengan fokus pada importer CSV lama, keamanan akun hasil seeder, tujuan notifikasi, dan pemeriksaan dependency framework.

## Temuan dan perbaikan

### 1. Import Google Form/CSV

Sebelum:

- folder unggahan sementara dipakai bersama;
- path file dari state komponen langsung dipercaya;
- isi file dapat berubah antara preview dan proses;
- parsing tanggal longgar dan data baris belum dibatasi;
- error internal dapat ditampilkan kepada pengguna.

Sesudah:

- file disimpan privat pada folder sementara per pengguna;
- real path wajib tetap berada di folder pengguna dan berekstensi `.csv`;
- ukuran dibatasi 5 MB dan data dibatasi 1.000 baris;
- hash file serta HMAC mengikat pengguna, modul, path, dan isi preview;
- header kosong/duplikat, tanggal tidak valid, URL, enum, panjang teks, karakter kontrol, dan jumlah lembar divalidasi;
- proses per baris memakai transaksi database dan pemeriksaan duplikat termasuk data soft-delete;
- file sementara dihapus setelah proses/reset;
- detail error sistem dicatat ke log, sedangkan pengguna memperoleh pesan aman.

### 2. Seeder akun awal

Sebelum:

- kredensial bawaan dapat ditulis ulang ketika seeder dijalankan kembali;
- password yang sama didokumentasikan tanpa pembatasan lingkungan.

Sesudah:

- akun yang sudah ada, termasuk soft-deleted, tidak diubah oleh seeder;
- password production wajib berasal dari `TU_*_INITIAL_PASSWORD` dan minimal 12 karakter;
- nilai `password` hanya menjadi fallback pada local/testing;
- dokumentasi menegaskan bahwa kredensial bawaan hanya untuk local/demo.

### 3. Notifikasi email dan WhatsApp

Sebelum:

- tujuan outbox diteruskan ke pengirim tanpa validasi terakhir;
- aturan email framework yang terpasang terdampak advisory CRLF injection.

Sesudah:

- tujuan divalidasi ketika antrean dibuat dan diperiksa kembali sebelum dikirim;
- karakter kontrol, CR/LF, alamat email tidak valid, nomor WhatsApp tidak valid, kanal asing, dan nilai terlalu panjang ditolak;
- field email Filament mempunyai aturan eksplisit penolakan CR/LF;
- target yang tidak valid ditandai `gagal` tanpa mencoba mengirim;
- pesan error transport yang mungkin sensitif hanya tersedia pada log aplikasi.

Mitigasi ini melindungi alur email yang saat ini digunakan aplikasi, tetapi tidak menggantikan kebutuhan upgrade framework.

### 4. Dependency framework

`composer audit --locked --no-dev` masih melaporkan empat advisory pada `laravel/framework` 10.50.3, termasuk advisory CRLF email. Laravel 10 tidak menyediakan rilis aman untuk seluruh advisory tersebut. Upgrade langsung belum dilakukan karena `composer why-not laravel/framework 12.70.0` menunjukkan blocker pada constraint root serta versi `laravel/prompts`, `laravel/sanctum`, `nunomaduro/collision`, Carbon, Symfony, dan Termwind.

Upgrade framework perlu menjadi perubahan tersendiri dengan pembaruan paket, penyesuaian kode, migrasi konfigurasi PHPUnit, dan regresi ulang. Memaksakannya bersama hardening V4.2 akan memperbesar risiko kerusakan aplikasi.

`npm audit --omit=dev` tidak menemukan vulnerability pada dependency production frontend.

## File utama yang diperbarui

- `app/Filament/Pages/GoogleFormImport.php`
- `app/Models/NotificationOutbox.php`
- `app/Models/Legalisir.php`
- `app/Console/Commands/ProcessNotificationOutbox.php`
- `app/Filament/Resources/LegalisirResource.php`
- `app/Filament/Resources/UserResource.php`
- `database/seeders/UserSeeder.php`
- `config/app.php`
- `.env.example`
- `README.md`
- `tests/Feature/GoogleFormImportSecurityTest.php`
- `tests/Feature/UserSeederTest.php`
- `tests/Feature/DocumentWorkflowTest.php`

## Hasil validasi

- `php artisan test`: **44 test lulus, 289 assertion**.
- lint PHP pada `app`, `config`, `database`, `routes`, dan `tests`: **lulus**.
- `composer validate --no-check-publish`: **lulus**.
- `npm run build`: **lulus**.
- `npm audit --omit=dev`: **0 vulnerability**.

PHPUnit masih menampilkan peringatan bahwa schema konfigurasi XML lama telah deprecated. Peringatan ini tidak menggagalkan test dan dapat dibereskan bersamaan dengan pekerjaan upgrade toolchain.

## Status tahap

Hardening aplikasi V4.2 yang diperiksa pada tahap ini sudah diterapkan dan tervalidasi. Risiko dependency Laravel tetap terbuka secara transparan; alur email aktif telah dimitigasi di tingkat aplikasi sambil menunggu upgrade framework terencana.

Langkah aman berikutnya adalah audit dan implementasi V4.3 Data Integrity, sedangkan upgrade Laravel dikerjakan sebagai jalur migrasi terpisah dengan baseline 44 test ini.
