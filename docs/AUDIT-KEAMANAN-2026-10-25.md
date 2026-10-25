# Audit keamanan dan kesiapan multi-pengguna

> Status tindak lanjut: temuan A01-A09 sudah diperbaiki pada kode tanggal 25 Oktober 2026. Rincian implementasi, cakupan menu, verifikasi, dan pekerjaan operasional tersedia di [PEMBARUAN-KEAMANAN-2026-10-25.md](PEMBARUAN-KEAMANAN-2026-10-25.md). Bagian di bawah ini dipertahankan sebagai bukti kondisi sebelum perbaikan.

Tanggal: 25 Oktober 2026 (Asia/Jakarta). Revisi yang diperiksa: `adcf254`.

Kesimpulan: satu role sudah dapat digunakan oleh beberapa akun berbeda. Identitas pelaku disimpan berdasarkan akun. Namun aplikasi belum layak dinyatakan aman untuk operasional data nyata tanpa perbaikan akses file, password bawaan, benturan edit, dan konsistensi audit. Kelulusan uji fungsi customer belum mencakup kondisi tersebut.

## Ruang lingkup dan bukti

Pemeriksaan meliputi kode aplikasi, konfigurasi lokal yang relevan, hak akses, formulir Livewire, penyimpanan lampiran, ekspor/impor, penomoran, audit, backup, dan advisory dependensi. Tidak ada perubahan kode operasional, reset password, migrasi, upgrade paket, pengiriman pesan, atau pengujian ke server customer.

- `php artisan test --do-not-cache-result`: **72 tes, 594 assertion, lulus**.
- Enam probe diagnostik tambahan: **38 assertion**, seluruh skenario kelemahan berhasil direproduksi. Empat probe pertama dijalankan bersama, kemudian dua probe tambahan. Assertion sengaja memeriksa keberadaan masalah; hasil lulus pada probe bukan tanda aplikasi aman.
- Probe menggunakan SQLite `:memory:` dan penyimpanan palsu melalui `Storage::fake('local')`. Tidak mengunduh backup asli.
- `composer audit --locked --format=json`: lima entri advisory Laravel, mewakili empat GHSA berbeda; advisory CRLF muncul dari dua sumber.
- `npm audit --json`: nol advisory yang dilaporkan. Ini bukan pembuktian ketiadaan seluruh celah frontend.
- PHPUnit memberi peringatan skema XML lama; tidak menyebabkan kegagalan tes.

Belum dilakukan: uji beban beberapa proses pada MySQL, pemeriksaan jaringan/TLS/server sekolah, pemeriksaan izin Google Drive, pemulihan backup nyata ke server terpisah, atau audit kepatuhan instansi. Hasil konfigurasi lokal tidak membuktikan konfigurasi server customer sama.

## Temuan berurutan berdasarkan prioritas

### A01 — P0: referensi unggahan dapat membuka file privat di luar kewenangan

**Terbukti melalui formulir dan endpoint unduh.** Akun `petugas_tu` yang tidak mempunyai izin backup dapat memasukkan referensi file dummy yang sudah ada pada disk `local` sebagai lampiran Surat Masuk. Observer mendaftarkannya sebagai lampiran surat, kemudian endpoint unduh mengizinkan akses berdasarkan modul surat. Isi file dummy berhasil diunduh dengan HTTP 200.

Prasyarat: pengguna mempunyai akses membuat surat dan mengetahui atau memperoleh path file yang ada. Uji ini membuktikan akses file di dalam disk `local`, bukan kemampuan membaca sembarang file sistem atau akses tanpa login.

Lokasi terkait:

- `app/Filament/Resources/SuratMasukResource.php:110`: FileUpload dengan disk `local`.
- `vendor/filament/forms/src/Components/BaseFileUpload.php:623`: validasi file berfokus pada objek unggahan sementara; state string yang menunjuk file lama diterima.
- `app/Observers/DocumentFileObserver.php:32`: path didaftarkan ulang berdasarkan entitas pemilik formulir.
- `app/Http/Controllers/PrivateDocumentController.php:61`: otorisasi berdasarkan modul, tanpa pemeriksaan asal/kepemilikan referensi file.
- `config/filesystems.php`: dokumen dan backup berada di bawah root `storage/app` yang sama.

Perbaikan: terima unggahan baru dari mekanisme unggahan yang sah; pada edit hanya izinkan file lama yang memang melekat pada record yang sedang diedit. Cek kembali hak akses terhadap record dan file di server. Pisahkan root/disk backup dari lampiran, dan validasi lokasi file saat unduh sebagai lapisan tambahan. Pembatasan direktori saja belum cukup untuk mencegah referensi file milik record lain.

Kriteria lulus: referensi backup, file modul lain, dan file record yang tidak berhak diakses ditolak; unggahan baru serta mempertahankan lampiran sah tetap bekerja. Terapkan pola tersebut ke seluruh komponen unggahan, bukan Surat Masuk saja.

### A02 — P1: seeder mengembalikan password akun lama ke password bersama

**Terbukti di kode dan tes bawaan.** `database/seeders/UserSeeder.php:78` menetapkan ulang password akun bawaan menjadi `password` pada setiap eksekusi. Ini sesuai permintaan pengembangan sebelumnya, tetapi berbahaya jika seeder dijalankan saat deployment data nyata. Mengganti password sekali tidak menyelesaikan masalah jika proses deployment mengembalikannya.

Perbaikan: pisahkan seeder demo dari inisialisasi produksi; jangan reset password akun yang sudah ada; gunakan akun per orang, password awal unik, dan kewajiban mengganti password. Pertimbangkan MFA untuk administrator dan pimpinan. Kode saat ini belum menyediakan alur mandiri penggantian password/MFA di panel.

Kriteria lulus: menjalankan seeder produksi berulang tidak mengubah password lama atau mengaktifkan akun demo; password demo ditolak pada akun operasional. Audit ini tidak memeriksa hash password akun customer.

### A03 — P1: edit bersamaan menyebabkan perubahan pengguna lain hilang

**Terbukti melalui dua akun dengan role sama.** Akun B membuka formulir Surat Masuk. Akun A mengubah pengirim dan menyimpan. B hanya mengubah lokasi arsip pada formulir lamanya lalu menyimpan. Nilai pengirim kembali ke nilai awal tanpa peringatan. Pembuat asli tetap akun A dan aktivitas kedua akun tercatat, tetapi data A sudah tertimpa.

Lokasi: `app/Filament/Resources/SuratMasukResource/Pages/EditSuratMasuk.php:9`, yang memakai alur edit bawaan tanpa pembandingan versi. Edit Legalisir juga memakai alur bawaan. Edit KP/KGB memiliki penguncian ketika menyimpan, tetapi tidak membandingkan versi data saat formulir pertama dibuka.

Perbaikan: tambahkan nomor versi record dan simpan dengan syarat versi masih sama. Jika sudah berubah, tampilkan bahwa pengguna lain telah memperbarui data dan minta pengguna memuat ulang atau menggabungkan perubahan. Proses perubahan status perlu transaksi dan pengecekan status terbaru di dalam transaksi. Penguncian database saat submit saja tidak mendeteksi formulir yang sudah lama terbuka.

Kriteria lulus: simulasi dua browser tidak menimpa data diam-diam; penolakan konflik menyebut record dan perubahan yang perlu diperiksa. Lanjutkan dengan uji paralel pada MySQL untuk penomoran, impor duplikat, dan perubahan status.

### A04 — P1: perubahan data dapat tersimpan tanpa jejak audit ketika audit gagal

**Terbukti dengan kegagalan audit yang disimulasikan.** Saat penyimpanan edit Surat Masuk, probe memicu exception pada pembuatan audit log. Operasi melaporkan kegagalan, tetapi pengirim pada database sudah berubah dan tidak ada log update.

Lokasi: `app/Observers/AuditObserver.php:34`, `app/Filament/Resources/SuratMasukResource/Pages/EditSuratMasuk.php:9`, dan `app/Providers/Filament/AdminPanelProvider.php`. Transaksi database panel tidak diaktifkan; nilai bawaan Filament adalah false. Beberapa alur create/KP/KGB sudah menggunakan transaksi secara eksplisit, sehingga temuan ini tidak berarti semua alur tidak memiliki transaksi.

Perbaikan: simpan perubahan bisnis dan audit dalam transaksi database yang sama. Tindakan lanjutan di luar database perlu ditangani setelah commit atau melalui outbox yang konsisten. Penulisan file fisik perlu mekanisme penanganan kegagalan tersendiri karena rollback SQL tidak mengembalikan file.

Kelengkapan audit juga perlu diperbaiki: belum ditemukan listener aplikasi untuk login berhasil/gagal/logout; unduh/hapus backup dan sejumlah jalur ekspor belum mencatat tindakan secara eksplisit. Sinkronisasi master Pegawai pada model KP/KGB memakai query update langsung yang melewati observer Pegawai. Log CRUD tetap bermanfaat, tetapi belum merekam seluruh peristiwa penting.

Kriteria lulus: kegagalan audit membatalkan perubahan bisnis; akun, waktu, record, tindakan, dan hasil tercatat; aktivitas sensitif bisa ditelusuri tanpa menyimpan password/token.

### A05 — P1: status Legalisir melewati aturan verifikasi

**Terbukti melalui formulir create.** Permohonan dapat disimpan langsung dengan `status=siap_diambil`, ketiga checklist false, dan `diverifikasi_oleh` kosong. Tombol khusus meminta checklist, tetapi pilihan status pada formulir tidak memakai aturan yang sama.

Lokasi: `app/Filament/Resources/LegalisirResource.php`, bagian Status Pemrosesan & Pengambilan dan aksi `tandai_siap`; `app/Models/Legalisir.php`, validasi status hanya memeriksa nilai yang diizinkan.

Perbaikan: satukan aturan perpindahan status pada layanan/model di server, dipakai oleh formulir, tombol, dan impor. Simpan pelaku/waktu verifikasi dari akun login; cegah status final tanpa syarat yang diwajibkan sekolah. Jika checklist memang opsional menurut SOP, sesuaikan seluruh jalur agar tidak saling bertentangan.

Kriteria lulus: tidak ada jalur untuk menandai siap tanpa verifikasi yang diwajibkan, dan dua proses tidak dapat melompati status secara bertentangan.

### A06 — P1: framework di luar dukungan keamanan dan advisory belum selesai

`composer.lock` memuat Laravel **10.50.3**, Filament **3.3.57**, Livewire **3.8.10**. Laravel 10 berakhir dukungan keamanan pada **4 Februari 2025** menurut [kebijakan resmi Laravel](https://laravel.com/docs/10.x/releases#support-policy).

Hasil audit dependensi harus dipisahkan dari eksploitabilitas aplikasi:

| Advisory unik | Penilaian untuk aplikasi ini |
|---|---|
| [GHSA-f9x3-7vq9-cph6](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6), TokenGuard bypass | Rentang versi ditandai terdampak. `config/auth.php` memakai session guard dan route API memakai Sanctum; jalur TokenGuard yang disyaratkan advisory tidak ditemukan pada konfigurasi yang ditinjau. Tidak terbukti bypass login aplikasi ini. |
| [GHSA-jh5r-qr3c-85q8](https://github.com/laravel/framework/security/advisories/GHSA-jh5r-qr3c-85q8), debug-page XSS | Advisory bergantung pada debug aktif dan interaksi pengguna. Debug aktif di `.env` lokal; eksploit browser tidak dijalankan. |
| [GHSA-crmm-hgp2-wgrp](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp), temporary signed URL | Ditandai berdasarkan versi. Belum dibuktikan pada route aplikasi. Terpisah dari celah referensi lampiran A01. |
| [GHSA-5vg9-5847-vvmq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq), CRLF email | Ada mitigasi validasi email di aplikasi serta tes outbox yang lulus, tetapi framework masih ditandai terdampak. |

Perbaikan: rencanakan upgrade Laravel ke cabang yang masih didukung dan patch terbaru, bersama kompatibilitas PHP/Filament/Livewire. Jangan menganggap `composer update` pada batas `^10.0` cukup untuk keluar dari cabang lama. Periksa ulang advisory saat implementasi; versi minimum per advisory tidak menjamin bebas advisory lain.

### A07 — P2: proteksi formula CSV tidak konsisten antar menu

**Terbukti pada ekspor bulk Surat Masuk.** Pengirim dummy `=1+1` keluar sebagai isi sel mentah `=1+1`. Tidak ada eksekusi rumus atau koneksi eksternal yang dilakukan dalam probe.

`app/Services/TuDataExchange.php:505` sudah menangani awalan rumus, tetapi `app/Filament/Resources/SuratMasukResource.php:324` dan ekspor bulk sejumlah resource lain langsung memakai `fputcsv` tanpa perlindungan yang sama. Risiko muncul saat penerima membuka CSV dalam aplikasi spreadsheet; dampaknya bergantung pada aplikasi dan pengaturan spreadsheet. Lihat [penjelasan OWASP](https://owasp.org/www-community/attacks/CSV_Injection).

Perbaikan: semua ekspor memakai satu pembuat CSV yang sama; uji awalan `=`, `+`, `-`, `@`, whitespace/control characters, serta pelestarian nomor telepon/NIP. Jangan mengubah data master hanya demi menyamarkan formula pada ekspor.

### A08 — P2: username angka dapat mengambil prioritas pencarian NIP akun lain

**Terbukti melalui lookup login.** Satu akun dihubungkan ke pegawai bernomor NIP; akun berbeda diberi username yang sama persis dengan NIP tersebut. `User::findForLogin()` memilih akun berusername angka terlebih dahulu. Pemilik NIP asli tidak lagi diarahkan ke akunnya saat memakai NIP itu.

Lokasi: `app/Models/User.php:168`. Ini ambiguitas identitas/kemungkinan gangguan login, bukan bukti dapat masuk tanpa password. Pembuatan akun memerlukan administrator.

Perbaikan: tetapkan namespace username yang tidak bertabrakan dengan NIP, atau validasi keunikan identifier lintas username dan NIP pada create/update akun serta perubahan NIP. Konflik data lama perlu dideteksi sebelum aturan baru diberlakukan.

### A09 — P1 sebelum produksi: backup dan konfigurasi operasional belum cukup terverifikasi

Temuan inspeksi kode, bukan hasil restore/load test:

- `app/Console/Commands/BackupDatabaseCommand.php:31`: nama backup hanya presisi detik; dua proses pada detik sama dapat menulis target sama.
- Baris 38–41: kredensial dirangkai ke shell command. Password berada pada argumen proses dan karakter khusus berisiko mengganggu perintah. Tidak ditemukan bukti input pengguna biasa mengontrol konfigurasi tersebut; jangan menyebutnya RCE publik.
- Baris 46–64: fallback membaca tabel satu per satu tanpa snapshot transaksi konsisten, dan menampung hasil di memori. Data antar tabel dapat berbeda waktu saat pengguna lain menulis.
- Backup berupa SQL database, tidak mencakup berkas lampiran. Tidak ada enkripsi backup atau salinan luar mesin yang diimplementasikan di perintah ini.
- `app/Console/Kernel.php:13` tidak menjadwalkan backup. Penjadwalan OS di luar repository belum diperiksa.
- `.env` lokal: `APP_ENV=local`, `APP_DEBUG=true`, URL HTTP. Cookie secure tidak diaktifkan secara eksplisit. Ini konfigurasi pengembangan; konfigurasi customer belum diketahui.

Perbaikan: proses backup tunggal dengan nama unik, snapshot konsisten, kredensial melalui mekanisme aman, pengecekan hasil, enkripsi, retensi, dan salinan di media/lokasi berbeda. Cadangkan database beserta lampiran dan simpan kunci aplikasi secara aman terpisah untuk memulihkan data terenkripsi. Latih restore ke server kosong. Operasional memakai konfigurasi production, HTTPS, cookie secure, akun DB berizin minimum, serta web server dengan document root `public`.

## Rancangan multi-akun yang disarankan

Satu orang memakai satu akun, misalnya `siti.tu` dan `andi.tu`, dengan role yang sama `petugas_tu`. Role menentukan tindakan yang diizinkan, sedangkan `users.id` menentukan pelaku. Jangan memakai akun bersama `staf_tu` untuk beberapa orang jika perlu pertanggungjawaban individual.

Pola ini sudah didukung schema: role tidak unik, username/email unik, dan relasi akun ke pegawai unik. `created_by` pada surat/KP/KGB dan `petugas_id` pada Legalisir berasal dari akun login pada jalur create yang diperiksa. `AuditLog::record()` menyimpan `user_id`, nama, IP, waktu, dan perubahan. Uji dua akun membuktikan pelaku edit dapat dibedakan.

Nomor otomatis menggunakan transaksi, `lockForUpdate`, dan sequence; database juga memiliki unique constraint untuk nomor penting dan integrity key KP/KGB. Ini fondasi yang baik untuk benturan pembuatan data, tetapi bukan perlindungan terhadap formulir lama atau bukti lulus uji paralel MySQL.

Saat ini akses berbasis modul, bukan kepemilikan per record. Dua petugas TU dapat melihat/mengedit data operasional modul yang sama; ini wajar jika arsip dikelola bersama. Jangan otomatis memfilter semua data berdasarkan `created_by` karena dapat memutus kerja bersama. Jika perlu pembagian tugas, tambahkan penanggung jawab/disposisi; jika ada dokumen terbatas, tambahkan aturan akses per record yang diperiksa juga saat unduh dan ekspor.

Administrator juga memiliki akses backup seluruh database dan audit yang menyimpan isi perubahan data. Artinya larangan membuka menu kepegawaian tidak menjamin administrator tidak dapat membaca data kepegawaian melalui backup/audit. Tentukan kepercayaan administrator secara eksplisit; jika dibatasi, perlu penyamaran isi log, pemisahan kewenangan backup/kunci, dan SOP akses khusus.

Tambahkan tampilan pembuat/pengubah terakhir serta filter pelaku. Sebaiknya audit juga menyimpan snapshot username dan role saat tindakan dilakukan, karena nama/role akun dapat berubah kemudian.

## Tata kelola sekolah dan rencana penerapan

Untuk data pegawai dan pemohon, kebutuhan pengamanan tidak hilang hanya karena server berada di jaringan lokal. UU PDP Pasal 35–39 mengatur pengamanan dan kerahasiaan pemrosesan; PP 71/2019 Pasal 22–24 mengatur jejak audit dan pengamanan sistem. Gunakan ini sebagai dasar penetapan kontrol bersama sekolah/dinas, bukan klaim bahwa audit kode ini merupakan sertifikasi kepatuhan. [UU 27/2022](https://jdih.komdigi.go.id/produk_hukum/view/id/832/t/crc32/), [PP 71/2019](https://jdih.komdigi.go.id/produk_hukum/view/id/695/t/peraturan%2Bpemerintah%2Bnomor%2B71%2Btahun%2B2019).

Tentukan penanggung jawab aplikasi/data, dasar dan tujuan pemrosesan, daftar akses, masa retensi sesuai aturan kearsipan instansi, prosedur pencabutan akun, dan penanganan insiden. Siapkan alur pelaporan kegagalan pelindungan data mengikuti Pasal 46 UU PDP, termasuk tenggat pemberitahuan 3 x 24 jam dalam ketentuan tersebut; penetapan penerapannya dikoordinasikan dengan penanggung jawab instansi. Jangan menetapkan retensi atau menghapus arsip negara secara sembarang.

Urutan implementasi yang disarankan:

1. Tutup celah referensi lampiran A01 dan pastikan seluruh jalur unduh/upload memeriksa kewenangan yang sama.
2. Pisahkan akun demo dan produksi, hentikan reset password oleh seeder, terapkan konfigurasi operasional, dan upgrade dependensi yang didukung.
3. Tambahkan deteksi konflik edit, transaksi data-audit, dan aturan status terpusat; uji dua akun serta kegagalan parsial.
4. Satukan keamanan ekspor, perbaiki konflik username/NIP, dan lengkapi pencatatan aktivitas sensitif.
5. Uji backup/restore dan beban MySQL pada lingkungan terpisah; verifikasi hak akses file, HTTPS, jaringan, dan prosedur operasional bersama sekolah.

Kriteria penerimaan akhir: enam skenario kelemahan di atas tidak lagi terjadi, tes fungsi tetap lulus, akun yang dicabut aksesnya tidak dapat melanjutkan operasi, penomoran/impor paralel tidak menghasilkan duplikat, dan pemulihan database beserta lampiran berhasil pada lingkungan terpisah.
