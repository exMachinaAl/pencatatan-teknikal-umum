# Checklist Seeder dan Otomasi Kenaikan Pangkat

Tanggal pemeriksaan: 26 Oktober 2026

## 1. Ringkasan perubahan

- `UserSeeder` sekarang menyediakan seluruh role aplikasi: administrator, kepala TU, kepala sekolah, petugas TU, petugas kepegawaian, auditor, dan pegawai.
- Password fallback pada lingkungan lokal/testing tetap `password`.
- Production wajib mengisi password awal berbeda melalui environment variable dan minimal 12 karakter.
- `SampleDataSeeder` hanya berjalan pada lingkungan `local` atau `testing`.
- Data contoh memakai field yang masih tampil pada formulir terbaru. Field lama seperti periode KP, nomor SK pada form awal, nominal gaji lama/baru, penandatangan surat, dan biaya legalisir tidak lagi dijadikan data utama contoh.
- Seeder dapat dijalankan berulang tanpa menggandakan transaksi contoh.
- Tersedia dua akun Petugas TU dan dua akun Kepegawaian pada data lokal untuk menguji transaksi antar akun dengan role yang sama.
- Akun `pegawai` pada data lokal dihubungkan ke data pegawai sehingga login menggunakan NIP dapat diuji.
- Kenaikan pangkat dengan SK terbit dan TMT masa depan tidak lagi mengubah golongan sebelum waktunya.
- Scheduler harian menerapkan golongan baru saat TMT sudah berlaku.

## 2. Daftar akun hasil seeder

### Akun inti

| Username | Role | Lingkungan | Password lokal/testing |
|---|---|---|---|
| `admin` | Administrator Sistem | Semua | `password` |
| `kepala_tu` | Kepala Tata Usaha | Semua | `password` |
| `kepala_sekolah` | Kepala Sekolah | Semua | `password` |
| `staf_tu` | Petugas TU | Semua | `password` |
| `kepegawaian` | Petugas Kepegawaian | Semua | `password` |
| `auditor` | Auditor/Pengawas | Semua | `password` |
| `pegawai` | Pegawai/Pemohon | Semua | `password` |

### Akun tambahan data contoh

| Username | Role | Lingkungan | Password |
|---|---|---|---|
| `staf_tu_2` | Petugas TU | Local/testing saja | `password` |
| `kepegawaian_2` | Petugas Kepegawaian | Local/testing saja | `password` |

Jangan menggunakan password `password` pada production. Seeder production akan menolak password kosong, password kurang dari 12 karakter, atau password awal yang sama antar akun inti.

## 3. Persiapan sebelum menjalankan seeder

- [ ] Pastikan backup database terbaru tersedia dan dapat dibuka.
- [ ] Pastikan `.env` menunjuk ke database yang benar.
- [ ] Periksa `APP_ENV`; gunakan `local` untuk data demonstrasi dan `production` untuk data riil.
- [ ] Pastikan `APP_DEBUG=false` pada production.
- [ ] Jalankan `php artisan migrate:status` dan pastikan tidak ada migration gagal.
- [ ] Pastikan tabel `users`, `pegawai`, `ref_golongan`, `ref_klasifikasi_surat`, `kenaikan_pangkat`, `gaji_berkala`, `surat_masuk`, `surat_keluar`, dan `legalisir` tersedia.
- [ ] Pada production, isi tujuh password awal berikut dengan nilai unik minimal 12 karakter:
  - `TU_ADMIN_SISTEM_INITIAL_PASSWORD`
  - `TU_KEPALA_TU_INITIAL_PASSWORD`
  - `TU_KEPALA_SEKOLAH_INITIAL_PASSWORD`
  - `TU_PETUGAS_TU_INITIAL_PASSWORD`
  - `TU_KEPEGAWAIAN_INITIAL_PASSWORD`
  - `TU_AUDITOR_INITIAL_PASSWORD`
  - `TU_PEGAWAI_INITIAL_PASSWORD`
- [ ] Jalankan `php artisan config:clear` setelah mengubah `.env`.

## 4. Cara menjalankan

Untuk pengembangan lokal:

```powershell
php artisan migrate
php artisan db:seed
```

Untuk membuat ulang database pengembangan dari nol (menghapus seluruh data lokal):

```powershell
php artisan migrate:fresh --seed
```

Perintah `migrate:fresh` dilarang digunakan pada production karena menghapus seluruh tabel dan data.

Untuk production:

```powershell
php artisan migrate --force
php artisan db:seed --force
```

Pada production, `SampleDataSeeder` otomatis dilewati. Seeder hanya menambahkan referensi, pengaturan awal, dan akun inti yang belum ada. Password, role, dan status aktif akun lama tidak ditimpa.

## 5. Pemeriksaan hasil seeder

### Referensi dan konfigurasi

- [ ] Menu golongan memiliki 20 referensi: 17 golongan PNS dan 3 golongan PPPK.
- [ ] Kode golongan tidak ganda.
- [ ] Urutan golongan meningkat sesuai tingkat.
- [ ] Klasifikasi surat memiliki 10 kode dasar.
- [ ] Kode `800` tersedia untuk kepegawaian.
- [ ] Kode `421.5` tersedia untuk ijazah/legalisir.
- [ ] Pengaturan nama sekolah, alamat, telepon, email, kepala sekolah, NIP kepala sekolah, interval KP, dan interval KGB tersedia.
- [ ] Seeder ulang tidak mengubah pengaturan yang sebelumnya sudah dikustomisasi operator.

### Akun dan hak akses

- [ ] Local/testing memiliki sembilan akun; production memiliki tujuh akun inti.
- [ ] Semua username unik.
- [ ] Semua email akun unik.
- [ ] `admin` hanya mengelola akun, pengaturan, backup, dan audit serta membaca layanan operasional.
- [ ] `kepala_tu` dapat mengelola seluruh modul operasional TU.
- [ ] `staf_tu` dan `staf_tu_2` hanya mengelola persuratan serta legalisir.
- [ ] `kepegawaian` dan `kepegawaian_2` hanya mengelola pegawai, KP, dan KGB.
- [ ] `kepala_sekolah` bersifat baca saja sesuai matriks izin.
- [ ] `auditor` bersifat baca saja dan dapat melihat audit.
- [ ] Akun nonaktif tetap nonaktif setelah seeder dijalankan ulang.
- [ ] Password yang sudah pernah diganti tidak kembali menjadi password awal.
- [ ] Login menggunakan username berhasil.
- [ ] Login menggunakan email berhasil.
- [ ] Pada data lokal, login akun `pegawai` menggunakan NIP `199004122018011003` berhasil.
- [ ] Segera ganti seluruh password awal setelah deployment pertama.

### Data pegawai

- [ ] Data contoh hanya membutuhkan nama, NIP, status kepegawaian, dan golongan.
- [ ] NIP tersimpan tanpa spasi.
- [ ] NIP tidak ganda, termasuk terhadap data soft-delete.
- [ ] PNS memiliki golongan PNS yang sesuai.
- [ ] PPPK menggunakan golongan PPPK.
- [ ] Honorer dapat tidak memiliki NIP dan golongan.
- [ ] Satu data pegawai maksimal terhubung dengan satu akun login.

### Kenaikan pangkat

- [ ] Tabel memuat contoh status `Berkas Disiapkan`, `Diajukan`, dan `SK Terbit`.
- [ ] Data contoh menggunakan pegawai, jenis, golongan lama, golongan baru, masa kerja, serta tautan opsional.
- [ ] Periode pengajuan dan nomor SK lama tidak ikut diisi oleh seeder baru.
- [ ] Pengajuan tidak dapat melompat langsung dari `Berkas Disiapkan` ke `SK Terbit`.
- [ ] Ketika berpindah dari `Diajukan` ke `SK Terbit`, TMT wajib diisi.
- [ ] Golongan lama dan golongan baru tidak boleh sama.
- [ ] Pengajuan dengan kombinasi pegawai, jenis, dan masa kerja identik ditolak sebagai duplikat.
- [ ] Identitas `created_by` pada data contoh berasal dari akun kepegawaian yang berbeda.

### Gaji berkala

- [ ] Data contoh hanya menggunakan pegawai, golongan, masa kerja tahun/bulan, tautan opsional, dan status.
- [ ] Nominal gaji lama/baru, nomor SK, dan TMT lama tidak diisi oleh seeder baru.
- [ ] Tersedia contoh status `Diproses` dan `SK Terbit`.
- [ ] Data dengan pegawai, golongan, dan masa kerja identik tidak dapat dibuat dua kali.
- [ ] Masa kerja bulan hanya 0 sampai 11.
- [ ] Masa kerja tahun hanya 0 sampai 60.

### Surat masuk dan disposisi

- [ ] Nomor agenda surat masuk dihasilkan otomatis.
- [ ] Tanggal surat tidak melewati tanggal diterima.
- [ ] Kode klasifikasi dapat dicari.
- [ ] Lokasi arsip fisik tampil dan dapat dicari.
- [ ] Tautan Google Drive/Sheets tampil jika tersedia.
- [ ] Surat dengan disposisi aktif otomatis berstatus `Didisposisikan`.
- [ ] Identitas pembuat surat dan pembuat disposisi dapat berasal dari dua akun Petugas TU berbeda.
- [ ] Surat tidak dapat diarsipkan selama disposisi belum selesai.

### Surat keluar

- [ ] Nomor surat resmi unik.
- [ ] Tahun nomor mengikuti tanggal surat bila pola nomor menyertakan tahun.
- [ ] Tersedia contoh `Draf` dan `Terkirim`.
- [ ] Lokasi arsip dan tautan dapat dibuka dari tabel.
- [ ] Penandatangan, pembuat konsep, sifat, dan status manual tidak muncul kembali pada input awal.

### Legalisir

- [ ] Tersedia contoh status `Diterima` dan `Siap Diambil`.
- [ ] Biaya selalu nol pada data contoh.
- [ ] Email pemohon dan pilihan notifikasi tidak diperlukan.
- [ ] Nomor antrean dan nomor legalisir unik.
- [ ] Lokasi arsip fisik dapat ditemukan melalui tabel dan Pencarian Terpadu.
- [ ] Status `Siap Diambil` hanya valid jika tiga checklist verifikasi lengkap.
- [ ] Petugas penerima dan petugas verifikasi tercatat berdasarkan akun.
- [ ] Tautan lampiran tampil hanya ketika tersedia.

### Idempotensi dan transaksi multi-user

- [ ] Jalankan `php artisan db:seed` dua kali.
- [ ] Jumlah pegawai, KP, KGB, surat masuk, surat keluar, dan legalisir tidak bertambah pada eksekusi kedua.
- [ ] Tidak muncul error unique constraint.
- [ ] Password, role, dan status akun lama tidak berubah.
- [ ] Perubahan manual pada data operasional riil tidak dihapus oleh seeder.
- [ ] Dua akun dengan role sama dapat membuat transaksi berbeda.
- [ ] Audit log menyimpan user ID, nama, username, dan role pelaku.
- [ ] Optimistic lock menolak penyimpanan form lama setelah akun lain memperbarui record yang sama.

## 6. Cara kerja otomatisasi pangkat

Otomatisasi yang aman bukan “setelah empat tahun langsung naik pangkat”. Empat tahun hanya dapat dipakai sebagai indikator calon/kelayakan awal. Kenaikan resmi masih bergantung pada pengajuan, verifikasi, persetujuan, SK, dan TMT.

Alur yang diterapkan:

1. Petugas membuat pengajuan dengan input ringkas.
2. Status dilanjutkan dari `Berkas Disiapkan` ke `Diajukan`.
3. Saat status dilanjutkan ke `SK Terbit`, sistem meminta TMT pangkat baru.
4. Jika TMT masih di masa depan, golongan master pegawai belum berubah.
5. Scheduler menjalankan `app:sync-effective-promotions` setiap hari pukul 00:15.
6. Pada atau setelah TMT, golongan master berubah otomatis ke golongan baru.
7. TMT pangkat terakhir ikut diperbarui.
8. Eksekusi ulang aman dan tidak menggandakan perubahan.
9. Riwayat lama tidak dapat menimpa riwayat pangkat yang memiliki TMT lebih baru.

### Pemeriksaan scheduler

- [ ] Jalankan `php artisan schedule:list` dan pastikan `app:sync-effective-promotions` muncul pada pukul 00:15.
- [ ] Jalankan `php artisan app:sync-effective-promotions` secara manual; perintah harus selesai tanpa error.
- [ ] Jalankan kembali perintah; hasil kedua harus menunjukkan nol perubahan jika tidak ada TMT baru.
- [ ] Pastikan server menjalankan Laravel scheduler setiap menit.

Contoh Windows Task Scheduler:

```text
Program: C:\path\ke\php.exe
Argumen: artisan schedule:run
Start in: C:\path\ke\project
Pengulangan: setiap 1 menit
```

Tanpa Windows Task Scheduler/cron, Laravel tidak dapat menjalankan perubahan tepat waktu secara otomatis.

### Mengapa tidak langsung otomatis setelah empat tahun?

- Masa empat tahun bukan satu-satunya syarat administratif.
- Kenaikan pilihan dapat mengikuti aturan jabatan fungsional yang berbeda.
- Pengajuan dapat ditolak, dikembalikan, atau memiliki TMT berbeda dari tanggal kelayakan.
- Mengubah golongan tanpa SK/TMT dapat membuat data sekolah berbeda dari dokumen kepegawaian resmi.
- Perubahan golongan memengaruhi data sensitif lain dan harus dapat diaudit.

Saran “otomatis” tetap masuk akal bila dibagi menjadi dua bagian:

- otomatis mendeteksi calon dan mengingatkan petugas berdasarkan interval; dan
- otomatis menerapkan golongan hanya setelah SK terbit dan TMT berlaku.

Bagian kedua sudah diterapkan. Bagian pertama memerlukan TMT pangkat terakhir sebagai data dasar yang konsisten. Karena field tersebut sebelumnya diminta dihapus dari input pegawai, sistem belum boleh menebak tanggal kelayakan hanya dari masa kerja pengajuan.

## 7. Pemeriksaan teknis sebelum serah terima

- [ ] `php artisan test --do-not-cache-result` lulus seluruhnya.
- [ ] `vendor\bin\pint --test` lulus.
- [ ] `npm run build` berhasil.
- [ ] `php artisan view:cache` berhasil.
- [ ] `php artisan route:list` berhasil.
- [ ] `php artisan schedule:list` berhasil.
- [ ] `git diff --check` tidak menampilkan whitespace error.
- [ ] Backup database dibuat sebelum deployment.
- [ ] Uji restore backup dilakukan pada database terpisah.
- [ ] Akun awal production mengganti password saat serah terima.
- [ ] Operator memahami bahwa data contoh tidak pernah dimasukkan pada production.
- [ ] Admin server memastikan scheduler, backup harian, ruang disk, dan log aplikasi dipantau.
