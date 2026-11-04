# Patch v17.0.63 — Fix 500 /produk (legacy `_data.php` kehilangan pagination + total)

Hotfix untuk regresi yang masuk ke rilis **v17.0.62**.

## Gejala
Buka `https://vapekongstore.com/produk` → **HTTP 500 "Terjadi kesalahan sistem.
Periksa writable/logs."** (home `/` tetap normal).

## Akar masalah
Halaman `/produk` disajikan lewat **LegacyController bridge** yang menjalankan pohon
`legacy/public/store/`. Di `legacy/public/store/products.php` dipanggil
`storefront_products_total()` (hitung jumlah) dan `storefront_pagination()`
(navigasi halaman), **tetapi kedua fungsi itu TIDAK ada** di
`legacy/public/store/_data.php` (terpotong saat refactor v17.0.50) →
`Call to undefined function` → 500.

Patch v17.0.62 ikut meng-overwrite `legacy/_data.php` dengan versi repo yang
terpotong, sehingga bug yang tadinya tersembunyi jadi kelihatan.

## Perbaikan (v17.0.63)
Tambah kembali `storefront_products_total()` dan `storefront_pagination()` di
`legacy/public/store/_data.php` (versi legacy pakai `legacy_base_url`), search
total memakai multi-token `storefront_search_clause` agar jumlah cocok dengan
daftar produk.

## Isi (13 berkas)
- `store/products.php` · `store/_data.php` · `assets/js/storefront.js` · `assets/css/storefront.css` di 3 pohon
- `VERSION` → 17.0.63

## Cara pasang
```bash
cd ~/public_html
wget -qO p17063.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.63/apply.sh"
bash p17063.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (13 file + VERSION)
2. Unduh `PATCH_v17.0.63.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: buka `/produk` → harus 200 normal; ketik `3ml 0.6` → hasil muncul otomatis; ganti kategori/brand → auto ter-update; tombol Terapkan/Reset sudah hilang.
