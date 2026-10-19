# Workflow Persuratan dan Legalisir

## Operasional harian

1. Gunakan **Pencarian Terpadu** untuk menemukan surat atau legalisir tanpa membuka modul satu per satu.
2. Pada surat masuk, isi penanggung jawab, prioritas, dan tenggat dalam bagian disposisi. Dashboard hanya menampilkan peringatan jika terdapat disposisi terlambat.
3. Pada surat keluar, pilih surat masuk yang dibalas. Saat menandai terkirim, isi metode, waktu, resi/referensi, dan bukti bila ada. Gunakan **Catat Bukti Diterima** ketika penerima telah mengonfirmasi.
4. Pada legalisir, pilih kanal notifikasi dan lengkapi kontak yang sesuai. Untuk menandai siap diambil, seluruh checklist verifikasi wajib dicentang.
5. Berikan tautan/QR pelacakan kepada pemohon. Token adalah rahasia akses status; jangan dibagikan kepada pihak lain.
6. Saat dokumen diserahkan, catat nama pengambil, hubungannya dengan pemohon, dan unggah bukti serah-terima bila tersedia.

## Lampiran privat

- File tersimpan pada disk `local`, bukan direktori publik.
- Setiap penggantian file utama atau bukti membuat versi baru; versi sebelumnya tetap menjadi arsip.
- Unduhan hanya melalui endpoint berautentikasi dan permission modul. Pengguna, waktu, IP, serta user-agent dicatat.

## Notifikasi

- Perubahan status ke `siap_diambil` membuat outbox satu kali untuk setiap kanal.
- Jalankan `php artisan notifications:process` secara manual atau lewat scheduler setelah konfigurasi mail produksi selesai.
- WhatsApp sengaja tidak dikirim sebelum provider dipilih. Record akan berstatus `menunggu_provider`; integrasi provider nantinya mengambil payload dari `notification_outbox` dan memperbarui status pengiriman.
- Pastikan `APP_URL` memakai alamat aplikasi yang dapat dibuka pemohon agar tautan pada email/QR tidak mengarah ke localhost.

## Keamanan dan pemulihan

- Token pelacakan disimpan sebagai hash untuk pencarian dan salinan terenkripsi untuk pencetakan ulang.
- Audit log tidak menyimpan hash atau token terenkripsi.
- Backup database harus dibuat sebelum migrasi atau restore. Jangan memakai `migrate:fresh` pada database Laragon utama.
