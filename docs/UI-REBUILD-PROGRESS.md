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
| 3 | Pengguna: tabel ringkas/responsif, form profil/peran/password, review otorisasi | Berikutnya |
| 4 | Surat masuk/keluar: daftar, pencarian nomor asli, detail readonly, lampiran/disposisi, form | Belum |
| 5 | Legalisir: daftar ringkas, detail terpisah, status dan input, import/CSV | Belum |
| 6 | Audit detail, query/filter konsisten, refactor duplikasi yang perlu | Belum |
| 7 | Backup/restore aman: service, validasi file, konfirmasi, safety backup, pengujian terisolasi | Belum |
| 8 | Review lintas modul mobile/desktop, aksesibilitas, regresi dan dokumentasi | Belum |

## Tahap 1 — perubahan

- Login khusus di app/Filament/Auth/Login.php, mewarisi autentikasi, pembatasan percobaan dan regenerasi sesi Filament.
- View login minimal dengan bantuan singkat; tidak menampilkan kredensial demo.
- Bahasa default Indonesia, dapat dioverride APP_LOCALE. Fallback bahasa Inggris tetap tersedia.
- CSS border/fokus/error eksplisit agar penghapusan shadow tidak menghilangkan umpan balik validasi. Hover sidebar kontras, tombol mobile lebih mudah disentuh, reduced motion.
- Sidebar tertutup di semua ukuran saat halaman dimuat; tidak memuat Alpine kedua kali.
- Vite memantau perubahan app/Filament dan provider panel selain Blade; CSS/JS menggunakan HMR. Ini refresh browser, bukan restart database/semua service.
- Test login asli melalui Livewire untuk akun aktif, kata sandi salah, akun nonaktif dan input wajib.

## Temuan review yang belum diselesaikan

- Tabel beberapa modul masih lebar; global table-layout fixed bukan solusi final responsivitas.
- Surat masuk belum mencari nomor_surat asli yang hanya menjadi description kolom.
- Legalisir masih memakai detail modal, status form ada dalam section collapsed.
- Audit per-record belum ada, disposisi belum terobservasi. Periksa hak akses dan redaksi data sensitif.
- Backup UI mengklaim enkripsi/jadwal yang belum ada. Command mengabaikan sejumlah risiko proses dan path; tidak ada restore UI aman. Jangan mengklaim fitur tersebut selesai.
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
- Titik lanjut: **tahap 3, Kelola Pengguna**. Baca UserResource.php dan test HakAksesTest.php; rancang kolom responsif bernomor, section form jelas, lalu uji izin create/edit/delete melalui resource/actions (bukan hanya canViewAny). Sesudahnya lanjut surat/layanan hingga backup/restore, jangan menganggap tahap 1–2 menyelesaikan semuanya.
