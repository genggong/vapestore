# 🔧 FIX `/store` 500 — v17.0.14 (index.php konsisten)

## Masalah
`/store/index.php` (root public_html) = versi legacy (21436B) berisi `legacy_base_url()`,
tapi `_data.php` di folder itu = versi public (tidak mendefinisikan `legacy_base_url`).
→ `Call to undefined function legacy_base_url()` → **500**. Hanya index.php yang kena.

## Perbaikan (paket ini)
- `store/index.php` → versi PUBLIC (21508B, 0 ref `legacy_base_url`) — konsisten dgn `_data.php` public,
  FAQ sudah hilang dari file ini (konten storefront normal).
- Tidak menyentuh file lain.

## Cara Pakai (cPanel → Terminal)
```bash
cd ~/public_html
wget -q -O fix-store-index.zip "https://github.com/genggong/vapestore/raw/main/patch/v17.0.14/fix-store-index.zip"
unzip -o fix-store-index.zip
php -l store/index.php
```
Lalu **LiteSpeed Cache Manager → Purge All**.

## Verifikasi
- `https://vapekongstore.com/store` → 200
- `https://vapekongstore.com/` → home storefront normal (bukan FAQ)
- `https://vapekongstore.com/produk` → 200
