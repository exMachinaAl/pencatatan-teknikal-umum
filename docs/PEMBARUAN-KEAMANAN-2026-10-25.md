# Pembaruan keamanan dan multi-pengguna

Tanggal penerapan: 25 Oktober 2026 (Asia/Jakarta).

Dokumen ini adalah tindak lanjut [audit keamanan](AUDIT-KEAMANAN-2026-10-25.md). Tanda **Diperbaiki** berarti kontrol sudah diterapkan dan diuji di aplikasi. Tanda **Operasional** berarti harus diselesaikan pada server/customer karena tidak dapat dipaksakan hanya melalui source code.

## Menu dan section yang ditandai

| Menu / section | Risiko yang ditemukan | Status dan perbaikan |
|---|---|---|
| Login | Username numerik dapat mengambil prioritas NIP; login/logout belum diaudit | **Diperbaiki**. Username numerik ditolak, username juga tidak boleh sama dengan NIP pegawai lain, dan login berhasil/gagal/logout mencatat snapshot akun, role, IP, serta user-agent. |
| Kelola Pengguna | Seeder mengembalikan password lama menjadi password bersama; edit bersamaan dapat saling menimpa | **Diperbaiki**. Seeder tidak mengubah password akun lama, password awal production berasal dari environment, password form minimal 12 karakter, dan konflik edit ditolak melalui versi record. |
| Data Pegawai | NIP berpotensi bentrok dengan username; edit/import bersamaan berisiko konflik | **Diperbaiki**. Pemeriksaan namespace identitas berjalan dua arah dan setiap perubahan memakai versi record/transaksi. |
| Kenaikan Pangkat | Status dan sinkronisasi master pegawai dapat melewati audit atau memakai data lama | **Diperbaiki**. Perubahan status dikunci dalam transaksi, versi record diperiksa, dan sinkronisasi dilakukan melalui model agar observer audit tetap aktif. |
| Gaji Berkala | Risiko sama dengan Kenaikan Pangkat | **Diperbaiki** dengan transaksi, pengecekan versi, penguncian pegawai, dan audit model. |
| Surat Masuk | Path lampiran dapat diarahkan ke file privat lain; edit bersamaan; ekspor formula spreadsheet | **Diperbaiki**. Path wajib berada pada direktori Surat Masuk dan terikat ke record pemilik, edit memakai optimistic locking, dan CSV menetralkan formula. |
| Surat Keluar | Path berkas final/bukti kirim, status paralel, dan CSV | **Diperbaiki**. Setiap jenis berkas mempunyai direktori yang diizinkan, path tidak boleh digunakan record lain, aksi status terkunci, dan CSV aman. |
| Legalisir | Status siap/diambil dapat melompati checklist; path dokumen; CSV | **Diperbaiki**. Status final wajib checklist lengkap dan identitas verifier, pengambilan wajib tanggal/nama, file terikat ke record, serta CSV aman. |
| Preview / Unduh Lampiran | Otorisasi hanya berdasarkan nama modul | **Diperbaiki**. Surat dan Legalisir memverifikasi pemilik, kolom, direktori, dan ikatan path saat setiap akses. Arsip kepegawaian lama tetap mengikuti izin modul. Akses preview/unduh tercatat. |
| Impor / Ekspor | Ekspor belum lengkap diaudit dan sel CSV dapat dieksekusi sebagai formula | **Diperbaiki**. Impor/ekspor mencatat akun dan jumlah data; semua nilai CSV yang berbahaya diberi prefix aman. |
| Audit Log | Kegagalan audit dapat meninggalkan perubahan bisnis; identitas lama sulit ditelusuri setelah akun berubah | **Diperbaiki**. Operasi panel memakai transaksi database dan log menyimpan snapshot username/role selain ID akun. |
| Backup Database | Password database dapat muncul di command, file hanya SQL, lokasi bercampur storage aplikasi, nama dapat bertabrakan | **Diperbaiki**. Backup memakai process argument tanpa shell, `MYSQL_PWD`, lock proses, snapshot konsisten, nama unik, folder `storage/secure-backups`, lampiran ikut dibundel, retensi 30, dan AES wajib pada production. |
| Framework / dependensi | Laravel 10 sudah di luar masa dukungan dan lockfile memiliki advisory | **Diperbaiki**. Laravel naik ke 12.70.1, Sanctum 4.3.3, PHPUnit 11.5.57; `composer audit --locked` melaporkan nol advisory. |
| Waktu sistem | Aplikasi masih memakai UTC, berbeda dari waktu operasional sekolah | **Diperbaiki**. Default aplikasi menjadi `Asia/Jakarta` dan dapat dioverride melalui `APP_TIMEZONE`. |

## Kontrol lintas akun

Tabel operasional utama memiliki `lock_version`. Ketika dua akun membuka record yang sama, penyimpanan dari formulir lama ditolak dan pengguna diminta memuat ulang. Aksi status membaca ulang serta mengunci baris database dalam transaksi. Dengan demikian dua akun dengan role yang sama tetap diidentifikasi secara terpisah dan tidak boleh menimpa perubahan secara diam-diam.

Audit sensitif menyimpan `user_id`, snapshot username, snapshot role, IP, user-agent, waktu, entitas, aksi, serta data sebelum/sesudah yang sudah menyaring password, token, dan versi internal.

## Konfigurasi production yang wajib

- Isi password awal akun dengan nilai berbeda dan kuat melalui seluruh variabel `TU_*_INITIAL_PASSWORD`. Seeder tidak lagi mengganti password akun yang sudah ada.
- Gunakan HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, dan `APP_URL` HTTPS.
- Isi `BACKUP_ARCHIVE_PASSWORD` minimal 16 karakter, simpan di luar repository, dan batasi izin folder `storage/secure-backups` untuk akun layanan aplikasi.
- Jalankan `php artisan schedule:run` setiap menit agar backup harian pukul 01.30 berjalan.
- Salin backup terenkripsi ke media/server kedua dengan akses terbatas, lalu lakukan uji restore berkala pada server terisolasi.
- Buat akun per orang. Jangan berbagi akun berdasarkan jabatan. Nonaktifkan akun segera saat pegawai pindah/tidak bertugas.
- MFA untuk administrator dan pimpinan masih merupakan peningkatan lanjutan; aplikasi saat ini belum menyediakan MFA bawaan.

## Verifikasi

- Migrasi `2026_10_25_000001_add_optimistic_lock_versions` telah diterapkan pada database lokal tanpa menghapus data.
- Suite akhir lulus **79 tes dengan 615 assertion**. Tes keamanan khusus mencakup isolasi lampiran, konflik edit, rollback saat audit gagal, aturan Legalisir, benturan username/NIP, CSV injection, dan audit login.
- Audit Composer setelah upgrade: nol advisory.
- Infrastruktur eksternal seperti TLS, permission sistem operasi, salinan backup luar server, dan restore nyata harus diverifikasi ulang saat deployment customer.
