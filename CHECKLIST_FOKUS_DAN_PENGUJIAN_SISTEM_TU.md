# CHECKLIST FOKUS PROYEK & PENGUJIAN SISTEM TATA USAHA

**Sumber:** Inventarisasi dari `pencatatan-teknikal-umum-main.zip` (source awal) dan riwayat paket V1–V4.0.1. **Bukan** hasil inspeksi database/aplikasi terbaru yang berjalan.  
**Status awal:** semua pengujian bertanda `[ ]` = **belum dibuktikan lulus**; bukan berarti fitur tidak ada.  
**Aturan:** jangan menambah fitur baru sebelum alur inti lulus uji end-to-end.

## 0. Tujuan produk (jangan bergeser)
Sistem membantu **Staf TU mencatat, mencari, memproses, dan mengarsipkan** Surat Masuk, Surat Keluar, Legalisir, KP, dan KGB; Kepala memonitor/mengesahkan sesuai kewenangan; Admin menjaga akun dan pengaturan. **Pencarian berdasarkan nomor/nama/kode, status jelas, dan dokumen tidak hilang** lebih penting daripada grafik, tampilan berlebihan, atau integrasi tambahan.

### Role MVP yang digunakan
- **Admin:** administrasi sistem, pengguna, keamanan, dan konfigurasi (boleh merangkap operasional bila kebijakan sekolah membolehkan).
- **Staf TU:** kelola operasional surat, legalisir, dan kepegawaian sesuai kewenangan.
- **Kepala:** lihat laporan dan proses persetujuan jika alur persetujuan memang diterapkan.
- **Pegawai:** tetap ada pada kode lama; **jangan perluas akses / portal mandiri pada tahap MVP**. Jangan menghapus role/tabel tanpa audit data.

## 1. Fitur yang sudah diimplementasikan dalam *source awal* (BELUM BERARTI LOLOS TES)
| Modul | Temuan kode sumber | Tingkat fokus |
|---|---|---|
| Login & pengguna | Login/logout, status aktif, role admin/kepala/staf/pegawai, CRUD pengguna | P0 |
| Surat Masuk | CRUD, nomor agenda, pengirim, klasifikasi, status, filter, pencarian, upload file, disposisi, ekspor CSV | P0 |
| Surat Keluar | CRUD, nomor surat, penerima, status, file final, filter/pencarian, ekspor CSV | P0 |
| Legalisir | CRUD, pemohon, jenis berkas, status proses/siap/diambil, cetak bukti PDF, ekspor CSV | P0 |
| Pencarian/arsip | Pencarian tabel, filter status/tanggal/klasifikasi, lokasi fisik dan softfile lokal, tautan eksternal | P0 |
| Pegawai | CRUD nama/NIP/jabatan/status/golongan dan perkiraan jatuh tempo | P1 |
| KP | CRUD usulan/nomor SK/golongan/status dan aksi khusus | P1 |
| KGB | CRUD masa kerja/TMT/gaji/nomor SK/status dan aksi khusus | P1 |
| Dashboard | Widget ringkasan, surat terbaru, jatuh tempo | P2 |
| Impor/Ekspor dasar | CSV ekspor beberapa modul, impor CSV Google Form (persuratan/legalisir) | P2 |
| Audit | `audit_log` dan observer | P1 keamanan |
| Backup | Ekspor/dump SQL, tanpa verifikasi restore lewat UI | P1 keamanan |
| Pengaturan | App settings, beberapa referensi | P2 |

**Catatan versi:** V1.2 dikonfirmasi pengguna berhasil dipasang. V1.3, V2, V3, V3.1, dan V4.0.1 memiliki paket tetapi status pemasangan dan keberhasilan fiturnya pada instalasi terbaru **belum terverifikasi**. V1/V1.1/V4.0 sempat gagal pada pemeriksaan/konflik. Jangan memberi label 'selesai' sebelum uji.

## 2. Checklist pengujian prioritas P0 — WAJIB BERHASIL
Catat hasil setiap butir: `PASS`, `FAIL`, `BLOCKED`, serta screenshot/error bila gagal.

### A. Persiapan dan akses
- [ ] **A01** Buat backup database dan folder `storage` pada salinan staging. **Harapan:** tersedia cadangan yang dapat dibaca.
- [ ] **A02** Login akun Admin aktif berhasil; password salah ditolak. **Harapan:** tidak dapat masuk dengan kredensial salah.
- [ ] **A03** Akun nonaktif tidak bisa masuk; logout memutus sesi. **Harapan:** URL admin tidak dapat dibuka setelah logout.
- [ ] **A04** Staf TU hanya bisa mengoperasikan modul yang diizinkan. **Harapan:** URL langsung dan aksi backend tetap dicegah jika terlarang.
- [ ] **A05** Kepala hanya melihat/menyetujui sesuai kebutuhan sekolah; tidak otomatis memiliki hak edit semua data. **Harapan:** pembatasan ada di backend.

### B. Surat Masuk
- [ ] **SM01** Input surat masuk dengan data wajib valid. **Harapan:** tersimpan dan muncul di daftar.
- [ ] **SM02** Nomor agenda otomatis unik, termasuk saat dua permintaan bersamaan. **Harapan:** tidak ada duplikat.
- [ ] **SM03** Edit pengirim/perihal/status dan lihat perubahan tersimpan. **Harapan:** detail sesuai dengan daftar.
- [ ] **SM04** Cari berdasarkan nomor agenda, nomor surat, dan pengirim. **Harapan:** hasil relevan.
- [ ] **SM05** Filter status, sifat, klasifikasi, serta rentang tanggal. **Harapan:** hasil konsisten.
- [ ] **SM06** Unggah PDF/JPG dan buka kembali dari record yang benar. **Harapan:** file bisa dibaca hanya oleh yang berwenang.
- [ ] **SM07** Buat disposisi, simpan instruksi/tenggat, ubah progres, lalu arsipkan. **Harapan:** alur dapat ditelusuri dan tidak kehilangan data.
- [ ] **SM08** Preview PDF/JPG/PNG/WebP dari detail surat. **Harapan:** file tampil tanpa menjadi publik dan akses tanpa izin ditolak.

### C. Surat Keluar
- [ ] **SK01** Input surat keluar dengan nomor resmi sekolah secara manual. **Harapan:** nomor wajib, tersimpan apa adanya, dan unik.
- [ ] **SK02** Coba simpan nomor surat keluar yang sama, termasuk nomor dari record terhapus. **Harapan:** duplikat ditolak.
- [ ] **SK03** Edit tujuan/perihal/penandatangan sesuai hak akses. **Harapan:** data tersimpan.
- [ ] **SK04** Unggah dan buka softfile final. **Harapan:** file sesuai record dan izin.
- [ ] **SK05** Ubah status menjadi terkirim, lalu arsipkan. **Harapan:** transisi status benar.
- [ ] **SK06** Cari berdasarkan nomor, tujuan, perihal; filter tanggal/status. **Harapan:** hasil akurat.
- [ ] **SK07** Preview softfile PDF/gambar dari detail surat. **Harapan:** preview sesuai record dan tetap memeriksa izin.

### D. Legalisir
- [ ] **LG01** Buat pengajuan dengan nama pemohon dan jenis dokumen. **Harapan:** nomor registrasi unik.
- [ ] **LG02** Cari berdasarkan nama pemohon dan nomor registrasi. **Harapan:** hasil cepat dan sesuai.
- [ ] **LG03** Jalankan alur proses -> siap -> sudah diambil; isi nama pengambil. **Harapan:** status dan tanggal tercatat.
- [ ] **LG04** Cetak bukti legalisir PDF. **Harapan:** isi sesuai record.
- [ ] **LG05** Coba mengakses PDF legalisir tanpa login atau lewat akun tak berwenang. **Harapan:** ditolak.
- [ ] **LG06** Unggah dokumen legalisir dan periksa akses file. **Harapan:** file tidak publik tanpa izin.
- [ ] **LG07** Preview dokumen legalisir PDF/gambar. **Harapan:** preview sesuai berkas dan aksesnya tercatat.

### E. Pencarian dan kearsipan lintas modul
- [ ] **AR01** Cari surat memakai nomor/nama/kode yang benar. **Harapan:** hasil tepat, bukan hanya cocok sebagian yang membingungkan.
- [ ] **AR02** Filter surat aktif dan surat diarsipkan. **Harapan:** arsip tetap dapat ditemukan.
- [ ] **AR03** Unduh/buka softfile sesuai hak akses. **Harapan:** URL langsung tidak membocorkan file rahasia.
- [ ] **AR04** Upload format/ukuran terlarang. **Harapan:** validasi menolak dan memberi pesan jelas.
- [ ] **AR05** Hapus/arsipkan record dan periksa jejak audit. **Harapan:** perubahan dapat dilacak.

## 3. Checklist P1 — SETELAH ALUR P0 STABIL

### F. Pegawai, KP, dan KGB
- [ ] **PG01** Tambah/edit pegawai; NIP duplikat ditolak sesuai aturan unik yang disepakati.
- [ ] **PG02** Cari pegawai berdasarkan NIP/nama; filter status/golongan.
- [ ] **PG03** Isi foto, kontak, biodata, unit kerja, pendidikan, dan tanggal mulai tugas. **Harapan:** data tersimpan, foto dapat dipreview, dan email pegawai tetap unik.
- [ ] **PG04** Hubungkan akun pengurus ke data pegawai. **Harapan:** satu data pegawai tidak dapat dipakai oleh lebih dari satu akun.
- [ ] **KP01** Buat usulan KP dengan pegawai dan golongan yang valid.
- [ ] **KP02** Perbarui status KP dan pastikan perubahan data master pegawai terjadi hanya saat sesuai aturan bisnis.
- [ ] **KP03** Periksa perhitungan perkiraan KP dan penanganan tanggal TMT tidak valid.
- [ ] **KGB01** Buat KGB dengan TMT, masa kerja, dan nominal valid.
- [ ] **KGB02** Perbarui status KGB; validasi sinkronisasi master pegawai.
- [ ] **KGB03** Staf/Pegawai tidak dapat melihat nominal gaji melalui UI, route, ekspor, atau request langsung jika tidak memiliki izin.

### G. Data, keamanan dan ketahanan
- [ ] **SEC01** Semua aksi kustom (arsipkan, update status, tandai terkirim/diambil) memeriksa izin backend.
- [ ] **SEC02** Semua halaman impornya menerapkan kontrol akses; file Excel/CSV tidak bisa menjadi jalur melewati izin.
- [ ] **SEC03** Nomor agenda, surat, dan legalisir tidak bertabrakan di kondisi paralel.
- [ ] **SEC04** Audit log dapat menampilkan operator, perubahan, waktu, dan referensi record yang benar.
- [ ] **SEC05** Backup SQL berhasil dan dapat dipulihkan **di database percobaan**, bukan langsung pada produksi.
- [ ] **SEC06** Tidak ada akun demo dengan password default di instalasi nyata.

## 4. Checklist P2 — FITUR PENDUKUNG (JANGAN MENGHAMBAT GO-LIVE INTI)
- [ ] **UX01** Kolom teks Keterangan tidak menembus panel; tombol menu titik tiga tidak terpotong.
- [ ] **UX02** Tabel dan formulir responsif pada desktop dan layar kecil.
- [ ] **EX01** CSV lama bisa diekspor dan dibuka; karakter mirip formula ditangani aman.
- [ ] **EX02** Verifikasi apakah pusat import/export V3/V3.1 benar-benar terpasang sebelum dites.
- [ ] **EX03** Import data valid, tanggal salah, duplikat, kolom hilang; tidak merusak record lama.
- [ ] **EX04** XLSX hanya diklaim aktif jika ekstensi PHP dan fungsi baca/tulis benar-benar diuji.
- [ ] **DASH01** Nilai statistik sesuai jumlah record di database.
- [ ] **BACK01** Pengaturan aplikasi hanya dapat diubah oleh pengguna berwenang.

## 5. FITUR DITUNDA / PARKING LOT
- [ ] Google Sheets dua arah (pilih hanya jika proses TU benar-benar memerlukan rekap kolaboratif).
- [ ] Penyimpanan Google Drive (evaluasi jika server kekurangan storage/bandwidth; jangan dianggap otomatis menghapus semua I/O sementara).
- [ ] QR Code verifikasi surat.
- [ ] Portal mandiri Pegawai.
- [ ] Dashboard grafik canggih dan desain kosmetik tambahan.
- [ ] Notifikasi otomatis multi-kanal.
- [ ] Workflow approval berjenjang tambahan (setelah pihak sekolah mengonfirmasi tahapan persetujuan).

## 6. CATATAN BUG / HASIL UJI
Gunakan template ini untuk setiap hasil gagal:

```text
ID UJI      : (contoh SM04)
MODUL       : Surat Masuk
ROLE        : Staf TU
LANGKAH     : ...
HASIL AKTUAL: ...
HARAPAN     : ...
BUKTI       : screenshot / pesan error / log laravel
TINGKAT     : P0 / P1 / P2
STATUS      : OPEN / IN PROGRESS / FIXED / RETEST PASS
```

## 7. KRITERIA RILIS MVP
- [ ] Seluruh uji **P0** lulus pada staging dengan data contoh.
- [ ] Tidak ada bug keamanan/hilang data P0 yang terbuka.
- [ ] Modul Pegawai/KP/KGB yang digunakan secara operasional lulus uji P1 relevan.
- [ ] Backup dan uji restore staging berhasil.
- [ ] Alur cari -> lihat -> ubah status -> arsipkan -> buka softfile berfungsi untuk surat dan legalisir.
- [ ] Hak akses tiga role operasional disetujui pihak sekolah.
- [ ] Ada berita acara UAT sederhana dari perwakilan TU (setidaknya satu petugas dan satu penanggung jawab).

**Fokus kerja terdekat:** pilih 1 contoh Surat Masuk, 1 Surat Keluar, dan 1 Legalisir; jalankan semua uji P0 untuk ketiganya, dokumentasikan bug, baru kerjakan perbaikan. Jangan menambah fitur sampai alur tersebut stabil.
