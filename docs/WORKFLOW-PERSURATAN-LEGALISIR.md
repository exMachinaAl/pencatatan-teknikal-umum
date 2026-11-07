# Pencatatan Pasif Persuratan dan Legalisir

Dokumen ini adalah panduan operasional aktif sejak 7 November 2026. Surat masuk, surat keluar, dan legalisir merupakan register arsip digital untuk tim TU kecil, bukan aplikasi disposisi atau persetujuan.

## Surat masuk

1. Catat tanggal diterima, nomor surat bila ada, tanggal surat, dan pengirim.
2. Pilih atau tambahkan kode klasifikasi.
3. Isi lokasi fisik secara konsisten, misalnya `Lemari A / Rak 2 / Ordner 2026`.
4. Tambahkan scan privat dan/atau tautan HTTPS bila tersedia.
5. Simpan. Surat langsung tercatat sebagai arsip; tidak ada disposisi, balasan, atau status tindak lanjut.

## Surat keluar

1. Catat nomor surat resmi, tanggal, dan tujuan.
2. Pilih kode klasifikasi dan isi lokasi arsip fisik.
3. Tambahkan scan final dan/atau tautan HTTPS bila tersedia.
4. Simpan. Tidak ada tahap draf, terkirim, bukti diterima, atau relasi balasan di UI.

## Legalisir

1. Catat tanggal layanan, nama, kontak bila ada, status pemohon, jenis dokumen, dan tahun lulus bila diketahui.
2. Pilih jumlah dokumen yang dibawa, maksimal 6.
3. Sistem menetapkan satu dokumen disimpan sebagai arsip TU dan menghitung sisanya sebagai dokumen yang dikembalikan. Contoh: dibawa 6, kembali 5, disimpan 1.
4. Isi lokasi fisik dokumen yang disimpan.
5. Tambahkan scan, tautan, atau catatan bila tersedia, lalu simpan.

Legalisir tidak mempunyai penolakan, antrean proses, status siap diambil, notifikasi, atau pelacakan publik untuk catatan baru. Data workflow lama tetap disimpan untuk kompatibilitas riwayat, tetapi tidak ditawarkan pada UI operasional.

## Koreksi, pencarian, dan lampiran

- Catatan pasif boleh dikoreksi oleh akun yang mempunyai izin ubah. Perubahan tetap masuk audit log dan dilindungi optimistic locking agar dua akun tidak saling menimpa.
- Pencarian terpadu mencakup nomor, pihak terkait, klasifikasi, kontak, jenis dokumen, dan lokasi arsip fisik.
- File lokal disimpan privat. Pratinjau dan unduh memerlukan login, izin modul, serta dicatat pada log akses.
- Tautan eksternal bersifat opsional dan tampil langsung pada tabel bila diisi.
- Ekspor CSV/XLSX adalah salinan data yang mudah dibaca, bukan pengganti backup database dan lampiran.

## Proses yang tetap aktif

Kenaikan Pangkat dan Kenaikan Gaji Berkala tetap mempunyai tahapan status karena keduanya benar-benar mewakili pekerjaan yang bergerak sampai diserahkan. Hanya dua proses tersebut yang tampil di bagian proses bisnis dan perhatian pada dashboard.
