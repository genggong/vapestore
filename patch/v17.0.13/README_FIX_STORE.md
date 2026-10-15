# 🔧 FIX `/store` 500 — v17.0.13

## Masalah
Ekstrak zip fix lama (v17.0.12 FIX_PATH) membuat folder nyasar di ROOT `public_html`:
- `store/` → cuma berisi `index.php` TANPA `_data.php` → `/store` fatal error 500
- `admin/` → kosong (admin asli ada di `legacy/public/admin`)
- `assets/js/` → cuma stock-request.js

## Cara Pakai (sekali saja, di cPanel → Terminal)
```bash
cd ~/public_html
wget -q -O fix-root-store.zip "https://github.com/genggong/vapestore/raw/main/patch/v17.0.13/fix-root-store.zip"
unzip -o fix-root-store.zip
php tools/fix_root_store.php
```

Output yang diharapkan: folder nyasar di-BACKUP ke `store_backup_<timestamp>` dll lalu dihapus,
dan blok `CEK FILE PENTING` menunjukkan semua file `ADA`.

Lalu **Purge cache**: cPanel → LiteSpeed Cache Manager → **Purge All**.
Catatan: kalau wget/unzip gagal, buat file manual via File Manager (isi `tools/fix_root_store.php`
sesuai repo) lalu `php tools/fix_root_store.php`.

## Verifikasi
- `https://vapekongstore.com/store` → 200 (bukan 500)
- `https://vapekongstore.com/` → home storefront normal
- `https://vapekongstore.com/produk` → 200

## Rollback
Kalau ada masalah, cukup rename folder backup kembali:
`mv store_backup_<timestamp> store`
