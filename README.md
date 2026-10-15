# Sistem Informasi Administrasi Tata Usaha (TU) Sekolah Negeri

Aplikasi web modern berbasis **Laravel 10** dan **Filament 3** yang dirancang khusus untuk memenuhi kebutuhan tata usaha sekolah negeri di Indonesia. Sistem ini mencakup 5 modul operasional utama, manajemen dokumen/arsip digital, otomatisasi jatuh tempo kepegawaian, integrasi impor respons Google Form (CSV), cetak bukti tanda terima legalisir (PDF), perlindungan privasi data gaji (UU PDP), dan pencadangan database mandiri.

---

## 🌟 Modul & Fitur Utama

### 1. Data Pegawai & Kepegawaian
* Pencatatan data lengkap Guru dan Tenaga Kependidikan: NIP, Nama, Status Kepegawaian (PNS, PPPK, Honorer), Jabatan, Golongan/Ruang, dan TMT terakhir.
* **Kalkulator Otomatis Calon Kenaikan Pangkat (KP)**: Deteksi otomatis pegawai yang telah mencapai interval 4 tahun dari TMT pangkat terakhir.
* **Kalkulator Otomatis Jatuh Tempo KGB**: Deteksi otomatis pegawai yang mendekati jatuh tempo Kenaikan Gaji Berkala (2 tahun dari TMT KGB terakhir).
* Filter pintar: Filter status kepegawaian, golongan, status aktif, filter cepat "Calon KP" dan "Jatuh Tempo KGB".
* Ekspor data pegawai ke format CSV/Excel.

### 2. Kenaikan Pangkat (KP)
* Pengelolaan riwayat dan usulan KP (Reguler, Pilihan / Jabatan Fungsional, Penyesuaian Ijazah).
* Alur proses transparan: `Berkas Disiapkan` ➔ `Diajukan ke BKD/Disdik` ➔ `SK Terbit` ➔ `Diserahkan`.
* Saat SK berstatus **SK Terbit** atau **Diserahkan**, sistem otomatis memperbarui golongan dan TMT pangkat pada master data pegawai.
* Pencarian cepat berdasarkan NIP, nama pegawai, atau nomor SK.

### 3. Kenaikan Gaji Berkala (KGB)
* Riwayat KGB per pegawai dengan penghitungan otomatis TMT KGB berikutnya (+2 tahun) yang tetap dapat disesuaikan manual.
* **Perlindungan Privasi Data Gaji (UU PDP No. 27 Tahun 2022)**: Data nominal gaji pokok hanya dapat diakses oleh peran **Admin (Operator TU)** dan **Kepala Sekolah / Kepala TU**. Bagi staf operasional biasa, kolom gaji disamarkan (`••••••••`).
* Dashboard peringatan jatuh tempo KGB dalam 3 bulan ke depan.

### 4. Surat Masuk & Disposisi
* **Penomoran Agenda Otomatis**: Format `SM/{Tahun}/{NomorUrut}` terurut per tahun anggaran secara otomatis.
* Lembar **Disposisi Pimpinan Digital**: Pencatatan arahan/instruksi Kepala Sekolah/Kepala TU, batas waktu, dan catatan tindak lanjut.
* Pengarsipan ganda: Pencatatan lokasi fisik lemari/ordner/boks arsip dan unggahan softfile scan dokumen (PDF/JPG/PNG, proteksi disk privat, maksimal 5 MB).
* Filter lengkap: Rentang tanggal diterima, pengirim, klasifikasi surat, sifat surat (Biasa, Penting, Segera, Rahasia), dan status arsip.

### 5. Surat Keluar & Kearsipan
* **Penomoran Surat Thread-Safe**: Dihasilkan otomatis di dalam transaksi database berkunci atomik `(tahun, nomor_urut)` untuk mencegah nomor ganda/lompat.
* Format penomoran fleksibel dan dapat dikonfigurasi melalui menu pengaturan (contoh standar: `421/{nomor_urut}/SMAN1/{tahun}`).
* Pencatatan pembuat konsep, penandatangan, tujuan surat, sifat, dan softfile final bertanda tangan.

### 6. Layanan Legalisir Dokumen
* Layanan permohonan legalisir bagi alumni, siswa aktif, maupun orang tua/wali (Ijazah, SKHUN, Transkrip Nilai, dll).
* Penomoran registrasi legalisir otomatis: `LEG/{Tahun}/{NomorUrut}`.
* Alur status: `Diterima` ➔ `Diproses / Diverifikasi` ➔ `Siap Diambil` ➔ `Sudah Diambil`.
* **Cetak Bukti Tanda Terima (PDF)**: Tombol cetak tanda terima instan ber-KOP resmi sekolah, lengkap dengan nomor registrasi, data pemohon, dan ketentuan pengambilan berkas.

### 7. Impor Respons Google Form (CSV)
* Jalur masuk fleksibel untuk data permohonan legalisir online atau surat dari instansi luar yang dihimpun melalui Google Form / Google Sheets.
* Dilengkapi wizard interaktif:
  1. Unggah CSV.
  2. Pemetaan kolom (Smart Column Mapping) antara kolom CSV Google Form dengan atribut database.
  3. Pratinjau 5 baris pertama data.
  4. Deteksi dan pencegahan data duplikat secara otomatis.
  5. Pencatatan log impor ke tabel `import_gform_log`.

### 8. Audit Log & Keamanan
* Pencatatan otomatis riwayat aksi `create`, `update`, `delete`, `restore`, dan `import` menggunakan Model Observers.
* Merekam identitas pengguna, IP address, waktu, serta perbandingan data sebelum dan sesudah perubahan (kata sandi dikecualikan dari log).

### 9. Pencadangan & Pemulihan Database (Backup & Restore)
* Perintah terjadwal via CLI: `php artisan app:backup-database`.
* Menu antarmuka visual (1-Click Backup) di Panel Admin untuk mengunduh berkas dump SQL terenkripsi secara langsung.

---

## 👥 Akun Bawaan (Default Credentials)

Sistem telah dilengkapi dengan data seeder awal dan 4 tingkat peran:

| Peran (Role) | Email Login | Kata Sandi | Hak Akses Utama |
|---|---|---|---|
| **Admin (Operator TU)** | `admin@tu.sekolah.sch.id` | `password` | Akses penuh CRUD seluruh modul, kelola akun pengguna, backup database, impor Google Form, ekspor data, konfigurasi format nomor surat. |
| **Kepala Sekolah / Kepala TU** | `kepala@tu.sekolah.sch.id` | `password` | Hak verifikasi surat & disposisi, monitoring legalisir, melihat data gaji pegawai, melihat audit log. |
| **Staf Tata Usaha** | `staf@tu.sekolah.sch.id` | `password` | Operasional harian surat masuk/keluar, legalisir, pengajuan KP dan KGB (data nominal gaji disamarkan). |
| **Pegawai / Guru** | `pegawai@tu.sekolah.sch.id` | `password` | Portal melihat data kepegawaian pribadi. |

---

## 💻 Persyaratan Lingkungan (Environment Requirements)

* **PHP**: 8.1.x atau lebih tinggi (Ekstensi aktif: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `intl`)
* **Node.js**: 20.19 atau lebih tinggi
* **MySQL**: 8.0.x atau MariaDB 10.4+
* **Composer**: 2.4+
* **Web Server**: Apache / Nginx / PHP Built-in Server (Laragon kompatibel langsung)

---

## 🚀 Petunjuk Menjalankan Aplikasi

Jika menggunakan terminal PowerShell:

```powershell
# 1. Pastikan PATH Laragon/PHP aktif
$env:Path = "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;" + $env:Path

# 2. Migrasi dan Seed Data Contoh (jika belum dilakukan)
php artisan migrate --seed

# 3. Hubungkan folder penyimpanan privat/publik
php artisan storage:link

# 4. Pasang dependensi frontend (cukup sekali)
npm install

# 5. Jalankan Laravel dan Vite bersamaan
npm run dev:full
```

Akses aplikasi melalui browser:
* **URL Login**: [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)
* Masuk menggunakan email dan password dari tabel di atas.

Vite akan memuat ulang halaman ketika file Blade/PHP berubah dan menerapkan perubahan CSS/JavaScript tanpa perlu menjalankan ulang server secara manual. Untuk build produksi, gunakan `npm run build`.

---

## 🧪 Menjalankan Pengujian Otomatis (Unit & Feature Tests)

Aplikasi telah dilengkapi unit & feature test untuk memastikan penomoran surat, kalkulasi jatuh tempo, dan pembatasan hak akses gaji berjalan 100% aman:

```powershell
php artisan test
```

Semua pengujian (`HakAksesTest`, `JatuhTempoTest`, `SuratTest`, `ExampleTest`) dinyatakan lulus (*8 passed, 22 assertions*).

---

## 🛡️ Prosedur Pemulihan Basis Data (Restore Guide)

Jika ingin memulihkan database dari snapshot cadangan yang ada di folder `storage/app/backups/`:

```powershell
mysql -u root -p tata_usaha_db < storage/app/backups/backup-tata_usaha_db-YYYY-MM-DD_HH-mm-ss.sql
```

---

## 📋 Catatan Risiko & Validasi Lanjutan dengan Pihak TU Sekolah

1. **Format Nomor Surat Dinas**: Format default saat ini adalah `421/{nomor_urut}/SMAN1/{tahun}`. Jika pihak sekolah memiliki kode sub-unit atau tata naskah dinas daerah khusus, format dapat disesuaikan langsung di menu **Pengaturan Aplikasi** tanpa perlu mengubah kode sumber.
2. **Keterhubungan Google Form**: Jalur impor CSV saat ini telah dilengkapi pencegahan data ganda. Bila volume pengiriman surat eksternal atau legalisir alumni sangat tinggi di masa mendatang, dapat dihubungkan webhook Google Apps Script langsung ke endpoint API internal.
3. **Penyimpanan Berkas Digital**: Berkas pindaian surat dan ijazah disimpan pada disk privat `storage/app/` demi menjaga kerahasiaan dokumen dinas sekolah sesuai UU Perlindungan Data Pribadi (UU PDP).
# pencatatan-teknikal-umum
