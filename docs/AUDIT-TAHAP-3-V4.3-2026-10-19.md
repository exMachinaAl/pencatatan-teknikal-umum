# Audit Tahap 3 — V4.3 Data Integrity

Tanggal implementasi: 19 Oktober 2026  
Metode penerapan: perubahan langsung pada direktori proyek dan database lokal, tanpa ZIP atau upgrader.

## Ringkasan hasil

V4.3 memperbaiki penomoran dokumen, transaksi penyimpanan, duplikasi data kepegawaian, sinkronisasi KP/KGB, serta validasi tanggal dan nominal di backend. Migrasi additive telah diterapkan ke database lokal setelah backup SQL dibuat.

## Temuan dan perbaikan

### 1. Penomoran otomatis belum benar-benar atomic

Sebelum:

- nomor Surat Masuk dan Legalisir dihitung berdasarkan jumlah record;
- transaksi generator selesai sebelum record disimpan;
- penguncian baris Surat Keluar tidak melindungi kondisi tabel kosong;
- form Surat Keluar memanggil generator dua kali sehingga `nomor_surat` dan `nomor_urut` dapat berasal dari urutan berbeda;
- record soft-deleted dapat menyebabkan nomor lama digunakan kembali.

Sesudah:

- tabel `document_sequences` menjadi sumber reservasi urutan per jenis dan periode;
- sequence dibuat/diperbarui dengan transaksi dan `lockForUpdate`;
- nilai awal sequence selalu dibandingkan dengan nomor maksimum yang sudah ada, termasuk soft-deleted;
- form tidak lagi menghasilkan nomor saat hanya dibuka;
- nomor dialokasikan ketika record disimpan;
- halaman create membungkus alokasi nomor dan penyimpanan dalam satu transaksi;
- identitas nomor Surat Masuk, Surat Keluar, Legalisir, dan nomor antrean tidak dapat diedit setelah tersimpan;
- tahun tanggal dokumen divalidasi terhadap tahun penomoran.

Catatan: sequence menjamin nomor tidak digunakan ulang. Celah urutan tetap mungkin terjadi bila generator dipanggil langsung sebagai reservasi tanpa membuat record; hal ini lebih aman daripada menggunakan ulang nomor dokumen.

### 2. Duplikat KP dan KGB

Sebelum:

- duplikat hanya diperiksa pada sebagian jalur import;
- pembuatan melalui model/UI lain masih dapat menghasilkan data ganda;
- pemeriksaan aplikasi saja belum melindungi request bersamaan.

Sesudah:

- KP memakai kunci unik berdasarkan pegawai, jenis KP, dan TMT;
- KGB memakai kunci unik berdasarkan pegawai dan TMT;
- validasi model memeriksa record aktif maupun soft-deleted;
- indeks unik database menjadi perlindungan terakhir untuk request bersamaan;
- migrasi mempertahankan semua duplikat lama jika ditemukan: satu record kanonik mendapat kunci, record duplikat tidak dihapus;
- database lokal diperiksa sebelum migrasi dan tidak memiliki duplikat KP/KGB.

### 3. Sinkronisasi master pegawai

Sebelum:

- sinkronisasi pangkat hanya berjalan dari action tertentu pada tabel Filament;
- sinkronisasi KGB hanya berjalan dari tombol "Tandai Diserahkan";
- data dari import, edit biasa, seeder, atau kode backend dapat melewati sinkronisasi.

Sesudah:

- KP berstatus `sk_terbit` atau `diserahkan` menyinkronkan golongan dan TMT pangkat pada master pegawai;
- KGB berstatus `diserahkan` menyinkronkan TMT KGB terakhir;
- sinkronisasi berada di model sehingga berlaku untuk UI, import, seeder, dan backend;
- record historis yang lebih lama tidak boleh menimpa TMT master yang lebih baru;
- create, edit, import, dan transisi status mengunci record pegawai dalam transaksi pada jalur operasional utama.

### 4. Validasi backend

Validasi tidak hanya bergantung pada komponen form. Model sekarang menolak:

- tanggal surat masuk yang lebih baru dari tanggal diterima;
- perubahan tahun tanggal yang tidak konsisten dengan nomor dokumen;
- tanggal penyelesaian/pengambilan legalisir sebelum tanggal permohonan;
- biaya legalisir negatif, non-angka, atau melewati kapasitas kolom;
- jumlah lembar di luar 1–10.000;
- status dan enum yang tidak dikenal;
- periode KP selain format `YYYY-MM`;
- golongan lama dan baru KP yang sama;
- gaji negatif, non-angka, terlalu besar, atau gaji baru lebih kecil dari gaji lama;
- masa kerja di luar 0–60 tahun dan 0–11 bulan;
- TMT KGB berikutnya yang tidak berada setelah TMT saat ini;
- NIP invalid atau penggunaan ulang NIP dari record soft-deleted.

Validasi import kepegawaian disamakan dengan batas backend tersebut.

`SampleDataSeeder` sekarang hanya berjalan pada lingkungan local/testing, menggunakan transaksi, menormalkan NIP tanpa spasi, dan aman dijalankan ulang tanpa menggandakan data. Data contoh tidak lagi dimasukkan ke production.

## Migrasi database lokal

Sebelum migrasi dibuat backup:

`storage/app/backups/backup-tata_usaha_db-2026-10-19_11-15-54.sql`

Backup berhasil dibuat dengan ukuran sekitar 47,82 KB. Restore backup belum diuji karena pengujian restore terisolasi termasuk ruang lingkup V4.5.

Migrasi yang dijalankan:

`2026_10_19_000001_enforce_data_integrity`

Hasil verifikasi setelah migrasi:

- KP: 2 record, 2 record memiliki integrity key;
- KGB: 2 record, 2 record memiliki integrity key;
- tidak ada data KP/KGB yang dihapus;
- status migrasi: `Ran`, batch 4.

## File utama

- `app/Support/DocumentSequence.php`
- `database/migrations/2026_10_19_000001_enforce_data_integrity.php`
- `app/Models/SuratMasuk.php`
- `app/Models/SuratKeluar.php`
- `app/Models/Legalisir.php`
- `app/Models/Pegawai.php`
- `app/Models/KenaikanPangkat.php`
- `app/Models/GajiBerkala.php`
- halaman create/edit dan Resource Filament terkait;
- `app/Services/TuDataExchange.php`
- `app/Services/TuPersonnelExchange.php`
- `tests/Feature/DataIntegrityTest.php`
- `tests/Feature/WorkspaceDashboardTest.php`

## Hasil validasi

- `php artisan test`: **51 test lulus, 316 assertion**.
- lint seluruh PHP pada `app`, `config`, `database`, `routes`, dan `tests`: **lulus**.
- `composer validate --no-check-publish`: **lulus**.
- `npm run build`: **lulus**.

Peringatan schema XML PHPUnit yang deprecated masih ada dan tidak menggagalkan test. Perubahan konfigurasi PHPUnit lebih aman disatukan dengan upgrade framework/toolchain terpisah.

## Status

Implementasi V4.3 selesai dan telah diterapkan ke database lokal. Langkah roadmap berikutnya adalah V4.4 Export & Import Finalization.
