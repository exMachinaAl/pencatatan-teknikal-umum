# Panduan Spreadsheet dan Lampiran

## Peran spreadsheet

Database aplikasi tetap menjadi sumber data utama. Google Sheets atau file Excel/CSV dipakai sebagai media bantu untuk:

- membagikan salinan data kepada pihak yang berwenang;
- mengisi banyak data sebelum diimpor ke aplikasi;
- menyimpan daftar kerja atau dokumen pendukung yang memang perlu dikelola di luar aplikasi; dan
- menautkan dokumen Google Drive, Google Sheets, atau layanan HTTPS lain ke satu catatan.

Spreadsheet bukan pengganti database dan perubahan pada Google Sheets tidak otomatis mengubah data aplikasi. Untuk memasukkan perubahan, unduh spreadsheet sebagai CSV/XLSX lalu gunakan menu pertukaran data dan template dari aplikasi.

## Modul yang memakai lampiran atau tautan

| Modul | Berkas lokal privat | Tautan eksternal opsional | Tampil langsung di tabel |
|---|---:|---:|---:|
| Surat Masuk | Ya | Ya | Ya |
| Surat Keluar | Ya | Ya | Ya |
| Legalisir | Ya | Ya | Ya |
| Kenaikan Pangkat | Berkas lama tetap dapat dibaca | Ya | Ya |
| Gaji Berkala | Berkas lama tetap dapat dibaca | Ya | Ya |
| Data Pegawai | Tidak ditambahkan | Tidak ditambahkan | Tidak |

Data Pegawai sengaja tetap ringkas: nama lengkap dan gelar, NIP, status kepegawaian, serta golongan/ruang. Tautan tidak ditambahkan ke Pengguna, Hak Akses, Audit Log, dan Pengaturan karena bagian tersebut bukan arsip dokumen operasional.

## Perilaku di tabel

- **Lihat file** membuka pratinjau PDF atau gambar privat di tab baru.
- **Unduh file** tersedia berdampingan untuk menyimpan berkas lokal.
- **Google Sheets**, **Google Drive**, **Google Form**, atau **Buka tautan** membuka lampiran eksternal di tab baru.
- Jika catatan tidak memiliki berkas atau tautan, tidak ada tombol maupun tanda pengganti yang ditampilkan.
- Pada layar ponsel, tombol menjadi ikon ringkas dan tetap berada pada baris ringkasan data.

Berkas lokal selalu melewati login, pemeriksaan hak akses, dan pencatatan log akses. Tautan eksternal mengikuti izin berbagi dari penyedia tautan.

## Cara mengisi tautan

1. Buka tambah atau edit data pada modul yang mendukung lampiran.
2. Isi **Tautan Google Drive / Sheets / Lampiran** atau **Tautan Lampiran / Google Sheets**.
3. Gunakan alamat lengkap yang diawali `https://`.
4. Simpan data. Tombol tautan akan muncul langsung di tabel.

Kolom ini opsional. Biarkan kosong bila tidak ada lampiran eksternal.

## Cara memakai Google Sheets untuk impor dan berbagi

1. Buka menu **Pertukaran Data TU** untuk Surat Masuk, Surat Keluar, atau Legalisir; gunakan **Pertukaran Data Kepegawaian** untuk Data Pegawai, Kenaikan Pangkat, atau Gaji Berkala.
2. Unduh template dari aplikasi agar nama dan urutan kolom benar.
3. Impor template ke Google Sheets dan isi data. Kolom tautan yang tersedia adalah `tautan_gform` untuk persuratan serta `tautan_lampiran` untuk Legalisir, KP, dan KGB.
4. Unduh kembali sebagai CSV atau XLSX.
5. Unggah ke aplikasi, periksa pratinjau dan kesalahan, lalu konfirmasi impor.

Ekspor dari aplikasi juga menyertakan kolom tautan, sehingga tautan tetap ikut saat data dibagikan melalui spreadsheet.

Untuk Legalisir, isi kolom `lokasi_arsip_fisik` dengan penanda yang konsisten, misalnya `Loker 2 / Rak B / LEG-2026`. Nilai ini tampil langsung di tabel dan dapat dicari melalui pencarian tabel, pencarian terpadu, maupun filter pencarian saat ekspor.

## Ekspor spreadsheet dan backup

Ekspor CSV/XLSX tetap berguna untuk Surat Masuk, Surat Keluar, Legalisir, serta data kepegawaian sebagai arsip yang mudah dibaca, laporan, pemindahan data, dan pemulihan manual sebagian. CSV paling stabil untuk pertukaran data, sedangkan XLSX lebih nyaman untuk diperiksa atau dibagikan kepada pengguna.

Namun, ekspor per modul bukan backup aplikasi yang lengkap. File tersebut tidak mencakup seluruh pengguna dan hak akses, audit log, konfigurasi, struktur database, serta isi berkas lampiran lokal. Tautan Google Drive atau Google Sheets ikut diekspor, tetapi aksesnya tetap bergantung pada file dan izin berbagi di layanan tersebut.

Gunakan dua lapis perlindungan:

1. backup database beserta direktori penyimpanan lampiran untuk pemulihan aplikasi secara utuh; dan
2. ekspor CSV/XLSX berkala sebagai arsip sekunder yang mudah dibaca dan dipindahkan.

## Pengaturan berbagi yang aman

- Untuk dokumen internal atau data pribadi, pilih akses **Restricted/Dibatasi** dan tambahkan akun yang benar-benar memerlukan akses.
- Hindari **Anyone with the link/Siapa saja yang memiliki link** untuk NIP, dokumen kepegawaian, ijazah, atau surat rahasia.
- Berikan akses **Viewer/Pelihat** bila penerima hanya perlu membaca.
- Uji tautan dengan akun penerima sebelum digunakan dalam operasional.
- Menghapus tautan dari aplikasi tidak menghapus file di Google Drive; ubah atau cabut izin langsung dari Google Drive bila akses harus dihentikan.

## Batas integrasi saat ini

Integrasi bersifat tautan serta impor/ekspor file. Aplikasi belum melakukan sinkronisasi otomatis dua arah dengan Google Sheets dan tidak mengubah isi Google Drive. Pendekatan ini sesuai untuk penggunaan pribadi atau tim kecil karena pengaturannya lebih sederhana dan tidak memerlukan kredensial Google API.
