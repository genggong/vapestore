# V17.0.18 — HOTFIX Halaman Admin 500: `Undefined constant "APP_ASSET_VERSION"`

## Gejala di server

Buka halaman admin setelah login (contoh `/admin/pos/index.php`, `/admin/customers/edit.php?id=293`,
`/pos/`) → muncul halaman putih:

```
Terjadi kesalahan sistem. Periksa writable/logs.
```

Di `writable/logs/log-<tanggal>.log`:

```
ERROR --> [Legacy bridge] Error: Undefined constant "APP_ASSET_VERSION" in APPPATH/Controllers/LegacyController.php:187
```

Catatan: lokasi `LegacyController.php:187` itu **menyesatkan** — placeholder `{file}`/`{line}` di
`log_message()` diisi CI4 dengan lokasi pemanggil log, bukan lokasi error. Lokasi sebenarnya
(ditemukan lewat reproduksi lokal) adalah layout admin: `legacy/public/layouts/admin-header.php:32`.

## Akar masalah

Sejak v17.0.16c, semua aset di-render dengan `?v=APP_ASSET_VERSION` (konstanta dari file `VERSION`),
dan konstanta itu didefinisikan di `app/Helpers/asset_helpers.php` yang di-`require` dari
`Helpers/helpers.php`. Di server ini `Helpers/helpers.php` masih **versi lama** (tanpa require itu)
sehingga layout memakai konstanta yang tidak pernah didefinisikan → `Error` → bridge mengembalikan 500.
Semua halaman admin yang memakai layout admin terkena; halaman publik tampak normal karena
memakai jalur aset lain.

## Perbaikan

1. **Definisi konstanta dipindah ke `bootstrap.php` (3 tree: root `app/`, `legacy/app/`, `pos/app/`).**
   Bootstrap selalu dimuat setiap halaman legacy, jadi konstanta dijamin ada **tanpa bergantung**
   pada versi `helpers.php`:
   ```php
   if (!defined('APP_ASSET_VERSION')) { define('APP_ASSET_VERSION', vk_asset_version()); }
   ```
   `vk_asset_version()` membaca `VERSION` dari docroot (fallback `1` bila tidak terbaca).
2. **`Helpers/helpers.php` + `Helpers/asset_helpers.php` dikirim ulang (3 tree)** supaya server yang
   file-nya tertinggal versi lama ikut pulih (termasuk fungsi `aset_url()`).
3. Versi terlihat di-bump → `VERSION` 17.0.18 dan halaman/modul **System Upgrade** (3 tree).

## File yang terlibat

| File | Perubahan |
|---|---|
| `VERSION` | 17.0.17 → 17.0.18 |
| `{app,legacy/app,pos/app}/bootstrap.php` | guard `APP_ASSET_VERSION` + `vk_asset_version()` |
| `{app,legacy/app,pos/app}/Helpers/helpers.php` | versi kanonik (require `asset_helpers.php`) |
| `{app,legacy/app,pos/app}/Helpers/asset_helpers.php` | versi kanonik (definisi + `aset_url()`) |
| `{app,legacy/app,pos/app}/Modules/System/SystemUpgrade.php` | bump versi |
| `legacy/public/admin/system-upgrade/index.php` | bump versi terlihat |

> `pos/app/Config/database.php` **tidak** ikut di paket ini — lihat catatan keamanan di bawah.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_18.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_18.sh
bash apply_patch_v17_0_18.sh
```

Script: backup `.bak` → `unzip -t` → `unzip -o` → verifikasi 9 file + 4 marker → uji runtime konstanta
(print `APP_ASSET_VERSION=17.0.18`).

Lalu **WAJIB**: cPanel → **LiteSpeed Cache Manager → Purge All**. Kalau halaman masih 500 setelah
Purge All, restart PHP/LiteSpeed sekali (proses PHP lama bisa masih memegang file versi sebelumnya —
ini terlihat pada uji lokal: proses lama tetap error sampai dinyalakan ulang).

## Verifikasi

1. `/admin/pos/index.php` → tampil normal (daftar produk + kotak member).
2. `/admin/customers/edit.php?id=<id pelanggan>` → tampil normal, ada field **Password Marketplace**.
3. `/pos/` → normal (500 sebelumnya).
4. `/admin/system-upgrade/index.php` → **v17.0.18**.
5. Terminal: `php -r 'require "legacy/app/bootstrap.php"; echo APP_ASSET_VERSION;'` → `17.0.18`.

## Rollback

```bash
cd ~/public_html
for b in $(find backup_17.0.18_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
```

## ⚠️ Catatan keamanan (dikerjakan terpisah dari paket ini)

`pos/app/Config/database.php` di repo sebelumnya berisi **kredensial DB hardcoded**:

```
host 101.50.1.87 · database coptahco_poshakim · user coptahco_hakimpos · password <REDACTED>
```

Repo ini publik, jadi kredensial itu sudah terekspos. Tindakan yang disarankan:

1. **Ganti password user DB tersebut** di hosting `101.50.1.87` (cPanel → MySQL Users).
2. Bila ada instalasi yang memakai konfigurasi itu, set environment `VAPEKONG_DB_HOST/PORT/NAME/USER/PASS`
   (konfigurasi kini membaca `getenv()` seperti tree lain, tanpa fallback rahasia).
3. File itu **sengaja tidak dikirim** di paket ini supaya instalasi yang sedang memakainya tidak
   langsung putus koneksi. File di repo sudah dibersihkan; riwayat Git lama masih memuatnya.

## Hasil uji lokal sebelum dikirim

- Reproduksi bug: `helpers.php` tanpa `require asset_helpers.php` → halaman POS gagal dengan pesan
  identik (`Undefined constant "APP_ASSET_VERSION" @ legacy/public/layouts/admin-header.php:32`).
- Setelah perbaikan: halaman render normal di 3 kondisi — `helpers.php` stale, `asset_helpers.php`
  hilang total, dan kondisi normal.
- `tests/test_patch_v17_0_18.py`: **9/9 OK** (termasuk uji runtime `require bootstrap` → konstanta =
  isi `VERSION`, dan uji "helpers stale" di tree salinan sementara). Regresi 17.0.17 & 17.0.16 tetap hijau.
- Skrip apply diuji di docroot palsu yang dibuat persis seperti kondisi server (bootstrap tanpa guard +
  helpers lama + asset_helpers hilang): sebelum apply → error yang sama; setelah apply + restart PHP → normal.
- `php -l` bersih untuk semua file di paket.