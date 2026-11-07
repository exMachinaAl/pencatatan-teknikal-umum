# Deployment Pencatatan Pasif TU

Tanggal verifikasi: 7 November 2026 (Asia/Jakarta).

## Hasil implementasi

- Surat Masuk dan Surat Keluar menjadi register arsip pasif.
- Lokasi fisik wajib pada input manual, impor CSV/XLSX, dan impor Google Form.
- Catatan surat baru langsung menggunakan status internal `diarsipkan`; status tidak menjadi pekerjaan pengguna.
- Disposisi, relasi balasan, pengiriman, bukti diterima, dan aksi perubahan status tidak lagi tampil di UI.
- Legalisir menjadi pencatatan satu kali dengan jumlah dibawa, dikembalikan, dan disimpan.
- Maksimal jumlah dibawa saat ini 6; tepat 1 dokumen disimpan TU; sisanya kembali ke pemohon.
- Catatan legalisir baru memakai status internal `tercatat` dan tidak membuat antrean, token pelacakan, atau notifikasi.
- Dashboard hanya menampilkan workflow Kenaikan Pangkat dan Kenaikan Gaji Berkala.
- Pencarian terpadu mencari nomor, pihak, kontak, klasifikasi, jenis dokumen, dan lokasi fisik.
- Tabel desktop menggunakan lima kelompok informasi; layar di bawah breakpoint `lg` memakai satu ringkasan agar teks tidak terjepit vertikal.
- Kolom dan data workflow lama tidak dihapus. Data historis tetap dapat dibaca.

## Titik pemulihan

- Commit sebelum perubahan: `f6508ec`.
- Tag lokal sebelum perubahan: `pre-passive-workflow-2026-11-07`.
- Backup privat sebelum migrasi lokal: `backup-tata_usaha_db-2026-11-07_19-35-16-574554-fb40as.zip`.
- Baseline lengkap: `docs/BASELINE-SEBELUM-PENCATATAN-PASIF-2026-11-07.md`.

Backup berada di penyimpanan privat dan sengaja tidak dimasukkan ke Git.

## Urutan commit

1. `124b19d` — baseline aplikasi sebelum perubahan.
2. `2b92097` — register Surat Masuk/Keluar pasif.
3. `30391d0` — legalisir sebagai layanan arsip selesai.
4. `e1afb23` — dashboard dan pencarian mengikuti proses nyata.
5. Commit verifikasi akhir berisi checklist ini dan penyesuaian tes terakhir.

## Perubahan database

Migrasi `2026_11_07_000001_add_passive_copy_counts_to_legalisir.php` hanya menambah:

- `jumlah_dibawa`;
- `jumlah_dikembalikan`; dan
- `jumlah_disimpan`.

Data lama diisi dari `jumlah_lembar`: satu dokumen disimpan dan sisanya dianggap dikembalikan. Kolom `jumlah_lembar` tetap ada dan dicerminkan dari `jumlah_dibawa` agar aplikasi lama, ekspor lama, serta rollback kode tetap memiliki nilai.

Migrasi sudah diuji dengan urutan maju, rollback satu langkah, lalu maju kembali. Jangan menjalankan rollback migrasi ini setelah operator memasukkan data baru tanpa terlebih dahulu membuat dan memverifikasi backup karena ketiga kolom baru akan dihapus.

## Checklist sebelum deployment customer

### Keputusan operasional

- [ ] Customer menyetujui bahwa Surat Masuk tidak mempunyai disposisi/balasan di aplikasi.
- [ ] Customer menyetujui bahwa Surat Keluar tidak mempunyai status draf/terkirim/diterima di aplikasi.
- [ ] Customer menyetujui bahwa Legalisir tidak mempunyai penolakan, status siap, pengambilan, notifikasi, atau pelacakan.
- [ ] Konfirmasi batas legalisir: maksimal **6 dokumen dibawa**.
- [ ] Konfirmasi aturan: tepat **1 dokumen disimpan TU**.
- [ ] Sepakati pola lokasi fisik, misalnya `Lemari / Rak / Map / Tahun`.
- [ ] Tentukan siapa yang boleh mengoreksi dan menghapus catatan.
- [ ] Pastikan KP dan KGB memang tetap memakai workflow aktif.

### Infrastruktur dan keamanan

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` menggunakan alamat produksi.
- [ ] HTTPS aktif dan sertifikat valid.
- [ ] Password seluruh akun produksi diganti dari password pengembangan.
- [ ] Akun yang tidak dipakai dinonaktifkan.
- [ ] Hak akses diuji dengan minimal dua akun pada role yang sama.
- [ ] Folder storage tidak dapat diakses langsung dari web.
- [ ] Permission `storage` dan `bootstrap/cache` hanya diberikan sesuai kebutuhan proses web.
- [ ] Mail/WhatsApp lama tidak dianggap sebagai kebutuhan legalisir baru.
- [ ] Scheduler Laravel berjalan setiap menit melalui Task Scheduler/cron.
- [ ] Zona waktu server dan aplikasi adalah `Asia/Jakarta`.
- [ ] Backup otomatis harian berhasil dan file backup dapat dibuka/dipulihkan pada mesin uji.

### Data dan file

- [ ] Jalankan `php artisan app:backup-database` sebelum deployment.
- [ ] Salin backup ke media lain yang tidak berada pada server aplikasi yang sama.
- [ ] Catat jumlah Surat Masuk, Surat Keluar, Legalisir, Pegawai, KP, dan KGB sebelum migrasi.
- [ ] Pastikan lampiran privat ikut dalam backup.
- [ ] Uji satu tautan Google Drive/Sheets menggunakan akun penerima yang benar.
- [ ] Pastikan dokumen sensitif tidak memakai izin “siapa saja yang memiliki link”.
- [ ] Unduh template impor terbaru; template lama legalisir memang tidak lagi kompatibel.

## Urutan deployment

Jalankan dari direktori aplikasi setelah commit target tersedia pada server:

```text
php artisan app:backup-database
php artisan down
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
php artisan up
```

Sesudah aplikasi hidup, periksa `php artisan migrate:status` dan `php artisan schedule:list`. Jangan memakai `migrate:fresh` pada database customer.

## Uji penerimaan setelah deployment

### Login dan role

- [ ] Login dengan username, NIP, dan email lama pada akun yang sesuai.
- [ ] Akun nonaktif tidak dapat masuk.
- [ ] Dua akun dengan role sama dapat melihat data bersama.
- [ ] Role baca-saja tidak melihat tombol tambah, edit, hapus, impor, atau ekspor yang tidak diizinkan.

### Surat Masuk

- [ ] Lokasi fisik wajib; penyimpanan gagal bila kosong.
- [ ] Nomor agenda otomatis dan tidak berubah saat edit.
- [ ] Catatan baru tampil tanpa status/disposisi.
- [ ] Lokasi, file privat, dan tautan tampil langsung di tabel.
- [ ] Pencarian nomor, pengirim, klasifikasi, serta lokasi berhasil.

### Surat Keluar

- [ ] Nomor surat wajib dan unik.
- [ ] Lokasi fisik wajib.
- [ ] Tidak ada tombol draf, terkirim, diterima, atau arsipkan.
- [ ] File dan tautan dapat dibuka dari tabel sesuai izin.
- [ ] Pencarian tujuan, klasifikasi, nomor, serta lokasi berhasil.

### Legalisir

- [ ] Pilihan jumlah hanya 1 sampai 6.
- [ ] Membawa 6 menghasilkan kembali 5 dan arsip 1.
- [ ] Lokasi fisik wajib.
- [ ] Tidak ada tombol proses, tolak, siap, diambil, notifikasi, atau bukti penerimaan.
- [ ] Catatan baru tidak mempunyai token/antrean pelacakan publik.
- [ ] Pencarian nama, kontak, jenis dokumen, nomor, dan lokasi berhasil.

### Tabel dan mobile

- [ ] Desktop dengan sidebar terbuka tidak membuat teks menjadi satu huruf per baris.
- [ ] Pada ponsel, tiap record tampil sebagai satu ringkasan dan tombol lampiran tetap dapat digunakan.
- [ ] Tidak ada scroll horizontal halaman yang tidak diperlukan.
- [ ] Menu aksi tetap dapat ditekan pada ukuran layar kecil.

### Impor, ekspor, dan keamanan

- [ ] Impor template terbaru berhasil untuk ketiga modul.
- [ ] Baris tanpa lokasi fisik ditolak dengan pesan jelas.
- [ ] Jumlah legalisir yang tidak konsisten ditolak seluruhnya sebelum simpan.
- [ ] Ekspor menyertakan lokasi, tautan, dan tiga jumlah legalisir.
- [ ] Nilai CSV yang diawali formula dinetralkan.
- [ ] File privat tidak dapat dibuka tanpa login atau tanpa izin modul.
- [ ] Koreksi record tercatat pada audit log.
- [ ] Simulasi edit bersamaan tidak menimpa perubahan akun lain.

## Strategi rollback

### Rollback sebelum ada data produksi baru

1. Aktifkan maintenance mode.
2. Jalankan rollback satu migrasi bila ketiga kolom baru belum pernah dipakai.
3. Kembalikan kode ke tag `pre-passive-workflow-2026-11-07`.
4. Bersihkan cache, bangun aset versi lama bila diperlukan, lalu hidupkan aplikasi.

### Rollback setelah sudah ada data produksi baru

Jangan langsung menjalankan `migrate:rollback`, karena jumlah legalisir baru akan hilang. Pilihan paling aman adalah:

1. Aktifkan maintenance mode.
2. Ekspor data yang masuk setelah deployment untuk arsip pemeriksaan.
3. Pulihkan database dan lampiran dari backup pra-deployment yang sudah diverifikasi.
4. Kembalikan kode ke tag `pre-passive-workflow-2026-11-07`.
5. Jalankan pemeriksaan jumlah record dan lampiran sebelum membuka aplikasi.

Rollback kode saja masih dapat membaca sebagian besar data karena kolom lama dipertahankan, tetapi status internal `tercatat` tidak dikenal sebagai workflow lama. Karena itu rollback penuh dengan backup adalah pilihan yang direkomendasikan bila customer membatalkan perubahan.

## Hasil verifikasi lokal

- PHPUnit: **85 tes, 682 assertion, lulus**.
- Vite: **59 modul, build produksi berhasil**.
- Migrasi baru: **Ran**.
- Scheduler: sinkronisasi pangkat pukul 00:15 dan backup pukul 01:30 tetap terdaftar.
- Pemeriksaan whitespace Git: lulus.
