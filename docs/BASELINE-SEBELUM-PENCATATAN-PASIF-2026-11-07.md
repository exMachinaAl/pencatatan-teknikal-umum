# Baseline Sebelum Penyederhanaan Pencatatan Pasif TU

Tanggal pencatatan: 7 November 2026  
Branch pengembangan: `optimize/tu-small-scale`  
Commit sumber: `f6508ec` (`fix bug UI`)  
Tag rollback: `pre-passive-workflow-2026-11-07`  
Tag versi demo customer: `v0.9.0-customer-demo` (`adcf254`)

## Tujuan dokumen

Dokumen ini merekam kondisi aplikasi sebelum penyederhanaan besar. Arah baru aplikasi adalah buku pencatatan digital/cloud untuk tim TU kecil. Surat dan legalisir menjadi pencatatan pasif; workflow dipertahankan hanya pada Kenaikan Pangkat dan Gaji Berkala yang memang memiliki tahapan nyata.

## Hasil verifikasi baseline

- Seluruh 18 migration berstatus `Ran`.
- Seluruh 85 test lulus dengan 682 assertion.
- Build Vite berhasil: 59 modul ditransformasikan.
- Scheduler aktif:
  - sinkronisasi TMT kenaikan pangkat setiap hari pukul 00:15;
  - backup database setiap hari pukul 01:30.
- Working tree bersih sebelum perubahan.
- Branch `main` dan `origin/main` tetap menunjuk ke `f6508ec`.

## Kondisi modul sebelum perubahan

### Surat Masuk

Input utama:

- nomor agenda otomatis;
- tanggal diterima;
- nomor dan tanggal surat;
- pengirim;
- kode klasifikasi;
- lokasi arsip fisik;
- softfile;
- tautan eksternal.

Workflow lama yang masih aktif:

- status `baru`, `didisposisi`, `selesai`, `diarsipkan`;
- repeater disposisi di form;
- penanggung jawab akun;
- prioritas dan batas waktu;
- instruksi dan laporan tindak lanjut;
- aksi arsipkan;
- observer yang menyinkronkan status surat berdasarkan disposisi.

### Surat Keluar

Input utama:

- nomor surat resmi;
- tanggal;
- tujuan;
- klasifikasi;
- lokasi fisik;
- file final;
- tautan eksternal.

Workflow lama yang masih aktif:

- status `draf`, `terkirim`, `diarsipkan`;
- relasi balasan ke surat masuk;
- metode pengiriman dan nomor resi;
- waktu dikirim dan diterima;
- penerima dan bukti penerimaan;
- aksi tandai terkirim, arsipkan, dan catat diterima.

### Legalisir

Input utama:

- nomor registrasi dan antrean otomatis;
- tanggal permohonan;
- nama dan kontak pemohon;
- status pemohon;
- jenis dokumen;
- lokasi arsip fisik;
- tahun lulus;
- jumlah lembar.

Workflow lama yang masih aktif:

- status `diterima`, `diproses`, `siap_diambil`, `diambil`, `ditolak`;
- mulai verifikasi;
- tiga checklist verifikasi;
- jadwal pengambilan;
- nama dan hubungan pengambil;
- bukti serah terima;
- alasan penolakan;
- notifikasi outbox saat siap diambil;
- halaman pelacakan publik dan cetak bukti penerimaan.

### Kenaikan Pangkat

Workflow dipertahankan:

- `berkas_disiapkan`;
- `diajukan`;
- `sk_terbit`;
- `diserahkan`.

Data utama:

- pegawai;
- jenis KP;
- golongan lama dan tujuan;
- masa kerja tahun/bulan;
- tautan lampiran;
- TMT diminta ketika SK terbit.

Golongan master pegawai diterapkan otomatis ketika SK sudah terbit/diserahkan dan TMT sudah berlaku. Perintah `app:sync-effective-promotions` menjadi fallback scheduler harian.

### Gaji Berkala

Workflow dipertahankan:

- `diproses`;
- `sk_terbit`;
- `diserahkan`.

Data utama:

- pegawai;
- golongan;
- masa kerja tahun/bulan;
- tautan lampiran.

### Dashboard

Sebelum perubahan dashboard memuat:

- navigasi modul;
- ringkasan surat prioritas;
- daftar yang membutuhkan perhatian;
- posisi pekerjaan berdasarkan status workflow Surat Masuk, Surat Keluar, Legalisir, KP, dan KGB;
- utilitas admin.

Dashboard baru nantinya tidak boleh menampilkan workflow palsu untuk surat dan legalisir. Fokusnya berubah menjadi jumlah pencatatan, data terbaru, lokasi arsip yang belum lengkap, lampiran yang belum tersedia, dan pengajuan kepegawaian yang masih aktif.

## Struktur data yang harus dipertahankan

Tidak ada kolom lama yang langsung dihapus pada fase optimisasi. Tabel berikut wajib tetap kompatibel:

- `users`;
- `pegawai`;
- `kenaikan_pangkat`;
- `gaji_berkala`;
- `surat_masuk`;
- `surat_keluar`;
- `disposisi`;
- `relasi_surat`;
- `legalisir`;
- `lampiran` dan log aksesnya;
- `audit_log`;
- `notification_outbox`;
- tabel referensi dan pengaturan.

Fitur lama disembunyikan atau dinonaktifkan dari UI terlebih dahulu. Data lama tetap dapat dibaca dan tidak dimusnahkan.

## Aturan perubahan

1. Migration baru harus bersifat additive dan memiliki `down()` yang aman.
2. Tidak boleh menjalankan `migrate:fresh` pada data customer.
3. Tidak boleh menghapus data disposisi, relasi surat, status legalisir lama, atau outbox lama.
4. Import/export harus diperbarui bersama perubahan form.
5. Search harus tetap dapat menemukan nomor, nama, klasifikasi, dan lokasi arsip.
6. Audit, otorisasi, optimistic locking, file privat, dan backup tidak boleh dikurangi.
7. Setiap fase harus memiliki test dan commit terpisah.

## Rencana commit

1. `docs: record pre-optimization application baseline`
2. `refactor: simplify incoming and outgoing letter records`
3. `refactor: simplify legalisir as completed service records`
4. `refactor: align dashboard with passive record keeping`
5. `test: cover passive TU record keeping workflows`

## Prosedur rollback

Melihat versi sebelum perubahan tanpa mengubah branch:

```powershell
git show pre-passive-workflow-2026-11-07
```

Membuat branch pemulihan dari baseline:

```powershell
git switch -c recovery/pre-passive-workflow pre-passive-workflow-2026-11-07
```

Kembali bekerja di branch optimisasi:

```powershell
git switch optimize/tu-small-scale
```

Jangan menggunakan `git reset --hard` terhadap workspace aktif. Rollback database dilakukan melalui backup dan migration `down()` yang telah diuji, bukan dengan menghapus database.

## Kriteria penerimaan akhir

- Surat Masuk dan Surat Keluar selesai ketika data serta lokasi arsip disimpan.
- Legalisir selesai ketika jumlah dibawa/dikembalikan/disimpan dan lokasi fisik dicatat.
- Tidak ada tombol disposisi, penolakan, pengiriman, penerimaan, atau pengambilan yang tidak dipakai customer.
- KP/KGB tetap mempunyai workflow yang jelas.
- Semua tabel stabil ketika sidebar desktop dibuka dan tetap ringkas pada mobile.
- Data lama tetap dapat dibaca.
- Import/export mengikuti field baru.
- Seluruh test, build frontend, cache view, dan pemeriksaan format lulus.
