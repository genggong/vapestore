# Patch v17.0.66 — Katalog filter instan (AJAX, tanpa reload)

Meningkatkan auto-apply (v17.0.64/65) dari **reload penuh** menjadi **AJAX**: hasil produk
langsung berganti saat mengetik/ganti dropdown, tanpa kedip halaman.

## Yang berubah
1. **`products.php`** (3 pohon, diserve dari legacy): mode `?ajax=1` (atau header
   `X-Requested-With: XMLHttpRequest`) mengembalikan **JSON** berisi HTML katalog
   (jumlah hasil + grid + pagination). Halaman normal tetap sama.
2. **`storefront.js`** (ROOT `assets/js/` — yang diserve live — + 3 pohon):
   - Search → **debounce 300ms** → fetch AJAX, tak reload.
   - Dropdown kategori/brand/sort → fetch AJAX saat ganti.
   - Klik pagination → fetch AJAX.
   - URL diperbarui via `history.replaceState` (back/refresh tetap mempertahankan filter).
   - State loading `.catalog-loading`; jika AJAX gagal → fallback submit biasa.
   - Tombol wishlist/keranjang tetap jalan (event delegation).
3. **`storefront.css`** (root + 3 pohon): tambah `.catalog-loading`.
4. **Bump cache** `?v=17.0.66` di `legacy/public/store/_header.php` (css) & `_footer.php` (js).

## Pengaman
- Base file di-sync dari live; fungsi foto (`storefront_product_image` dkk) & pagination **tidak tersentuh** — test memverifikasi foto identik live.
- Backup otomatis dibuat `apply.sh` sebelum menimpa; titik rollback git `backup-v17.0.65-pre-ajax`.

## Isi (14 berkas)
- `store/products.php` (public/legacy/pos) + `assets/js/storefront.js` + `assets/css/storefront.css` (3 pohon)
- `assets/js/storefront.js` + `assets/css/storefront.css` (ROOT — yang diserve live)
- `legacy/public/store/_header.php` + `_footer.php` (versi cache)
- `VERSION` → 17.0.66

## Cara pasang
```bash
cd ~/public_html
wget -qO p17066.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.66/apply.sh"
bash p17066.sh
```
### apply.sh — yang dikerjakan
1. Backup → `~/backup_code_<ts>.tar.gz` (14 file + VERSION)
2. Unduh `PATCH_v17.0.66.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — WAJIB
1. `/admin/system-upgrade/index.php` → **Upgrade Otomatis**
2. **Purge LiteSpeed Cache**
3. Tes (tab incognito / hard refresh): buka `/produk` → ketik `3ml 0.6` → hasil berganti **instan tanpa reload**; ganti kategori/brand → langsung berubah; klik halaman 2 → tanpa reload; foto tetap tampil.

## Rollback
Jika ada masalah:
```bash
cd ~/public_html
tar -xzf ~/backup_code_<ts>.tar.gz   # dari backup sebelum apply 17.0.66
```
lalu Upgrade Otomatis + purge cache. Atau `git checkout backup-v17.0.65-pre-ajax` di repo.
