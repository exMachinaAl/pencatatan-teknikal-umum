# PB-01 — Integritas Kenaikan Pangkat

Tanggal penerapan: 10 November 2026  
Status: selesai di source dan sudah diterapkan ke database lokal; deployment customer belum dilakukan

## Perilaku yang dipertahankan

- Form KP tetap memakai pegawai, jenis KP, golongan lama, golongan tujuan, masa kerja, dan tautan lampiran opsional.
- TMT tetap diminta ketika status dilanjutkan menjadi **SK Terbit**.
- Golongan master pegawai tetap berubah otomatis ketika TMT sudah berlaku.
- Pengajuan masa depan tidak mengubah golongan sebelum waktunya.
- Scheduler tetap menjalankan `app:sync-effective-promotions` setiap hari.

## Aturan integritas baru

1. Satu pegawai hanya boleh memiliki satu pengajuan KP untuk satu TMT, termasuk bila jenis KP berbeda.
2. Golongan tujuan harus memiliki urutan lebih tinggi daripada golongan lama.
3. Saat TMT diterapkan, golongan lama pada pengajuan harus sesuai dengan golongan master pegawai.
4. Jika record historis tidak mempunyai golongan lama, perubahan hanya diterapkan bila golongan master saat ini memiliki urutan lebih rendah dari target.
5. Pengajuan dengan TMT yang lebih lama tidak dapat menimpa riwayat master yang lebih baru.
6. Sinkronisasi diproses menurut TMT lalu ID sehingga beberapa kenaikan berurutan dapat selesai dalam satu siklus.
7. Menjalankan sinkronisasi kembali tidak menghasilkan perubahan tambahan.

Model memberi pesan validasi yang dapat dipahami pengguna. Kolom unik `decision_key` menjadi lapisan database untuk menahan dua request bersamaan yang sama-sama melewati pengecekan awal.

## Pemeriksaan data sebelum deployment

Migration baru melakukan pemeriksaan ini secara otomatis. Pemeriksaan manual MySQL dapat dilakukan dengan query baca-saja berikut:

```sql
SELECT pegawai_id, tmt, COUNT(*) AS jumlah
FROM kenaikan_pangkat
WHERE tmt IS NOT NULL
GROUP BY pegawai_id, tmt
HAVING COUNT(*) > 1;
```

Jika tidak ada hasil, migration dapat menambahkan dan mengisi `decision_key`.

Jika ada hasil, migration berhenti sebelum membuat kolom baru. Periksa dokumen dan pegawai terkait bersama petugas kepegawaian. Tentukan TMT yang benar atau gabungkan data yang memang merupakan pengajuan sama. Jangan memilih record berdasarkan ID secara otomatis karena aplikasi tidak mengetahui keputusan administrasi yang sah.

Record soft-deleted tetap dihitung sebagai riwayat agar keputusan pada pegawai dan TMT yang sama tidak dibuat ulang tanpa pemeriksaan.

## Deployment

Sebelum migration:

```powershell
php artisan app:backup-database
php artisan migrate:status
```

Sesudah backup terverifikasi:

```powershell
php artisan migrate --force
php artisan optimize:clear
php artisan test --filter="KenaikanPangkat|DataIntegrity|BusinessProcess"
```

Jangan menjalankan `migrate:fresh` pada database customer.

## Verifikasi operasional

- [ ] Pengajuan KP biasa dapat dibuat.
- [ ] Golongan tujuan yang lebih rendah ditolak.
- [ ] Pengajuan jenis berbeda dengan pegawai dan TMT sama ditolak.
- [ ] SK Terbit dengan TMT masa depan belum mengubah master pegawai.
- [ ] Setelah TMT tercapai, scheduler mengubah master ke golongan tujuan.
- [ ] Menjalankan scheduler dua kali tidak mengubah master untuk kedua kalinya.
- [ ] Pengajuan dengan golongan lama yang tidak cocok tidak mengubah master.
- [ ] Tabel KP menampilkan status “Sudah diterapkan” setelah sinkronisasi.

## Pengujian source

- Tes fokus aturan baru: **7 tes, 31 assertion**, lulus.
- Tes fokus KP dan integritas: **20 tes, 94 assertion**, lulus sebelum penambahan fallback historis.
- Seluruh suite setelah perubahan akhir: **106 tes, 838 assertion**, lulus.
- Probe konflik migration memakai SQLite `:memory:`: konflik ditolak dan kolom `decision_key` belum dibuat.

Semua pengujian otomatis dan probe konflik memakai database memori. Setelah pengujian selesai, migration diterapkan secara terpisah ke database lokal pada 10 November 2026. Pemeriksaan lokal menemukan 3 record KP, tanpa konflik pegawai+TMT dan tanpa record final yang kehilangan golongan lama. Satu record bertanggal berhasil memperoleh `decision_key`. Tidak ada data KP yang dihapus. Database customer belum diubah.

## Rollback source dan database

Jika migration ini merupakan migration terakhir yang diterapkan, rollback database dapat dilakukan dengan:

```powershell
php artisan migrate:rollback --step=1 --force
```

Rollback menghapus unique index dan kolom `decision_key`; data pengajuan tidak dihapus. Source model dan command harus dikembalikan melalui commit PB-01 yang sama agar kode dan skema tetap selaras.
