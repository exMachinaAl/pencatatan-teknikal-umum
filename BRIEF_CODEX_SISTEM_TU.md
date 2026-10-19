# Implementasi & Modernisasi Sistem Tata Usaha Sekolah

Anda bertindak sebagai **Senior Full-Stack Laravel Developer, Software Architect, Security Engineer, QA Engineer, dan Release Engineer**.

Saya memiliki proyek Sistem Tata Usaha Sekolah menggunakan Laravel, Filament, PHP, dan MySQL (lingkungan Windows/Laragon).

Saya ingin Anda **mengaudit dan mengimplementasikan perbaikan secara langsung pada repository**, bukan hanya menjelaskan langkah atau memberikan potongan kode.

## A. Tujuan

Selesaikan bug, perkuat keamanan, tingkatkan kualitas UI/UX, dan sempurnakan fitur aplikasi tanpa merusak sistem serta database yang telah berjalan.

Sebelum mengubah kode, analisis repository yang tersedia. Jangan menganggap versi framework, struktur database, dan patch sebelumnya sama dengan referensi lama tanpa melakukan pemeriksaan.

## B. Modul Sistem

Cakupan modul meliputi:

- Dashboard dan statistik operasional.
- Autentikasi, manajemen pengguna, dan role Admin/Kepala/Staf/Pegawai.
- Administrasi pegawai.
- Kenaikan Pangkat (KP).
- Kenaikan Gaji Berkala (KGB).
- Surat Masuk, Disposisi, dan Surat Keluar.
- Legalisir dan arsip digital.
- Export–Import CSV/Excel.
- Pengaturan aplikasi, audit log, dan backup database.

Audit seluruh modul dan klasifikasikan fitur menjadi **tersedia, sebagian selesai, belum tersedia, atau mengalami bug**.

## C. Roadmap Implementasi

**V4.2 — Security & Stabilization**

Perbaiki seluruh otorisasi CRUD, Laravel Policy, akses berdasarkan role, aksi kustom Filament, keamanan dokumen PDF, impor Google Form, serta akses data KGB. Tambahkan automated tests untuk akses yang diizinkan dan ditolak.

**V4.3 — Data Integrity**

Pertahankan nomor agenda surat masuk sebagai identitas internal otomatis dan unik. Nomor surat keluar wajib diisi manual sesuai nomor resmi sekolah serta tetap divalidasi unik. Perbaiki transaksi database, sinkronisasi KP/KGB dengan data pegawai, validasi tanggal dan nominal, serta pengendalian data duplikat.

**V4.4 — Export & Import Finalization**

Implementasikan atau sempurnakan CSV/XLSX, template import, preview/dry-run, validasi per baris, deteksi duplikat, export berdasarkan filter, laporan hasil import, dan audit aktivitas.

**V4.5 — Backup & Restore**

Perbaiki backup SQL, keamanan penyimpanan, enkripsi yang benar, verifikasi checksum, retensi, serta pengujian restore menggunakan database sementara. Jangan memulihkan database produksi tanpa persetujuan.

**V5.0 — UI/UX Final**

Perbaiki dashboard, sidebar, tabel, toolbar, formulir, filter, dan responsivitas. Secara khusus, pastikan teks panjang pada kolom Keterangan tidak keluar dari panel dan tombol aksi tiga titik tidak terpotong di tepi tabel.

**V5.1 — Advanced Features**

Tambahkan secara bertahap approval surat, notifikasi tenggat, QR verifikasi, pencarian arsip lanjutan, activity timeline, laporan periodik, dan dashboard berdasarkan role.

## D. Metode Upgrade Wajib

Saya menggunakan metode **ZIP patch dengan file `.bat` yang cukup di-double-click**, tanpa perlu mengetik command.

Setiap paket upgrade harus menyediakan:

- `JALANKAN-CEK.bat`
- `JALANKAN-UPGRADE.bat`
- `JALANKAN-ROLLBACK.bat`
- `manifest.json`, payload perubahan, dokumentasi dan checksum SHA-256.

Folder upgrader ditempatkan sejajar dengan root proyek.

Simpan seluruh backup patch di `.trash/` dan riwayat serta mekanisme rollback di `.vhistory/`, keduanya berada **dalam root proyek utama**. Tidak boleh ada residu di folder induk.

Jangan menimpa modifikasi lokal secara paksa, menghapus database, menjalankan `migrate:fresh`, atau menyertakan `.env` dan rahasia lain ke ZIP.

Pastikan rollback tetap bisa dijalankan meskipun folder upgrader eksternal dihapus. Rollback file tidak boleh diasumsikan dapat membatalkan migrasi atau data database.

## E. Standar Kualitas

Setiap tahap harus memenuhi kriteria berikut:

1. Tidak merusak fitur lama.
2. Hak akses diverifikasi di backend.
3. Menggunakan transaksi dan validasi data yang sesuai.
4. Tidak mengubah database produksi secara destruktif.
5. Menjalankan unit/feature/integration tests yang relevan.
6. Mendokumentasikan file yang berubah dan hasil pengujian.
7. Menghasilkan ZIP patch yang dapat dipasang dan di-rollback.
8. Tidak mengklaim keberhasilan fitur jika baru menguji penyalinan file.

## F. Hasil yang Saya Minta

Untuk setiap fase, hasilkan kode yang benar-benar diimplementasikan, laporan bug sebelum/sesudah, hasil pengujian, daftar perubahan file, dan ZIP upgrader siap pakai.

Kerjakan berdasarkan kondisi aktual repository, bukan asumsi.

**Mulai dengan audit repository dan implementasi V4.2 terlebih dahulu. Setelah tahap ini tervalidasi, lanjutkan fase berikutnya sesuai roadmap, tanpa menggabungkan semua perubahan ke dalam satu patch berisiko besar.**

Gunakan file `BRIEF_CODEX_SISTEM_TU.md` yang saya lampirkan sebagai spesifikasi teknis lebih lengkap dan ikuti detail pengamanan serta acceptance criteria di dalamnya.
