# Mekanisme Akun dan Seeder

Tanggal berlaku: 10 November 2026

## Prinsip

Instalasi operasional dimulai dengan satu akun Administrator Sistem. Administrator kemudian membuat akun nyata melalui menu **Kelola Pengguna** dan memilih role yang sesuai. Password akun tidak disimpan sebagai kumpulan variabel `.env`.

Administrator Sistem berfungsi mengelola akun dan infrastruktur. Akun ini bukan pengganti Kepala TU dan tidak memperoleh kewenangan operasional Kepala TU.

## Seeder operasional

Perintah berikut membuat referensi golongan, klasifikasi surat, pengaturan awal, dan satu akun administrator:

```powershell
php artisan db:seed
```

Pada local/testing:

- username: `admin`
- password development: `password`
- nilai dapat diperiksa pada konstanta `UserSeeder::LOCAL_TEST_PASSWORD`

Pada production baru, `UserSeeder` meminta password admin secara tersembunyi sebanyak dua kali. Password wajib minimal 12 karakter dan tidak boleh sama dengan password development. Jika proses production dijalankan noninteraktif dan belum ada admin, seeder berhenti tanpa membuat akun dengan password yang tidak diketahui.

Gunakan perintah interaktif berikut jika bootstrap admin belum selesai:

```powershell
php artisan db:seed --class=UserSeeder --force
```

Menjalankan seeder ulang tidak mengubah password, role, atau status akun yang sudah ada. Seeder hanya melengkapi kolom identitas admin lama yang masih kosong.

## Pembuatan akun nyata

Setelah administrator berhasil login:

1. Buka **Pengaturan & Utilitas → Kelola Pengguna**.
2. Pilih **Buat Pengguna Sistem**.
3. Isi nama, username, role, status aktif, dan password unik minimal 12 karakter.
4. Hubungkan ke data pegawai bila login menggunakan NIP diperlukan.
5. Sampaikan kredensial kepada pemilik akun melalui jalur internal yang aman.

Email bersifat opsional. Username wajib unik dan tidak boleh seluruhnya berupa angka agar tidak bertabrakan dengan NIP.

## Akun dan data demo

Akun seluruh role tidak dibuat oleh seed standar. Untuk pengembangan atau demonstrasi, jalankan:

```powershell
php artisan db:seed --class=SampleDataSeeder
```

`SampleDataSeeder` memanggil `DemoUserSeeder`, lalu membuat pegawai dan transaksi contoh. Keduanya hanya berjalan pada environment `local` atau `testing`. Password demo didefinisikan oleh `DemoUserSeeder::DEMO_PASSWORD` dan tidak boleh dipakai pada production.

Untuk membuat akun demo tanpa transaksi contoh:

```powershell
php artisan db:seed --class=DemoUserSeeder
```

## Pemeriksaan setelah instalasi

- [ ] Seed standar menghasilkan tepat satu akun dengan role Administrator Sistem pada database baru.
- [ ] Login admin berhasil.
- [ ] Admin dapat membuka menu Kelola Pengguna.
- [ ] Admin dapat membuat akun role lain dengan password unik.
- [ ] Menjalankan seed ulang tidak mereset password admin.
- [ ] Akun nonaktif tidak dapat login.
- [ ] `DemoUserSeeder` dan `SampleDataSeeder` tidak berjalan pada production.
- [ ] Tidak ada variabel `TU_*_INITIAL_PASSWORD` pada `.env` atau `.env.example`.

## Data yang sudah ada

Perubahan mekanisme seed tidak menghapus atau menonaktifkan akun yang sudah tersimpan. Akun Kepala TU, petugas, kepegawaian, auditor, dan pegawai yang sudah ada tetap dapat digunakan. Pemisahan ini hanya mengubah perilaku seed untuk instalasi baru dan pengujian berikutnya.
