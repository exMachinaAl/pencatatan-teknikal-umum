# Sistem Informasi Administrasi Tata Usaha (TU) Sekolah Negeri

Aplikasi web modern berbasis **Laravel 10** dan **Filament 3** yang dirancang khusus untuk memenuhi kebutuhan tata usaha sekolah negeri di Indonesia. Sistem ini mencakup 5 modul operasional utama, manajemen dokumen/arsip digital, otomatisasi jatuh tempo kepegawaian, integrasi impor respons Google Form (CSV), cetak bukti tanda terima legalisir (PDF), perlindungan privasi data gaji (UU PDP), dan pencadangan database mandiri.

---

## 🌟 Modul & Fitur Utama

### 1. Data Pegawai & Kepegawaian
* Pencatatan data lengkap Guru dan Tenaga Kependidikan: NIP, nama, foto profil privat, kontak, biodata, unit kerja, pendidikan terakhir, status kepegawaian, jabatan, golongan/ruang, dan TMT terakhir.
* Akun pengurus dapat memuat foto privat, nomor kontak, jabatan organisasi, unit/divisi, serta dihubungkan ke satu data pegawai.
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
* **Perlindungan Privasi Data Gaji (UU PDP No. 27 Tahun 2022)**: Data nominal gaji pokok hanya dapat diakses oleh **Kepala TU** dan **Petugas Kepegawaian**. Bagi role lain yang boleh membaca modul kepegawaian, nominal disamarkan (`••••••••`).
* Dashboard peringatan jatuh tempo KGB dalam 3 bulan ke depan.

### 4. Surat Masuk & Disposisi
* **Penomoran Agenda Otomatis**: Format `SM/{Tahun}/{NomorUrut}` terurut per tahun anggaran secara otomatis.
* Lembar **Disposisi Pimpinan Digital**: Pencatatan arahan/instruksi Kepala Sekolah/Kepala TU, batas waktu, dan catatan tindak lanjut.
* Pengarsipan ganda: Pencatatan lokasi fisik lemari/ordner/boks arsip dan unggahan softfile scan dokumen (PDF/JPG/PNG, proteksi disk privat, maksimal 5 MB).
* Preview PDF atau gambar tersedia dari detail record tanpa menjadikan file publik; izin pengguna tetap diperiksa dan akses dicatat.
* Filter surat masuk: rentang tanggal diterima, klasifikasi surat, dan status arsip. Kode klasifikasi dapat dicari atau ditambahkan langsung saat mencatat surat.

### 5. Surat Keluar & Kearsipan
* **Nomor Surat Resmi**: Nomor surat keluar diisi manual mengikuti nomor yang telah ditetapkan sekolah dan diperiksa unik oleh sistem.
* Nomor agenda surat masuk tetap dibuat otomatis sebagai identitas internal TU, terpisah dari nomor surat instansi asal.
* Pencatatan nomor resmi, tanggal, tujuan, kode klasifikasi, lokasi arsip, dan softfile final bertanda tangan. Status alur dikelola otomatis melalui aksi tabel.

### 6. Layanan Legalisir Dokumen
* Layanan permohonan legalisir bagi alumni, siswa aktif, maupun orang tua/wali (Ijazah, SKHUN, Transkrip Nilai, dll).
* Penomoran registrasi legalisir otomatis: `LEG/{Tahun}/{NomorUrut}`.
* Alur status: `Diterima` ➔ `Diproses / Diverifikasi` ➔ `Siap Diambil` ➔ `Sudah Diambil`.
* **Cetak Bukti Tanda Terima (PDF)**: Tombol cetak tanda terima instan ber-KOP resmi sekolah, lengkap dengan nomor registrasi, data pemohon, dan ketentuan pengambilan berkas.
* Dokumen legalisir yang berupa PDF atau gambar dapat dipreview secara privat oleh pengguna yang berwenang.

### 7. Impor Respons Google Form (CSV)
* Jalur masuk fleksibel untuk data permohonan legalisir online atau surat dari instansi luar yang dihimpun melalui Google Form / Google Sheets.
* Dilengkapi wizard interaktif:
  1. Unggah CSV.
  2. Pemetaan kolom (Smart Column Mapping) antara kolom CSV Google Form dengan atribut database.
  3. Pratinjau 5 baris pertama data.
  4. Deteksi dan pencegahan data duplikat secara otomatis.
  5. Pencatatan log impor ke tabel `import_gform_log`.

### 8. Lampiran Langsung dan Tautan Google
* Lampiran privat dapat dipratinjau dan diunduh langsung dari kolom **Lampiran** pada tabel tanpa masuk ke halaman detail.
* Surat Masuk, Surat Keluar, Legalisir, Kenaikan Pangkat, dan Gaji Berkala menerima tautan HTTPS Google Drive, Google Sheets, Google Form, atau penyedia lampiran lain. Tautan hanya ditampilkan bila diisi.
* Template impor dan hasil ekspor menyertakan kolom tautan opsional agar tautan dapat ikut dibagikan melalui spreadsheet.
* Berkas lokal tetap melewati otorisasi aplikasi dan dicatat pada log akses. Hak akses tautan Google mengikuti pengaturan berbagi pada Google Drive; gunakan pembatasan akun organisasi untuk dokumen sensitif.
* Panduan penggunaan tersedia di [docs/PANDUAN-SPREADSHEET-DAN-LAMPIRAN.md](docs/PANDUAN-SPREADSHEET-DAN-LAMPIRAN.md).

### 8. Audit Log & Keamanan
* Pencatatan otomatis riwayat aksi `create`, `update`, `delete`, `restore`, dan `import` menggunakan Model Observers.
* Merekam identitas pengguna, IP address, waktu, serta perbandingan data sebelum dan sesudah perubahan (kata sandi dikecualikan dari log).

### 9. Pencadangan & Pemulihan Database (Backup & Restore)
* Perintah terjadwal via CLI: `php artisan app:backup-database`.
* Menu antarmuka visual (1-Click Backup) di Panel Admin untuk membuat dan mengunduh dump SQL dari penyimpanan privat. Enkripsi dan verifikasi checksum dijadwalkan pada fase V4.5.

---

## 👥 Akun Bawaan Lokal (Default Credentials)

Seeder membuat lima akun lokal berikut. Administrator Sistem berada di Divisi IT sebagai fasilitator teknis dan bukan atasan operasional TU. Kepala TU adalah pimpinan tertinggi di lingkungan TU. Dua role tambahan (`kepegawaian` dan `auditor`) dapat diberikan Administrator Sistem kepada pengguna nyata melalui menu Kelola Pengguna.

> Seluruh akun bawaan menggunakan password `password`. Menjalankan `UserSeeder` ulang akan mereset password kelima akun bawaan menjadi `password`, tanpa mengubah role atau status aktifnya.

| Peran (Role) | Username | Email Lama | Kata Sandi | Hak Akses Utama |
|---|---|---|---|---|
| **Administrator Sistem / Fasilitator TI** | `admin` | `admin@tu.sekolah.sch.id` | `.env` | Divisi IT: kelola pengguna, pengaturan, backup, audit teknis, dan pemantauan baca-saja. Bukan pelaksana atau pimpinan TU. |
| **Kepala Tata Usaha** | `kepala_tu` | `kepala.tu@tu.sekolah.sch.id` | `.env` | Pimpinan operasional tertinggi TU dengan akses seluruh modul kerja TU dan audit. |
| **Kepala Sekolah** | `kepala_sekolah` | `kepala@tu.sekolah.sch.id` | `.env` | Baca modul operasional dan cetak bukti legalisir; tidak mengubah record langsung. |
| **Petugas TU** | `staf_tu` | `staf@tu.sekolah.sch.id` | `.env` | Operasional surat masuk/keluar dan legalisir, termasuk impor/ekspor yang diizinkan. |
| **Pegawai / Guru** | `pegawai` | `pegawai@tu.sekolah.sch.id` | `.env` | Akses panel dasar; portal record pribadi belum dibuka sampai relasi kepemilikan data tersedia. |

Portal menerima username, NIP dari data pegawai yang dihubungkan ke akun, atau email lama. Untuk instalasi skala pribadi, email akun boleh dikosongkan.

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

Aplikasi dilengkapi unit dan feature test untuk memeriksa penomoran surat, kalkulasi jatuh tempo, impor, serta pembatasan hak akses:

```powershell
php artisan test
```

Jumlah test terus bertambah mengikuti fase pengembangan. Gunakan hasil perintah terbaru sebagai sumber status, bukan angka statis pada dokumentasi.

---

## 🛡️ Prosedur Pemulihan Basis Data (Restore Guide)

Jika ingin memulihkan database dari snapshot cadangan yang ada di folder `storage/app/backups/`:

```powershell
mysql -u root -p tata_usaha_db < storage/app/backups/backup-tata_usaha_db-YYYY-MM-DD_HH-mm-ss.sql
```

---

## 📋 Catatan Risiko & Validasi Lanjutan dengan Pihak TU Sekolah

1. **Nomor Surat Dinas**: Nomor surat keluar diisi manual sesuai nomor resmi yang telah ditetapkan sekolah. Sistem memeriksa keunikannya dan tidak membuat nomor baru atas nama sekolah.
2. **Keterhubungan Google Form**: Jalur impor CSV dan tautan langsung Google Drive/Sheets telah tersedia. Sinkronisasi otomatis dua arah belum memakai Google API; bila volume data meningkat, integrasi dapat dilanjutkan melalui Google Apps Script atau service account ke endpoint API internal.
3. **Penyimpanan Berkas Digital**: Berkas pindaian surat dan ijazah disimpan pada disk privat `storage/app/` demi menjaga kerahasiaan dokumen dinas sekolah sesuai UU Perlindungan Data Pribadi (UU PDP).
# pencatatan-teknikal-umum
