# PB-02 — Pembaruan Dependensi Keamanan

Tanggal penerapan: 10 November 2026  
Status: selesai di source; deployment customer belum dilakukan

## Kondisi awal

`composer audit --locked` melaporkan 11 advisori pada 6 paket Symfony. `npm audit` melaporkan satu advisori pada `follow-redirects` versi 1.16.1.

Pembaruan Symfony 7.4.23 dipilih karena merupakan security release resmi dan menutup advisori yang memengaruhi HttpFoundation, HttpKernel, Routing, HtmlSanitizer, dan YAML. `symfony/mime` memperoleh perbaikan terkait pada 7.4.22. `follow-redirects` diperbarui melewati batas aman 1.16.2.

Referensi resmi:

- <https://github.com/symfony/symfony/releases/tag/v7.4.23>
- <https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693>
- <https://github.com/follow-redirects/follow-redirects/security/advisories/GHSA-mrwp-f3q8-3g9p>

## Perubahan versi

Paket utama yang diperbaiki:

| Paket | Sebelum | Sesudah |
|---|---:|---:|
| `symfony/html-sanitizer` | 7.4.20 | 7.4.23 |
| `symfony/http-foundation` | 7.4.21 | 7.4.23 |
| `symfony/http-kernel` | 7.4.21 | 7.4.23 |
| `symfony/mime` | 7.4.19 | 7.4.22 |
| `symfony/routing` | 7.4.21 | 7.4.23 |
| `symfony/yaml` | 7.4.21 | 7.4.23 |
| `follow-redirects` | 1.16.1 | 1.16.3 |

Composer juga memperbarui dependensi transitif yang diperlukan: `league/uri`, `league/uri-interfaces`, tiga Symfony contracts/polyfill, `error-handler`, dan `var-dumper`. Constraint di `composer.json` dan `package.json` tidak diubah; perubahan hanya berada pada `composer.lock` dan `package-lock.json`.

## Verifikasi

- `composer validate`: lulus.
- `composer install --dry-run --no-scripts`: tidak ada paket yang kurang atau tidak konsisten.
- `composer audit --locked`: **nol advisori**.
- `npm ci --ignore-scripts`: lulus.
- `npm audit`: **nol advisori**.
- `npm run build`: lulus, 59 modul ditransformasi.
- Package discovery Laravel dan publish asset Filament: lulus.
- Route internal Livewire tetap `/livewire/update` dan login tetap route Filament.
- Seluruh suite: **106 tes, 838 assertion**, lulus.

Tes otomatis mencakup autentikasi, session, hak akses, lampiran privat, login Filament, serta skenario subdomain/base-path/HTTPS pada tingkat feature test. Verifikasi browser nyata tetap menjadi PB-09.

Tidak ada migration, seed, atau perubahan `.env` pada PB-02. Database lokal dan customer tidak diubah oleh tahap ini.

## Deployment

Setelah menarik commit PB-02:

```powershell
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize:clear
```

Pada server yang menyimpan hasil build dari CI, langkah npm dapat diganti dengan menyalin artifact build yang sudah diverifikasi.

Sesudah instalasi, jalankan:

```powershell
composer audit --locked
npm audit
php artisan test
```

## Rollback

Kembalikan commit PB-02, kemudian pasang ulang versi yang tercatat pada lock file lama:

```powershell
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize:clear
```

Rollback PB-02 tidak memerlukan rollback database.
