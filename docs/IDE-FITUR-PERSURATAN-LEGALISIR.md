# Ide fitur Persuratan dan Legalisir

Dokumen ini adalah urutan rekomendasi, bukan klaim fitur yang sudah tersedia. Prioritas dipilih berdasarkan manfaat harian, keamanan arsip, dan kemudahan penerapan pada SIPATU saat ini.

## Prioritas berikutnya

1. **Pencarian terpadu dan filter tersimpan**
   Cari nomor agenda, nomor surat asli, pengirim/tujuan, perihal, pemohon, serta status dari satu kolom. Pengguna dapat menyimpan filter seperti “surat segera belum selesai” atau “legalisir siap diambil”.

2. **Timeline aktivitas per berkas**
   Detail menampilkan pencatatan dibuat, disposisi, perubahan status, catatan, penggantian lampiran, dan siapa yang melakukannya. Ini lebih mudah diperiksa daripada audit log mentah.

3. **Lampiran privat dan riwayat versi**
   File hanya dibuka melalui endpoint berizin, bukan URL storage langsung. Simpan versi, ukuran, hash, pengunggah, waktu akses, serta alasan penggantian dokumen.

4. **Tenggat disposisi dan tindak lanjut**
   Surat masuk dapat diberikan kepada penanggung jawab dengan batas waktu, prioritas, catatan hasil, dan indikator terlambat. Dashboard hanya menampilkan pekerjaan yang relevan dengan pengguna.

5. **Antrean legalisir dan pelacakan pemohon**
   Buat nomor antrean serta token pelacakan acak. Pemohon hanya melihat status aman seperti diterima, diproses, siap diambil, atau ditolak—tanpa membuka data internal.

## Persuratan

- Hubungkan surat balasan dengan surat masuk agar percakapan/riwayat dokumen terlihat sebagai satu rangkaian.
- Kunci nomor surat keluar secara transaksional dan sediakan status nomor dibatalkan beserta alasannya; nomor lama tidak digunakan ulang.
- Template surat keluar berdasarkan jenis dokumen dengan variabel sekolah, tanggal, tujuan, dan penandatangan.
- Catat metode pengiriman, tanggal kirim, penerima, nomor resi, dan bukti terima.
- Deteksi kemungkinan duplikat dari nomor surat, instansi, tanggal, serta hash lampiran sebelum penyimpanan.
- Label QR untuk map/ordner yang mengarah ke detail arsip setelah pengguna login.
- Rekap buku agenda bulanan/tahunan dengan ekspor yang menghormati role dan menyamarkan data sensitif.

## Legalisir

- Satu permohonan dapat memiliki beberapa jenis dokumen dan jumlah salinan tanpa membuat record pemohon berulang.
- Checklist verifikasi: dokumen asli diperlihatkan, identitas cocok, tahun lulus, nomor ijazah, jumlah lembar, dan petugas pemeriksa.
- Jadwal pengambilan, nama penerima, hubungan dengan pemohon, serta bukti serah terima.
- Alasan penolakan terstruktur dan alur perbaikan berkas tanpa menghapus riwayat permohonan lama.
- QR pada bukti legalisir untuk memeriksa keaslian nomor, status, dan tanggal; jangan tampilkan NIK/alamat pada halaman publik.
- Notifikasi siap diambil melalui email atau WhatsApp sebagai integrasi opsional dengan antrean kirim, retry, dan log pengiriman.
- Batas layanan/SLA per jenis dokumen agar petugas dan Kepala TU dapat melihat antrean yang terlambat.

## Struktur data yang kemungkinan diperlukan

- `document_events`: timeline lintas surat dan legalisir.
- `document_attachments`: versi file, hash, klasifikasi akses, dan pengunggah.
- `attachment_access_logs`: catatan preview/unduh file sensitif.
- `disposition_tasks`: penanggung jawab, tenggat, hasil, dan waktu selesai.
- `letter_relations`: hubungan surat masuk, balasan, dan lampiran terkait.
- `legalisir_items`: jenis dokumen dan jumlah salinan per permohonan.
- `document_deliveries`: pengiriman surat atau serah terima legalisir.
- `public_tracking_tokens`: token acak yang disimpan dalam bentuk hash dan dapat kedaluwarsa.

## Urutan pembangunan yang disarankan

Mulai dari pencarian terpadu, detail dengan timeline, dan endpoint lampiran privat. Setelah fondasi itu stabil, lanjutkan tenggat disposisi serta antrean/pelacakan legalisir. Template, notifikasi eksternal, dan QR publik sebaiknya dibangun terakhir karena membutuhkan aturan data, keamanan token, dan operasional yang lebih matang.
