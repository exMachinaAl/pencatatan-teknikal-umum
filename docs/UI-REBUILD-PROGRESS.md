# Pengerjaan UI bertahap

Permintaan: bangun dan review per tahap. Dokumen ini adalah titik lanjut jika konteks percakapan terpotong, bukan pernyataan bahwa seluruh pekerjaan selesai.

## Batasan dan keputusan

- Proyek Laravel 10 / Filament 3 lokal; pertahankan stack, database MySQL Laragon, dan data pengguna.
- Visual flat klasik: krem, hijau tua, border tipis; tanpa gradient atau shadow dekoratif. Ikon mengikuti Filament, bukan gambar baru.
- Menu hamburger tertutup saat membuka halaman, termasuk desktop. Dashboard bukan kumpulan kartu angka.
- Detail tambahan dibuka saat diperlukan; data utama dan status tidak boleh tersembunyi. Nomor urut terlebih dahulu pada daftar.
- Target review viewport 360, 390, 768, 1024, 1440 px serta keyboard, tema terang/gelap, teks panjang, data kosong dan error.
- Jangan menjalankan migrate:fresh, seed, atau restore pada database utama untuk pengujian UI. PHPUnit wajib SQLite :memory:; guard di tests/CreatesApplication.php menghentikan koneksi lain. Restore kelak diuji pada MySQL terisolasi.
- Jangan menyunting vendor. Jangan publikasi ke layanan hosting untuk pekerjaan lokal ini.

## Tahapan

| Tahap | Cakupan | Status |
| --- | --- | --- |
| 1 | Fondasi warna, fokus/error, login Indonesia, hamburger, refresh Vite untuk Filament | Selesai dan direview |
| 2 | Dashboard: maksimal dua surat aktif, menu modul, tindak lanjut menuju filter yang sesuai | Selesai dan direview |
| 3 | Pengguna dan role: tabel/form, matriks permission, URL/action backend, dashboard per role | Selesai dan direview |
| 4 | Surat masuk/keluar: daftar, pencarian nomor asli, detail readonly, lampiran/disposisi, form | Selesai dan direview |
| 5 | Legalisir: daftar ringkas, detail terpisah, status dan input, import/CSV | Selesai dan direview |
| 6 | Audit detail, query/filter konsisten, refactor duplikasi yang perlu | Selesai dan direview |
| 7 | Backup/restore aman: service, validasi file, konfirmasi, safety backup, pengujian terisolasi | Belum |
| 8 | Review lintas modul mobile/desktop, aksesibilitas, regresi dan dokumentasi | Belum |

## Tahap 1 — perubahan

- Login khusus di app/Filament/Auth/Login.php, mewarisi autentikasi, pembatasan percobaan dan regenerasi sesi Filament.
- View login minimal dengan bantuan singkat; tidak menampilkan kredensial demo.
- Bahasa default Indonesia, dapat dioverride APP_LOCALE. Fallback bahasa Inggris tetap tersedia.
- CSS border/fokus/error eksplisit agar penghapusan shadow tidak menghilangkan umpan balik validasi. Hover sidebar kontras, tombol mobile lebih mudah disentuh, reduced motion.
- Sidebar tertutup di semua ukuran saat halaman dimuat; tidak memuat Alpine kedua kali.
- Vite memantau seluruh PHP aplikasi, config, migration/seeder, Blade, dan route; CSS/JS menggunakan HMR. Ini refresh browser, bukan restart database/semua service.
- Test login asli melalui Livewire untuk akun aktif, kata sandi salah, akun nonaktif dan input wajib.

## Temuan review yang belum diselesaikan

- Tabel beberapa modul masih lebar; global table-layout fixed bukan solusi final responsivitas.
- Surat masuk belum mencari nomor_surat asli yang hanya menjadi description kolom.
- Legalisir masih memakai detail modal, status form ada dalam section collapsed.
- Audit per-record belum ada, disposisi belum terobservasi. Periksa hak akses dan redaksi data sensitif.
- Backup UI mengklaim enkripsi/jadwal yang belum ada. Command mengabaikan sejumlah risiko proses dan path; tidak ada restore UI aman. Jangan mengklaim fitur tersebut selesai.
- Laravel 10 yang masih dipakai memiliki 4 advisory pada audit Composer per 16 Oktober 2026. Upgrade framework ke Laravel 12 perlu menjadi tahap teknis tersendiri; jangan menonaktifkan audit Composer secara permanen.
- Verifikasi browser tahap 1–2 hanya mencakup login/dashboard dan smoke test tautan surat yang tersedia. Seluruh modul lain, input panjang, validasi upload, keyboard menyeluruh dan backup/restore belum diverifikasi end-to-end.

## Tahap 2 — perubahan

- Prioritas surat masuk hanya status baru/didisposisi; surat keluar hanya draf. Urutan urgensi, tanggal terbaru, lalu ID sebagai penentu saat tanggal sama. Soft-deleted tetap dikecualikan.
- Dua panel surat berada langsung setelah hero. Keadaan kosong menjelaskan tidak ada antrean aktif, bukan menyatakan database kosong; daftar arsip tetap bisa diakses.
- Hero dipendekkan setelah review screenshot mobile; tanggal menjadi baris sederhana, bukan kartu tinggi. Tanggal mengikuti Asia/Jakarta tanpa mengubah timezone penyimpanan aplikasi.
- Menu utama enam modul, utilitas dalam disclosure terpisah sesuai izin akses halaman/resource.
- Scope Legalisir::perluTindakLanjut(), GajiBerkala::perluTindakLanjut() dan Pegawai::calonKp() menyamakan query hitungan dashboard dan filter tujuan.
- Filter KGB menghitung kedua batas tanggal secara inklusif: satu bulan lalu sampai tiga bulan ke depan; yang sudah diserahkan dikecualikan dari tindak lanjut.
- Tes baru memverifikasi prioritas, arsip/selesai/deleted, empty state, akses tautan utilitas, hitungan dan hasil filter melalui URL daftar.

## Hasil review 15 Oktober 2026

- npm run build: berhasil (Vite 8.3.4). Tidak menambahkan bundel Alpine/Livewire kedua.
- php artisan test: 18 tes, 89 assertions lulus; memakai SQLite in-memory, tidak melakukan migrasi/seed/restore MySQL utama.
- PHPUnit menampilkan peringatan schema XML lama, bukan kegagalan tes; pembaruan schema dapat ditangani terpisah.
- Browser Edge headless melalui Playwright: login dan dashboard pada 360, 390, 768, 1024, 1440 px, tema terang serta gelap. Tidak ada overflow horizontal halaman pada data yang diperiksa.
- Hamburger bisa dibuka, tertutup kembali setelah reload, dan dua panel prioritas tampil. Tautan surat yang tersedia menghasilkan HTTP 200, tanpa error JavaScript pageerror selama smoke test.
- Screenshot ditinjau: login mobile, dashboard desktop/mobile, tema gelap. Hasil lokal ada di storage/app/ui-review/; tidak dipublikasikan.
- Browser login memakai akun demo yang sudah tersedia, lalu hanya membaca halaman. Tidak ada pembuatan/pengubahan data administrasi, restore, atau backup melalui browser.
- Script tests/Browser/workspace-review.cjs dapat dipakai ulang. Memerlukan Playwright yang sudah terpasang (PLAYWRIGHT_MODULE dapat menunjuk direktori paket), browser Edge, REVIEW_URL lokal, dan opsional REVIEW_EMAIL/REVIEW_PASSWORD dari environment untuk halaman terautentikasi. Tidak menyimpan kredensial/session state ke artefak.
- Port preview khusus 8017 hanya digunakan untuk review. Server Vite yang sudah dimiliki pengguna di 5173 dibiarkan berjalan.

## Verifikasi dan titik lanjut

- Jalankan npm run build dan php artisan test setelah tahap yang relevan. Periksa guard SQLite dahulu.
- Untuk pengembangan gunakan npm run dev:full (PHP harus tersedia di PATH); jika server Laravel sudah hidup, cukup npm run dev.
- Tulis hasil nyata dan perubahan status di sini setelah setiap review. Lanjut dari tahap pertama yang belum selesai, jangan mengulang migrasi atau setup database.
- Titik lanjut: **tahap 4, surat masuk/keluar**. Mulai dari nomor urut, pencarian `nomor_surat` asli, tabel mobile, detail readonly, disposisi/lampiran, endpoint file privat dan status final. Sesudahnya lanjut legalisir hingga backup/restore.

## Tahap 3 — pengguna, role, dan keamanan aksi

- Role operasional `staf` dipecah berdasarkan tanggung jawab, tetapi persuratan dan legalisir sengaja digabung sebagai `petugas_tu` sesuai keputusan pengguna. Matriks lengkap ada di `docs/ROLE-PERMISSIONS.md`.
- Database MySQL Laragon dimigrasikan pada 16 Oktober 2026: `staf` menjadi `petugas_tu`, `kepala` menjadi `kepala_sekolah`. Hasil: admin 1, kepala_sekolah 1, pegawai 1, petugas_tu 1. Tidak ada record dihapus.
- Role `kepala_tu`, `kepegawaian`, dan `auditor` sudah tersedia tetapi belum dipasang otomatis ke akun nyata. Penetapan dilakukan Admin lewat Kelola Pengguna.
- Semua resource memakai pemeriksaan backend terpusat untuk view/create/update/delete/restore. Administrator Sistem/Fasilitator TI dapat memantau persuratan dan legalisir secara baca-saja; petugas tidak dapat mengakses pengguna/pengaturan/backup.
- Dashboard memfilter query, kartu, menu, quick action, dan hitungan berdasarkan role. Ini mencegah kebocoran data melalui ringkasan.
- Cetak legalisir, import CSV, perubahan status dan ekspor diperiksa kembali pada backend. Aksi titik tiga melakukan reset tabel setelah berhasil sehingga label/status berubah tanpa reload manual.
- Record final/arsip tidak dapat diedit langsung. Force delete dinonaktifkan; bulk delete akun ditiadakan; admin tidak dapat menurunkan role, menonaktifkan, atau menghapus dirinya sendiri melalui UI.
- Kelola Pengguna memakai kolom No. terlebih dahulu; email menjadi informasi sekunder di mobile. Form dipisah menjadi identitas, peran/status, dan password; password minimal 8 karakter.
- Review otomatis final setelah revisi administrator: 29 tes/213 assertions lulus. Tes keamanan mencakup URL langsung 403, role read-only, dashboard sesuai role, endpoint cetak, final record, self-lockout admin, refresh aksi tabel, query pencarian semua halaman daftar, dan aksi Livewire yang dipaksa tidak mengubah data.
- Review Edge awal: admin dan Petugas TU pada 360/390/768/1024/1440, terang/gelap. Setelah revisi role 16 Oktober 2026, Admin dapat membaca surat/legalisir tetapi tetap mendapat 403 untuk create/edit/import/cetak; Petugas TU tetap mendapat 403 untuk pengguna. Menu titik tiga surat keluar dapat dibuka/ditutup tanpa error JavaScript. Screenshot awal tersimpan lokal dengan prefix `admin-` dan `petugas-tu-`.

## Revisi pemisahan Administrator Sistem dan Kepala TU — 19 Oktober 2026

- Label role `admin` ditegaskan menjadi `Administrator Sistem (Fasilitator TI)`, yaitu fungsi teknis Divisi IT di luar hierarki operasional TU. Kepala TU menjadi pimpinan tertinggi operasional TU.
- Admin mendapat permission `view` untuk Surat Masuk, Surat Keluar, dan Legalisir. Dashboard ikut menampilkan tiga ruang kerja tersebut, maksimal dua surat prioritas, dan antrean legalisir.
- Admin tetap tidak memiliki permission create/update/delete/restore/transition/import/export/print pada modul operasional. URL create/edit, halaman import, dan endpoint cetak legalisir diverifikasi menghasilkan 403.
- Admin tetap tidak dapat melihat Kepegawaian atau nominal gaji.
- Rekomendasi fitur lanjutan diprioritaskan di `docs/IDE-FITUR-PERSURATAN-LEGALISIR.md`.

## Stabilisasi refresh dan blank screen — 16 Oktober 2026

- Akar masalah utama adalah override `resources/views/vendor/filament/components/layout/base.blade.php` yang masih memakai kontrak Filament 3.2 lama. Override dihapus agar layout resmi selalu cocok dengan versi paket, termasuk render hook, body attributes, dark mode, dan event navigasi.
- Filament dinaikkan dari 3.2.0 ke 3.3.57 dalam major version yang sama. Asset Filament dipublikasikan ulang dan cache aplikasi dibersihkan.
- SPA panel dinonaktifkan eksplisit. Tautan antarhalaman memakai navigasi dokumen penuh agar kegagalan query tidak meninggalkan shell Livewire kosong.
- Client memiliki recovery satu kali untuk request Livewire 419/5xx atau timeout 20 detik. Jika kegagalan berulang dalam 30 detik, aplikasi berhenti melakukan reload otomatis dan menampilkan tombol `Muat ulang halaman`, sehingga tidak terjadi reload loop.
- Browser Edge menguji Surat Masuk, Surat Keluar, dan Legalisir selama dua siklus: klik dari dashboard, query pencarian, clear pencarian, serta menu titik tiga. Simulasi HTTP 500 pada query juga pulih otomatis dan halaman kembali ter-render.
- Script regresi tersedia di `tests/Browser/navigation-reliability.cjs`; query seluruh halaman daftar diuji di `tests/Feature/NavigationReliabilityTest.php`.

## Workflow dokumen dan layanan — 16 Oktober 2026

- Pencarian Terpadu mencari nomor agenda/surat, pengirim, tujuan, perihal, nomor antrean, dan nama pemohon, tetapi hanya pada modul yang diizinkan untuk role aktif.
- Detail surat/legalisir memakai halaman penuh dan menambahkan timeline aktivitas per berkas, lampiran privat beserta versi aktif/arsip, serta log setiap unduhan.
- Disposisi memiliki akun penanggung jawab, prioritas, tenggat, waktu selesai, scope terlambat, dan indikator perhatian pada dashboard bila memang ada yang terlambat.
- Surat keluar dapat dihubungkan ke satu atau lebih surat masuk sebagai balasan. Pengiriman mencatat metode, resi/referensi, waktu kirim/terima, penerima, dan bukti privat.
- Legalisir otomatis memperoleh nomor antrean harian dan token acak. Halaman publik/QR hanya menampilkan nomor layanan, status, jenis/jumlah dokumen, dan jadwal; nama, kontak, serta nomor dokumen tidak ditampilkan.
- Transisi “Siap Diambil” mewajibkan tiga checklist verifikasi serta jadwal pengambilan. Transisi “Sudah Diambil” mencatat pengambil, hubungan, waktu, dan bukti serah-terima.
- Email/WhatsApp masuk ke `notification_outbox`. Email diproses eksplisit melalui `php artisan notifications:process`; WhatsApp berstatus `menunggu_provider` sampai provider dan kredensial dikonfigurasi. Antrean bisa dipantau lewat menu Layanan Publik.
- Endroid QR Code dikunci ke 5.1 karena proyek memakai PHP 8.2; cabang 6 terbaru memerlukan PHP 8.4.
- Sebelum migrasi MySQL dibuat backup `storage/app/backups/backup-tata_usaha_db-2026-10-16_08-01-20.sql` (40.599 byte). Migrasi batch 3 selesai; jumlah data tetap users 4, surat masuk 2, surat keluar 3, legalisir 2, dan kedua legalisir lama memperoleh token.
- PHPUnit: 34 tes/240 assertions lulus. Browser Edge: tiga daftar diuji dua siklus termasuk simulasi Livewire 500; pencarian, detail workflow, pelacakan publik, QR, dan viewport mobile 390 px juga lulus tanpa overflow horizontal atau error JavaScript.
- Build produksi Vite 8.3.4 berhasil. Screenshot review lokal: `storage/app/ui-workflow-preview.png` dan `storage/app/ui-workflow-mobile-preview.png`.
