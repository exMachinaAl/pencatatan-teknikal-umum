# Analisis dan pembaruan proses bisnis

Tanggal: 26 Oktober 2026 (Asia/Jakarta).

## Kesimpulan analisis

Penilaian bahwa proses bisnis hampir tidak terlihat memang benar. Sebelum pembaruan ini, dashboard terutama berfungsi sebagai kumpulan pintasan dan angka. Sebagian angka juga masih dihitung dari field lama yang sudah dihapus dari formulir, seperti TMT KGB dan TMT pangkat terakhir pada Data Pegawai. Akibatnya data baru tidak dapat masuk ke perhitungan dashboard.

Masalah lain yang ditemukan:

- KGB mempunyai status di database, tetapi status tidak terlihat pada tabel dan tidak ada aksi untuk melanjutkan proses.
- Legalisir mempunyai status `diproses`, tetapi tidak ada aksi untuk masuk ke tahap tersebut. Status juga masih dapat diubah manual dari form edit.
- Tombol Legalisir dapat melompat dari `diterima` langsung ke `siap_diambil`.
- Disposisi tidak menyinkronkan status Surat Masuk induknya.
- Surat Masuk dengan disposisi aktif dapat langsung diarsipkan.
- Kenaikan Pangkat memperbolehkan pemilihan status secara bebas, bukan melanjutkan urutan kerja.

## Proses bisnis yang sekarang digunakan

### Data Pegawai

Data Pegawai adalah **master data**, bukan transaksi. Isinya menjadi referensi akun, Kenaikan Pangkat, dan KGB. Karena itu menu ini tidak membutuhkan tahapan status.

### Surat Masuk

`Baru Diterima` → `Didisposisikan` → `Selesai Ditindaklanjuti` → `Diarsipkan`

- Surat tanpa kebutuhan disposisi dapat diarsipkan langsung.
- Saat disposisi dibuat atau masih aktif, surat otomatis menjadi `Didisposisikan`.
- Jika seluruh disposisi selesai, surat otomatis menjadi `Selesai Ditindaklanjuti`.
- Surat tidak dapat diarsipkan ketika masih ada disposisi berjalan.

### Surat Keluar

`Konsep / Draf` → `Terkirim` → `Diarsipkan`

- Data penerimaan oleh tujuan dicatat sebagai bukti tambahan pada tahap terkirim.
- Bukti penerimaan saat ini bersifat opsional karena SOP customer belum menetapkan bahwa seluruh jenis pengiriman wajib memiliki konfirmasi penerima.

### Legalisir

`Permohonan Diterima` → `Sedang Diverifikasi` → `Siap Diambil` → `Sudah Diambil`

Cabang alternatif: `Diterima/Diproses` → `Ditolak` dengan alasan wajib.

- Petugas menekan **Mulai Verifikasi** untuk mengambil pekerjaan.
- Tahap siap hanya tersedia setelah proses dimulai dan seluruh checklist terpenuhi.
- Tahap diambil mewajibkan nama pengambil dan waktu penyerahan.
- Status tidak lagi dapat diedit manual melalui form.

### Kenaikan Pangkat

`Berkas Disiapkan` → `Diajukan` → `SK Terbit` → `Diserahkan`

Tombol tabel hanya melanjutkan satu tahap. Status tidak dapat dilompati atau dimundurkan secara langsung.

### Kenaikan Gaji Berkala

`Diproses` → `SK Terbit` → `Diserahkan`

Status sekarang terlihat pada tabel dan tersedia tombol **Lanjut ke ...**. Dashboard menghitung semua pengajuan yang belum diserahkan, tidak lagi menggunakan TMT yang sudah dihapus dari input.

## Perubahan dashboard

- Menambahkan bagian **Proses Bisnis – Posisi pekerjaan saat ini**.
- Setiap modul menampilkan jumlah record pada masing-masing tahap status.
- Setiap tahap dapat diklik untuk membuka tabel dengan filter status yang sesuai.
- Legalisir membedakan pekerjaan verifikasi dan dokumen yang sudah siap tetapi belum diserahkan.
- KGB dan KP dihitung berdasarkan status proses nyata.
- Deskripsi kartu menu disesuaikan dengan formulir dan tabel terbaru.
- Perhitungan lama `KGB jatuh tempo` dan `calon KP` dihapus dari dashboard karena bergantung pada input yang sudah tidak digunakan.

## Keputusan SOP yang masih perlu customer tetapkan

Kode tidak dapat menentukan keputusan organisasi berikut tanpa persetujuan sekolah:

- Apakah Surat Keluar wajib mempunyai bukti diterima sebelum boleh diarsipkan.
- Apakah KP/KGB membutuhkan tahap ditolak atau dikembalikan untuk perbaikan.
- Berkas apa saja yang wajib untuk KP/KGB karena input saat ini hanya menyediakan tautan lampiran opsional.
- Target waktu/SLA setiap tahap, pejabat pemberi persetujuan, dan mekanisme eskalasi keterlambatan.
- Apakah perubahan status final perlu persetujuan dua akun berbeda.

Kelima hal tersebut merupakan proses organisasi, bukan sekadar masalah tampilan. Sampai SOP diputuskan, aplikasi mempertahankan alur minimum yang konsisten dengan input terbaru.
