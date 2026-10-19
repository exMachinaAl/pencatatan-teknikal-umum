# Penerapan Struktur Role Organisasi

Tanggal penerapan: 19 Oktober 2026  
Metode: diterapkan langsung pada source code, `.env`, dan database lokal tanpa upgrader.

## Keputusan struktur

`admin` dan `kepala_tu` adalah dua fungsi yang berbeda:

| Kode role | Nama tampilan | Posisi organisasi | Tanggung jawab |
| --- | --- | --- | --- |
| `admin` | Administrator Sistem (Fasilitator TI) | Divisi IT di luar hierarki operasional TU | Akun, konfigurasi, backup, audit teknis, dan pemantauan baca-saja. |
| `kepala_tu` | Kepala Tata Usaha (Pimpinan TU) | Pimpinan tertinggi di unit Tata Usaha | Seluruh proses operasional surat, legalisir, kepegawaian, transisi status, impor/ekspor, data gaji, dan audit. |
| `kepala_sekolah` | Kepala Sekolah | Pimpinan sekolah, terpisah dari Kepala TU | Pemantauan baca-saja dan cetak legalisir; tidak mengubah record operasional langsung. |

Dengan struktur ini, istilah "fasilitator" merujuk pada fasilitator teknis dari Divisi IT, bukan pimpinan Tata Usaha. Kode database `admin` dipertahankan agar integrasi lama tidak rusak, tetapi seluruh label dan dokumentasi aktif memakai istilah Administrator Sistem.

Pemisahan tugas sengaja dipertahankan:

- Administrator Sistem dapat mengelola pengguna dan infrastruktur, tetapi tidak memutasi data operasional atau melihat nominal gaji.
- Kepala TU dapat memimpin dan mengubah seluruh pekerjaan operasional TU, tetapi tidak mengelola akun sistem, konfigurasi teknis, atau backup.
- Hak menghapus data operasional tidak otomatis diberikan kepada Kepala TU karena arsip administratif harus tetap dilindungi.

## Akun awal yang diterapkan

| Email | Role |
| --- | --- |
| `admin@tu.sekolah.sch.id` | `admin` |
| `kepala.tu@tu.sekolah.sch.id` | `kepala_tu` |
| `kepala@tu.sekolah.sch.id` | `kepala_sekolah` |
| `staf@tu.sekolah.sch.id` | `petugas_tu` |
| `pegawai@tu.sekolah.sch.id` | `pegawai` |

Akun Kepala TU ditambahkan karena sebelumnya belum ada pengguna dengan role `kepala_tu`. Nama akun admin lama diperbarui dari "Operator TU (Admin)" menjadi "Administrator Sistem (Divisi IT)".

## Konfigurasi `.env`

Lima variabel berikut telah ditambahkan ke `.env` dengan nilai acak unik 36 karakter dan tidak ditampilkan pada log/laporan:

- `TU_ADMIN_SISTEM_INITIAL_PASSWORD`
- `TU_KEPALA_TU_INITIAL_PASSWORD`
- `TU_KEPALA_SEKOLAH_INITIAL_PASSWORD`
- `TU_PETUGAS_TU_INITIAL_PASSWORD`
- `TU_PEGAWAI_INITIAL_PASSWORD`

`.env.example`, `config/app.php`, dan `UserSeeder` sudah memakai nama baru tersebut. Nama environment lama tetap menjadi fallback konfigurasi untuk kompatibilitas deployment lama, tetapi tidak lagi didokumentasikan sebagai konfigurasi utama.

Empat akun lama diperiksa dan semuanya masih memakai password default `password`. Hanya keempat password default tersebut yang dirotasi ke nilai `.env`; password yang sudah pernah diganti tidak akan ditimpa oleh seeder. Akun Kepala TU baru langsung dibuat dengan password dari `.env`. Remember token kelima akun dibersihkan.

## Migrasi dan backup

Backup sebelum perubahan data:

`storage/app/backups/backup-tata_usaha_db-2026-10-19_11-23-23.sql`

Migrasi yang diterapkan:

`2026_10_19_000002_align_organizational_roles` — status `Ran`, batch 5.

Migrasi hanya mengganti nama akun seeded admin jika email, role, dan nama lamanya cocok. Nama akun admin yang telah dikustomisasi tidak akan ditimpa.

## Pengamanan dan kompatibilitas

- `UserSeeder` tetap tidak mengubah password, role, atau status akun yang sudah ada.
- Role key `admin`, `kepala_tu`, dan role lainnya tidak diubah.
- Alias role lama `staf` dan `kepala` tetap didukung selama masa kompatibilitas.
- Permission backend tetap bersumber dari satu matriks pada model `User`.
- Test membuktikan Administrator Sistem dapat mengelola akun tetapi tidak mengubah surat, sedangkan Kepala TU berlaku sebaliknya dan dapat memimpin modul operasional.

## Hasil validasi

- `php artisan test`: **52 test lulus, 325 assertion**.
- lint seluruh file PHP aplikasi: **lulus**.
- `composer validate --no-check-publish`: **lulus**.
- `npm run build`: **lulus**.
