# Performa Lokal dan Deployment Subpath `/tu`

## Diagnosis 7 November 2026

Pengukuran dilakukan pada Windows, PHP 8.2.34, dan repository di dalam OneDrive.

- Bootstrap `php artisan about`: sekitar 1,1 detik setelah warm-up.
- Login melalui `php artisan serve` tanpa OPcache: sekitar 0,66–1,3 detik per request hangat.
- PHP menyediakan `php_opcache.dll`, tetapi extension tidak aktif pada konfigurasi CLI.
- Blade terkompilasi sebelumnya berjumlah sekitar 481 file di dalam folder OneDrive.
- Session dan cache sebelumnya juga ditulis ke OneDrive.
- Halaman sebelumnya meminta font Bunny dan avatar UI Avatars melalui internet.
- Dengan OPcache pada server lokal, request login hangat turun menjadi sekitar 0,15–0,25 detik.

Kelambatan mendekati satu menit tidak berasal dari query aplikasi pada pengujian ini. Penyebab yang paling mungkin adalah kombinasi cold scan OneDrive/antivirus, PHP tanpa OPcache, dan resource internet yang timeout pada jaringan lokal sekolah.

## Menjalankan server lokal cepat

Gunakan:

```powershell
composer run serve:fast
```

atau langsung:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/serve-fast.ps1
```

Script mengaktifkan OPcache hanya untuk proses server tersebut. Konfigurasi PHP global tidak diubah. Setelah mengubah route/config/provider, bersihkan cache jika hasil lama masih terlihat:

```powershell
composer run optimize:clear
```

Untuk demo yang jarang mengalami perubahan kode, cache Laravel dapat dibuat sekaligus:

```powershell
composer run serve:fast:optimize
```

Proses optimasi pertama dapat memerlukan waktu karena seluruh view Filament dikompilasi. Jangan jalankan varian ini setiap kali mengubah kode.

## Menjauhkan file sementara dari OneDrive

Konfigurasi berikut sudah didukung:

```dotenv
CACHE_FILES_PATH=C:/Users/nama/AppData/Local/tu-sekolah/cache
SESSION_FILES_PATH=C:/Users/nama/AppData/Local/tu-sekolah/sessions
VIEW_COMPILED_PATH=C:/Users/nama/AppData/Local/tu-sekolah/views
```

Direktori harus sudah tersedia dan dapat ditulis oleh proses PHP. Session, cache, serta compiled view bersifat sementara sehingga tidak perlu masuk backup aplikasi.

Pemindahan seluruh repository keluar OneDrive tetap merupakan perbaikan terbesar untuk development. Lokasi yang disarankan misalnya `C:/Projects/pencatatan-tu`. OneDrive dapat tetap dipakai untuk salinan backup, bukan folder kerja aktif beserta `vendor`.

## Deployment pada `/tu`

Gunakan build produksi, bukan Vite development server:

```powershell
npm ci
npm run build
```

Pastikan `public/hot` tidak ada. Kemudian atur:

```dotenv
APP_URL=http://alamat-server/tu
ASSET_URL=http://alamat-server/tu
```

`ASSET_URL` boleh kosong jika reverse proxy/PHP-FPM mengirim base URL `/tu` dengan benar. Mengisinya eksplisit lebih aman pada konfigurasi alias yang tidak meneruskan prefix.

Web server harus memetakan URL `/tu` ke folder `public`, bukan ke root repository. Contoh konsep Nginx/PHP-FPM:

```nginx
location /tu/ {
    alias C:/path/pencatatan-tu/public/;
    try_files $uri $uri/ /tu/index.php?$query_string;
}

location = /tu/index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME C:/path/pencatatan-tu/public/index.php;
    fastcgi_param SCRIPT_NAME /tu/index.php;
    fastcgi_param HTTP_X_FORWARDED_PREFIX /tu;
    fastcgi_pass 127.0.0.1:9000;
}
```

Sesuaikan path dan alamat PHP-FPM. Pada Apache/Laragon, buat `Alias /tu` yang menunjuk langsung ke folder `public` dan aktifkan `AllowOverride All`.

Setelah mengganti URL atau konfigurasi server:

```powershell
php artisan optimize:clear
php artisan optimize
```

Periksa source HTML. Semua URL berikut harus mengandung `/tu`:

- `/tu/build/assets/...`;
- `/tu/css/tu-ui-v20-operasional.css`;
- `/tu/livewire/livewire.js`; dan
- endpoint `/tu/admin/...`.

Jika browser menampilkan error origin menuju port 5173, berarti aplikasi masih memakai Vite development server. Hentikan `npm run dev`, hapus `public/hot`, jalankan `npm run build`, lalu muat ulang tanpa cache.

## PHP-FPM produksi/lokal

Pastikan OPcache aktif pada `php.ini` milik PHP-FPM:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

Restart PHP-FPM setelah mengubah `php.ini`. Jangan hanya memeriksa `php -m` dari CLI karena PHP-FPM dapat memakai file konfigurasi berbeda.
