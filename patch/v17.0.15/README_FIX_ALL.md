# 🔧 VAPEKONG FIX SATU-KALI — v17.0.15 (Selesaikan /store 500 + / masih FAQ)

Satu file → backup & bersihkan folder nyasar, timpa index.php root ke CI4, verifikasi.

## Cara Pakai (cPanel → Terminal)
```bash
cd ~/public_html
wget -q -O apply-fix-store.zip "https://github.com/genggong/vapestore/raw/main/patch/v17.0.15/apply-fix-store.zip"
unzip -o apply-fix-store.zip
php tools/apply_fix_store.php
```
Lalu **LiteSpeed Cache Manager → Purge All** (penting — tanpa ini / masih menyajikan versi lama).

## Yang dilakukannya
1. **index.php ROOT** → ditimpa versi CI4 (legacy bridge) jika belum; yang lama dibackup `index.php.bak_<timestamp>`
2. **store/ root** (penyebab `/store` 500 — campur versi legacy/public) → backup `store_backup_<timestamp>` + dihapus
3. **admin/ root** (kosong; asli di `legacy/public/admin`) → backup + dihapus
4. **assets/js** (cuma stock-request.js nyasar) → backup + dihapus
Semua folder lain TIDAK disentuh. Semua di-backup dulu sebelum dihapus.

## Verifikasi
- `https://vapekongstore.com/store` → 200 (bukan 500)
- `https://vapekongstore.com/` → home storefront normal (bukan FAQ)
- `https://vapekongstore.com/produk` → 200
