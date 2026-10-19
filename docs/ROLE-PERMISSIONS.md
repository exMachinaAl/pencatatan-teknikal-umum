# Role dan permission SIPATU

Dokumen ini mencatat aturan yang benar-benar diterapkan pada MVP per 16 Oktober 2026. Sumber aturan program berada di `App\Models\User::ROLE_PERMISSIONS`; tabel ini harus ikut diperbarui bila matriks kode berubah.

## Role

| Kode | Tampilan | Cakupan |
| --- | --- | --- |
| `admin` | Administrator Sistem (Fasilitator TI) | Divisi IT di luar hierarki TU: pengguna, pengaturan, backup, audit teknis, serta pemantauan baca-saja untuk persuratan dan legalisir. |
| `kepala_tu` | Kepala Tata Usaha (Pimpinan TU) | Pimpinan tertinggi operasional TU: seluruh modul operasional, perubahan status, impor/ekspor yang tersedia, dan audit. |
| `petugas_tu` | Petugas TU (Persuratan & Legalisir) | Surat masuk, surat keluar, legalisir. Ini penggabungan staf persuratan dan staf legalisir. |
| `kepegawaian` | Petugas Kepegawaian | Data pegawai, kenaikan pangkat, kenaikan gaji berkala, termasuk nominal gaji. |
| `kepala_sekolah` | Kepala Sekolah | Baca modul operasional dan cetak bukti legalisir; tidak mengedit record secara langsung. |
| `auditor` | Auditor / Pengawas | Baca modul operasional dan audit; tidak mengubah atau mengekspor data. |
| `pegawai` | Pegawai / Pemohon | Belum membuka record operasional karena relasi kepemilikan data belum tersedia. |

## Matriks aksi

| Role | Pengguna | Sistem | Surat | Legalisir | Kepegawaian | Audit |
| --- | --- | --- | --- | --- | --- | --- |
| Administrator Sistem / Fasilitator TI | lihat, buat, ubah, hapus | pengaturan, backup | lihat | lihat | — | lihat |
| Kepala TU | — | — | lihat, buat, ubah, status, impor, ekspor | lihat, buat, ubah, status, cetak, impor, ekspor | lihat, buat, ubah, status, impor, ekspor | lihat |
| Petugas TU | — | — | lihat, buat, ubah, status, impor, ekspor | lihat, buat, ubah, status, cetak, impor, ekspor | — | — |
| Kepegawaian | — | — | — | — | lihat, buat, ubah, status, impor, ekspor | — |
| Kepala Sekolah | — | — | lihat | lihat, cetak | lihat | — |
| Auditor | — | — | lihat | lihat | lihat | lihat |
| Pegawai | — | — | — | — | — | — |

`status` berarti aksi alur yang sekarang tersedia, misalnya tandai terkirim, arsipkan, siap diambil, diserahkan, atau perubahan status KP. Aksi tersebut tidak sama dengan approval bertingkat.

## Pengamanan yang diterapkan

- Resource Filament memeriksa view/create/update/delete/restore pada backend, bukan hanya menyembunyikan sidebar.
- URL create/edit/list yang tidak sesuai role menghasilkan 403.
- Aksi custom dan ekspor memiliki pemeriksaan UI serta pemeriksaan ketika closure dijalankan.
- Endpoint cetak legalisir memeriksa permission backend.
- Import hanya menawarkan dan menerima modul yang diizinkan untuk pengguna.
- Dashboard tidak menjalankan query atau menampilkan angka modul yang tidak boleh dilihat.
- Record final dikunci dari edit langsung: surat masuk arsip, surat keluar terkirim/arsip, legalisir diambil/ditolak, KP/KGB diserahkan.
- Admin tidak dapat mengubah role, menonaktifkan, atau menghapus akunnya sendiri melalui UI.
- Force delete dinonaktifkan. Role operasional saat ini tidak mendapat permission delete/restore.
- Role lama `staf` dan `kepala` tetap dikenali sementara oleh model; migrasi database mengubahnya menjadi `petugas_tu` dan `kepala_sekolah`.
- Administrator Sistem bukan bagian dari rantai komando TU. Kepala TU adalah otoritas tertinggi untuk pekerjaan operasional TU; provisioning akun dan infrastruktur tetap menjadi pemisahan tugas milik Divisi IT.

## Batas aman yang sengaja belum dibuka

- Admin dapat membaca metadata dan detail surat/legalisir, tetapi tidak dapat membuat, mengedit, menghapus, mengubah status, mengimpor, mengekspor, atau mencetak bukti legalisir.
- Akses file rahasia tetap membutuhkan endpoint unduh terotorisasi dan pencatatan akses khusus.
- Portal data milik pegawai/pemohon belum aktif karena record belum memiliki relasi kepemilikan yang cukup untuk membatasi query dengan aman.
- Approval independen (pembuat tidak boleh menyetujui miliknya sendiri) belum dapat ditegakkan sampai tabel approval dan pemeriksa tersedia.
- Unduh/preview berkas sensitif perlu endpoint terotorisasi serta klasifikasi kerahasiaan per dokumen; ini dilanjutkan pada tahap surat dan arsip.
- Belum ada akun yang otomatis dibuat untuk `kepala_tu`, `kepegawaian`, dan `auditor`. Admin memilih pengguna nyata dan memberi role melalui Kelola Pengguna agar tidak menciptakan akun default berpassword umum.
