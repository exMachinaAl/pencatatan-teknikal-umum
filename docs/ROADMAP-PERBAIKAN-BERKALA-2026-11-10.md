# Roadmap Perbaikan Berkala Aplikasi TU

Tanggal audit: 10 November 2026 (Asia/Jakarta)  
Branch: `optimize/tu-small-scale`  
Revisi dasar: `9ccfbb0` (`fix: dukung deployment subdomain dan base path`)  
Status dokumen: aktif, diperbarui setiap tahap perbaikan

## Tujuan dokumen

Dokumen ini menjadi catatan utama hasil pemeriksaan kondisi aplikasi dan daftar perbaikan bertahap. Gunakan ID temuan, misalnya `PB-01`, saat mencari kode, membuat commit, atau menulis hasil pengujian.

Aplikasi tidak perlu dibuat ulang. Fondasi Laravel, Filament, pembatasan akses per role, audit berdasarkan akun, lampiran privat, dan perlindungan edit bersamaan sudah cukup baik untuk operasional TU skala kecil. Fokus berikutnya adalah menjaga integritas data, memperbarui dependensi, menyederhanakan pengalaman pengguna, lalu mengoptimalkan berdasarkan pengukuran.

## Kondisi dasar saat audit

- Laravel `12.70.1`, Filament `3.3.57`, dan Livewire `3.8.11`.
- Seluruh tes bawaan lulus: **97 tes, 814 assertion** dalam **3 menit 7,58 detik**.
- Tes dijalankan memakai SQLite `:memory:`. Database aplikasi dan `.env` tidak diubah.
- Bootstrap CLI `php artisan --version` terukur sekitar **0,65-0,81 detik** pada dua eksekusi.
- PHP CLI lokal tidak memuat OPcache; Xdebug juga tidak aktif.
- `composer audit --locked` menemukan **11 advisori pada 6 paket**.
- `npm audit` menemukan **1 advisori high** pada dependensi transitif `follow-redirects`; pembaruan tersedia.
- Worktree sudah memiliki perubahan lama pada `.phpunit.result.cache`. File tersebut bukan bagian dari audit maupun roadmap ini.

Kelulusan tes menunjukkan perilaku yang sudah diuji masih berjalan. Itu belum membuktikan semua kondisi bisnis aman. Audit ini menemukan satu konflik pangkat yang belum dicakup tes.

## Arti status

- `[ ] Belum`: belum dikerjakan.
- `[~] Berjalan`: sedang dikerjakan dan belum memenuhi kriteria selesai.
- `[x] Selesai`: perubahan, tes, dan catatan hasil sudah lengkap.
- `[!] Tertunda`: sengaja ditunda; alasannya wajib dicatat.

## Daftar perbaikan berdasarkan prioritas

### PB-10 — P1 — Pisahkan bootstrap admin, akun nyata, dan akun demo

Status: `[x] Selesai`

Mekanisme lama membuat akun untuk seluruh role dan mengambil tujuh password awal dari `.env`. Mekanisme tersebut mencampur instalasi operasional dengan kebutuhan demonstrasi.

Perubahan:

- [x] Seed standar hanya membuat satu Administrator Sistem pada database baru.
- [x] Password admin production diminta secara tersembunyi dan dikonfirmasi saat bootstrap interaktif.
- [x] Password admin maupun role lain tidak lagi dikonfigurasi melalui `TU_*_INITIAL_PASSWORD`.
- [x] Akun operasional lain dibuat admin melalui menu Kelola Pengguna.
- [x] Akun seluruh role dipindahkan ke `DemoUserSeeder` khusus local/testing.
- [x] Transaksi contoh dipisahkan dari `DatabaseSeeder` dan dijalankan secara eksplisit melalui `SampleDataSeeder`.
- [x] Seed ulang tidak mereset password, role, atau status akun yang sudah ada.
- [x] Seluruh test suite lulus: 100 tes, 825 assertion.

Kriteria selesai: instalasi baru memperoleh tepat satu admin, data demo hanya muncul ketika diminta, production tidak menyimpan password awal role di `.env`, dan akun lama tetap utuh.

### PB-01 — P0 — Konflik pengajuan pangkat pada TMT yang sama

Status: `[ ] Belum`

Dua pengajuan untuk pegawai yang sama dengan TMT sama tetapi jenis pengajuan berbeda dapat diterima. Jika golongan tujuan berbeda, setiap sinkronisasi dapat menerapkan pengajuan pertama lalu pengajuan kedua. Pengujian sintetis di database memori memperlihatkan golongan pegawai berganti dari tujuan pertama ke tujuan kedua pada setiap siklus.

Lokasi utama:

- `app/Models/KenaikanPangkat.php`, pembuatan `integrity_key` dan `applyEffectiveRank()`.
- `app/Console/Commands/SyncEffectivePromotions.php` atau command sinkronisasi pangkat yang aktif.
- `tests/Feature/BusinessProcessTest.php` dan `tests/Feature/DataIntegrityTest.php`.

Rencana perbaikan:

- [ ] Tetapkan aturan bahwa satu pegawai hanya memiliki satu keputusan pangkat efektif untuk satu TMT.
- [ ] Tolak pengajuan aktif yang bertentangan meskipun `jenis` berbeda.
- [ ] Pastikan golongan tujuan memiliki urutan lebih tinggi daripada golongan lama untuk proses kenaikan.
- [ ] Pastikan golongan lama sesuai dengan data master pegawai pada saat keputusan diterapkan.
- [ ] Buat sinkronisasi idempoten: eksekusi berulang tidak mengubah data lagi.
- [ ] Tambahkan tes konflik TMT sama, target berbeda, urutan golongan turun, dan sinkronisasi berulang.
- [ ] Tentukan penanganan data lama yang sudah telanjur konflik sebelum menambahkan constraint database.

Kriteria selesai: tidak ada dua keputusan yang dapat saling menimpa pada TMT sama, sinkronisasi berulang menghasilkan keadaan yang tetap, dan semua tes lama serta tes baru lulus.

### PB-02 — P1 — Pembaruan dependensi dan pemeriksaan regresi

Status: `[ ] Belum`

Audit dependensi tanggal 10 November 2026 menemukan 11 advisori Composer pada 6 paket Symfony dan satu advisori npm berlevel high pada `follow-redirects`. Salah satu versi yang terpasang adalah `symfony/http-foundation 7.4.21`; konfigurasi gateway dan pembentukan URL perlu diuji kembali setelah pembaruan.

Rencana perbaikan:

- [ ] Simpan daftar paket dan batas versi perbaikannya sebelum upgrade.
- [ ] Perbarui lock file dengan perubahan sesempit mungkin.
- [ ] Jalankan kembali `composer audit --locked` dan `npm audit`.
- [ ] Uji login, logout, session, CSRF, unduh lampiran, redirect, subdomain, base-path, dan tunnel HTTPS.
- [ ] Pastikan pembaruan tidak mengubah database atau `.env` deployment.

Kriteria selesai: advisori yang memiliki versi perbaikan sudah ditutup atau alasan penundaannya terdokumentasi; seluruh pengujian regresi lulus.

### PB-03 — P1 — Batas kepercayaan hostname pada gateway

Status: `[ ] Belum`

`app/Http/Middleware/TrustHosts.php` memasukkan hostname request ke daftar tepercaya jika request berasal dari proxy tepercaya. IP proxy sudah dibatasi melalui `config/trustedproxy.php`, tetapi keamanan hostname ikut bergantung pada validasi yang dilakukan Nginx/AppCtl. Konfigurasi server Laptop B belum termasuk ruang pemeriksaan ini.

Rencana perbaikan:

- [ ] Audit konfigurasi gateway yang benar-benar digunakan saat deployment.
- [ ] Pastikan gateway menolak atau menormalkan host yang tidak diizinkan.
- [ ] Pertahankan dukungan hostname tunnel dinamis tanpa mempercayai semua proxy jaringan.
- [ ] Tambahkan tes host tidak sah dari jalur proxy tepercaya sesuai keputusan konfigurasi.
- [ ] Lakukan uji nyata pada subdomain, base-path, dan tunnel HTTPS.

Kriteria selesai: batas tanggung jawab aplikasi dan gateway jelas, host yang tidak sah ditolak, dan tiga mode deployment tetap berjalan.

### PB-04 — P1 — Pencarian terpadu memotong hasil tanpa penjelasan

Status: `[ ] Belum`

`app/Filament/Pages/GlobalDocumentSearch.php` mengambil maksimal 12 hasil per jenis dokumen dan 30 hasil gabungan. Halaman hanya menampilkan jumlah koleksi yang tersisa sebagai “hasil ditemukan”. Arsip lama yang cocok dapat tidak muncul, sehingga pengguna dapat mengira data tidak ada.

Rencana perbaikan:

- [ ] Ganti pembatasan tersembunyi dengan pagination atau tombol “lihat semua”.
- [ ] Tampilkan total hasil sebenarnya dan jumlah yang sedang diperlihatkan.
- [ ] Urutkan hasil lintas modul berdasarkan tanggal yang konsisten.
- [ ] Pastikan pencarian lokasi arsip fisik, nomor dokumen, nama, dan kode klasifikasi tetap bekerja.
- [ ] Tambahkan tes dengan lebih dari 12 hasil pada satu modul dan lebih dari 30 hasil gabungan.

Kriteria selesai: pengguna dapat menemukan seluruh data yang cocok dan memahami apakah hasil sedang dibatasi.

### PB-05 — P2 — Konsolidasi UI tabel, ukuran teks, dan sidebar

Status: `[ ] Belum`

Tampilan saat ini memuat stylesheet utama serta tiga lapisan patch: `tu-ui-v12-safe.css`, `tu-ui-v13-table-fix.css`, dan `tu-ui-v20-operasional.css`. Banyak aturan memakai `!important`, beberapa teks sekunder pada mobile setara sekitar 10-11 px, dan hook tampilan memaksa sidebar tertutup pada setiap pemuatan halaman.

Rencana perbaikan:

- [ ] Ambil screenshot baseline pada lebar 360, 768, 1024, dan desktop dengan sidebar terbuka serta tertutup.
- [ ] Inventarisasi aturan yang saling menimpa sebelum menghapus stylesheet lama.
- [ ] Satukan token spacing, font, lebar kolom, dan aturan pembungkusan tabel.
- [ ] Pertahankan preferensi sidebar pengguna pada desktop; mobile tetap boleh tertutup secara default.
- [ ] Naikkan keterbacaan teks sekunder tanpa membuat tabel kembali renggang.
- [ ] Pastikan informasi utama dan sekunder berada dalam satu sel yang rapi pada layar sempit.
- [ ] Uji menu KP, Pencarian Terpadu, Legalisir, Surat Masuk, dan Surat Keluar.

Kriteria selesai: tidak ada teks satu huruf per baris, informasi penting tetap terbaca, tidak ada overflow tak terkendali, dan preferensi sidebar stabil.

Catatan: audit ini memeriksa source CSS, belum melakukan verifikasi visual lintas browser. Karena itu screenshot baseline wajib dibuat sebelum refactor.

### PB-06 — P2 — Sisa alur notifikasi legalisir lama

Status: `[ ] Belum`

Menu `Antrean Notifikasi` dan aksi pengiriman ulang email masih tersedia, sementara workflow legalisir telah menjadi pencatatan pasif tanpa email pemohon dan tanpa proses penolakan/notifikasi siap.

Rencana perbaikan:

- [ ] Pastikan apakah data outbox lama masih diperlukan sebagai arsip.
- [ ] Sembunyikan menu operasional yang tidak lagi memiliki alur aktif.
- [ ] Jika riwayat harus dipertahankan, jadikan read-only dan beri label sebagai data lama.
- [ ] Pastikan tidak ada scheduler atau aksi gagal yang tampak aktif tetapi tidak pernah diproses.
- [ ] Perbarui dokumentasi workflow legalisir.

Kriteria selesai: navigasi mencerminkan proses bisnis pasif yang telah disetujui dan data historis tetap aman.

### PB-07 — P2 — Optimisasi query tabel kenaikan pangkat

Status: `[ ] Belum`

Tabel kenaikan pangkat menggunakan relasi pegawai, golongan lama, dan golongan baru, tetapi query utama baru terlihat memuat `lampiran` secara eksplisit. Ini berpotensi menimbulkan query tambahan per baris.

Rencana perbaikan:

- [ ] Ukur jumlah dan waktu query pada daftar KP dengan data realistis.
- [ ] Eager-load hanya relasi yang dipakai tabel dan ekspor.
- [ ] Bandingkan sebelum/sesudah menggunakan jumlah record yang sama.
- [ ] Periksa tabel KGB dan modul dokumen dengan metode yang sama.

Kriteria selesai: jumlah query tidak bertambah linear karena relasi per baris dan tidak ada regresi pada filter, pencarian, atau ekspor.

### PB-08 — P3 — Diagnosis performa startup dan request nyata

Status: `[ ] Belum`

Bootstrap Artisan yang diperiksa tidak menunjukkan jeda satu menit. Kelambatan dapat berada pada proses development server, request pertama, koneksi database, DNS, antivirus/OneDrive, build aset, atau konfigurasi PHP. Belum cukup bukti untuk memilih satu penyebab.

Rencana perbaikan:

- [ ] Ukur terpisah waktu `artisan serve`, GET login pertama/kedua, login Livewire, dashboard, dan tabel besar.
- [ ] Catat waktu koneksi database dan query lambat.
- [ ] Bandingkan workspace OneDrive dengan checkout lokal non-sync bila pengujian perangkat memungkinkan.
- [ ] Periksa OPcache pada PHP-FPM produksi; kondisi CLI lokal bukan bukti kondisi FPM.
- [ ] Periksa log Laravel, PHP-FPM, dan Nginx pada request yang lambat.
- [ ] Optimalkan hanya bottleneck yang terukur.

Kriteria selesai: sumber waktu tunggu diketahui melalui angka sebelum/sesudah, bukan perkiraan.

### PB-09 — P2 — Pengujian deployment browser dan CSRF nyata

Status: `[ ] Belum`

Tes base-path saat ini sudah memeriksa URL HTML, endpoint Livewire, session, dan alur autentikasi pada tingkat feature test. Namun aset belum benar-benar dimuat oleh browser, dan middleware CSRF Laravel melewati pemeriksaan token saat unit test berjalan.

Rencana perbaikan:

- [ ] Tambahkan pengujian browser untuk memuat CSS/JS dan mengirim form Livewire.
- [ ] Uji token CSRF salah atau hilang pada lingkungan yang tidak melewati middleware.
- [ ] Uji pergantian subdomain dan base-path pada instance yang sama.
- [ ] Uji login, redirect, navigasi, logout, cookie secure, dan endpoint POST pada tunnel HTTPS.
- [ ] Simpan checklist smoke test manual untuk deployment customer.

Kriteria selesai: login dan navigasi terbukti berjalan melalui browser nyata pada seluruh mode, bukan hanya melalui pemeriksaan string respons.

## Urutan tahap yang disepakati

1. **Tahap 0:** PB-10 — mekanisme akun dan seeder, diprioritaskan ulang atas arahan pemilik aplikasi.
2. **Tahap 1:** PB-01 — integritas kenaikan pangkat.
3. **Tahap 2:** PB-02 dan PB-03 — dependensi serta batas keamanan gateway.
4. **Tahap 3:** PB-04 dan PB-06 — konsistensi proses bisnis dan pencarian.
5. **Tahap 4:** PB-05 — konsolidasi UI berdasarkan screenshot baseline.
6. **Tahap 5:** PB-07 dan PB-08 — optimisasi berbasis pengukuran.
7. **Tahap 6:** PB-09 — verifikasi deployment browser dan checklist rilis.

Satu tahap sebaiknya memiliki commit sendiri. Jangan mencampur upgrade dependensi, perubahan bisnis, dan refactor UI dalam satu commit karena akan menyulitkan pengujian dan rollback.

## Prosedur setiap tahap

- [ ] Catat kondisi dan tes yang relevan sebelum perubahan.
- [ ] Terapkan perubahan sekecil mungkin.
- [ ] Tambahkan tes untuk bug yang benar-benar ditemukan.
- [ ] Jalankan tes fokus, kemudian seluruh test suite jika perubahan menyentuh model, middleware, atau dependensi.
- [ ] Periksa `git diff` agar `.env`, database lokal, cache tes, dan file pengguna tidak ikut commit.
- [ ] Isi log pelaksanaan di bawah dengan tanggal, commit, hasil tes, dan risiko tersisa.
- [ ] Ubah status menjadi `[x] Selesai` hanya setelah kriteria selesai terpenuhi.

## Log pelaksanaan

| Tanggal | ID | Commit | Hasil tes | Catatan |
|---|---|---|---|---|
| 2026-11-10 | Baseline | `9ccfbb0` | 97 tes, 814 assertion lulus | Audit dan reproduksi konflik PB-01 dengan database memori; belum ada perubahan perilaku aplikasi. |
| 2026-11-10 | PB-10 | Belum di-commit | 100 tes, 825 assertion lulus | Seed standar hanya membuat admin; akun demo dan data contoh dipisahkan; password awal role dihapus dari konfigurasi environment. |

## Dokumen terkait

- [Audit keamanan dan kesiapan multi-pengguna](AUDIT-KEAMANAN-2026-10-25.md)
- [Pembaruan keamanan](PEMBARUAN-KEAMANAN-2026-10-25.md)
- [Baseline sebelum pencatatan pasif](BASELINE-SEBELUM-PENCATATAN-PASIF-2026-11-07.md)
- [Workflow persuratan dan legalisir](WORKFLOW-PERSURATAN-LEGALISIR.md)
- [Deployment pencatatan pasif](DEPLOY-PENCATATAN-PASIF-2026-11-07.md)
- [Performa dan subpath TU](PERFORMA-DAN-SUBPATH-TU.md)

