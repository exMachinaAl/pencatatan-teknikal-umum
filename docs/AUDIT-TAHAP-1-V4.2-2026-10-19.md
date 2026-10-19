# Audit Tahap 1 — V4.2 Security & Stabilization

Tanggal pemeriksaan: 19 Oktober 2026

## Ruang lingkup tahap ini

Tahap pertama memeriksa baseline aplikasi, matriks role, Laravel Policy, akses halaman Filament, aksi kustom, endpoint dokumen privat, pertukaran data, dan pengelolaan file backup. Perubahan dibatasi pada keamanan dan konsistensi otorisasi; tidak ada migrasi database dan tidak ada perubahan data produksi.

Repository sudah memiliki banyak modifikasi lokal sebelum audit dimulai. Seluruh perubahan tersebut dipertahankan dan tidak ada reset atau overwrite paksa.

## Baseline aktual

- Laravel 10.50.3, Filament 3.3.57, PHP 8.2.34.
- Migrasi database aplikasi berstatus `Ran` sampai migrasi workflow tanggal 16 Oktober 2026.
- Baseline test sebelum perbaikan: 34 test lulus dengan 240 assertion.
- `.env` lokal memakai MySQL, sedangkan test otomatis dipaksa memakai SQLite in-memory.

## Bug yang diperbaiki

1. Laravel Policy masih memakai matriks empat role lama (`admin`, `kepala`, `staf`, `pegawai`), sedangkan Resource Filament memakai tujuh role baru. Keputusan Gate dapat berbeda dengan keputusan UI/Resource.
2. Policy belum didaftarkan secara eksplisit dan nama modul Policy berbeda dengan sumber izin utama pada model `User`.
3. Policy masih mengizinkan update record final, sementara Resource Filament menguncinya.
4. Halaman Ringkasan Hak Akses menampilkan role lama dan menolak `kepala_tu`/`auditor` yang memiliki akses audit.
5. Halaman export/import administrasi dan layanan exchange kepegawaian masih memeriksa role lama. Akibatnya role baru yang sah ditolak, sementara Admin dapat memperoleh jalur pertukaran data yang tidak sesuai matriks baru.
6. Folder upload pertukaran data administrasi dipakai bersama. Sekarang upload dipisahkan per ID pengguna dan path diverifikasi tetap di folder privat milik pengguna tersebut.
7. Aksi download dan delete backup menerima nama file dari Livewire tanpa pembatasan path. Sekarang hanya nama backup SQL yang valid dan file nyata di dalam `storage/app/backups` yang diterima.
8. Middleware autentikasi aplikasi mengarahkan tamu ke route `login` yang tidak ada. Endpoint privat kini mengarah ke login Filament.
9. Aksi antre ulang notifikasi hanya memiliki pemeriksaan deklaratif. Closure aksi sekarang juga melakukan pemeriksaan izin backend.
10. Policy pengguna dapat mengizinkan Admin menghapus akun sendiri walaupun Resource menolaknya. Policy sekarang ikut menolak.
11. UI backup mengklaim file terenkripsi, padahal implementasi saat ini menghasilkan SQL biasa. Klaim tersebut dihapus sampai enkripsi benar-benar diimplementasikan pada V4.5.
12. Keterangan privasi KGB menyebut Admin dapat melihat nominal, tidak sesuai matriks aktual. Teks sudah diselaraskan dengan akses Kepala TU dan Petugas Kepegawaian.

## Klasifikasi modul saat ini

| Modul | Status | Catatan audit |
| --- | --- | --- |
| Dashboard operasional | Tersedia | Ringkasan disaring berdasarkan izin role dan sudah memiliki feature test. |
| Login, pengguna, dan role | Tersedia | Tujuh role aktif; alias role lama tetap didukung selama migrasi bertahap. |
| Data Pegawai | Tersedia | CRUD, filter, dan export tersedia untuk role kepegawaian. |
| Kenaikan Pangkat | Tersedia | CRUD, perubahan status, dan sinkronisasi dasar tersedia. Integritas transaksi ditinjau lagi pada V4.3. |
| Kenaikan Gaji Berkala | Tersedia | Nominal dibatasi untuk Kepala TU/Petugas Kepegawaian; pengujian privasi tersedia. |
| Surat Masuk dan Disposisi | Tersedia | CRUD, disposisi, deadline, arsip, dan lampiran privat tersedia. Approval independen belum tersedia. |
| Surat Keluar | Tersedia | CRUD, status kirim/arsip, bukti kirim, relasi surat, dan lampiran privat tersedia. Penomoran concurrency ditinjau lagi pada V4.3. |
| Legalisir | Tersedia | Workflow, PDF bukti, QR/token tracking, notifikasi outbox, dan lampiran privat tersedia. |
| Pencarian arsip | Tersedia | Pencarian terpadu untuk surat dan legalisir sudah ada serta mengikuti izin modul. |
| Google Form CSV | Sebagian selesai | Otorisasi tersedia, tetapi parser lama belum memiliki dry-run/transaksi/validasi tanggal sekuat layanan exchange baru. Dilanjutkan pada V4.4. |
| Export/import CSV/XLSX | Sebagian selesai | Preview, hash proof, validasi, dan transaksi tersedia pada layanan exchange; cakupan automated test khusus file masih perlu ditambah pada V4.4. |
| Audit log | Sebagian selesai | Perubahan model dan beberapa aksi tercatat; audit percobaan akses/login/export belum lengkap. |
| Backup database | Sebagian selesai | Pembuatan/list/download/delete tersedia dan traversal path sudah ditutup. Enkripsi, checksum, retensi, dan restore test belum tersedia (V4.5). |
| Approval surat bertingkat | Belum tersedia | Membutuhkan tabel approval dan aturan pemisahan pembuat/pemeriksa. |
| Notifikasi tenggat lanjutan/laporan periodik | Sebagian selesai | Deadline disposisi dan outbox legalisir tersedia; scheduler/notifikasi periodik belum lengkap. |

## Hasil validasi sesudah perbaikan

- PHP syntax lint: 142 file lulus.
- PHPUnit: 38 test lulus, 266 assertion.
- Test baru mencakup Laravel Gate, akses halaman exchange dengan role baru, penguncian record final, redirect endpoint privat, larangan hapus akun sendiri, policy notification outbox, dan penolakan traversal path backup.
- `composer validate`: lulus.
- `npm run build`: lulus dengan 59 module ditransformasi.
- `npm audit --omit=dev`: 0 vulnerability produksi.
- `composer audit --locked`: menemukan empat advisory pada `laravel/framework`, termasuk satu advisory severity high. Laravel 10.50.3 adalah versi terakhir seri 10 yang tersedia di constraint proyek, sehingga penyelesaian penuh memerlukan rencana upgrade framework terpisah dan regression test, bukan update minor tanpa pengujian.

PHPUnit juga memberi peringatan bahwa schema konfigurasi XML sudah deprecated. Ini bukan kegagalan test, tetapi perlu dibersihkan pada tahap pemeliharaan test berikutnya.

## Status V4.2 setelah tahap pertama

Konsistensi matriks role, Policy, Resource, halaman exchange, endpoint privat, dan path backup telah diperbaiki serta tervalidasi. V4.2 belum dinyatakan final karena masih perlu:

1. keputusan dan uji kompatibilitas upgrade dari Laravel 10 untuk menutup advisory dependency;
2. perluasan negative test per role untuk seluruh aksi kustom dan endpoint file;
3. audit lanjutan terhadap sanitasi/validasi impor Google Form lama;
4. pengamanan akun contoh pada seeder agar kredensial umum tidak dipakai saat deployment produksi;
5. pembuatan paket ZIP upgrader setelah seluruh pekerjaan V4.2 final, agar patch tidak berisi fase setengah selesai.

Tidak ada perintah destructive, `migrate:fresh`, restore database, atau perubahan data produksi yang dijalankan pada tahap ini.
