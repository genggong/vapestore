# Patch v17.0.64 — Katalog auto-apply filter + search multi-token + tombol Terapkan/Reset dihapus

Pengganti aman dari v17.0.62/63 (yang dibatalkan karena memakai base repo yang lebih tua dari live → 500 & foto hilang).

## Pengaman utama
Sebelum fitur dipasang, **base repo di-sinkron dari file live yang benar** (`public/` & `pos/` sudah identik live; `legacy/public/store/_data.php` dan `legacy/public/assets/css/storefront.css` di-sync dari versi live). Jadi hasil patch = **file live + fitur**, fungsi foto (`storefront_product_image`, `storefront_public_file_exists`, `storefront_category_image`) **tidak tersentuh** → foto produk & kategori dijamin tetap jalan. Terverifikasi via test (per-baris identik dgn base live).

## Perubahan
1. **Tombol "Terapkan" dan "Reset" dihapus** (semua versi). Filter auto-apply:
   - Search: **debounce 300ms** — hasil update otomatis saat berhenti ketik.
   - Dropdown Kategori / Brand / Urutkan: langsung submit saat ganti pilihan (change).
2. **Search multi-token** — ketik `3ml 0.6` → cocok bila tiap kata ada minimal di satu kolom (nama/kode/desc/kategori). AND antar kata, OR antar kolom.
3. **Mobile (≤680px)**: search bar jadi baris penuh horizontal yang simpel; kategori+brand sejajar, sort di baris penuh.

## Isi (13 berkas)
- `store/products.php` · `store/_data.php` · `assets/js/storefront.js` · `assets/css/storefront.css` di 3 pohon (public/legacy/pos)
- `VERSION` → 17.0.64

## Cara pasang
```bash
cd ~/public_html
wget -qO p17064.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.64/apply.sh"
bash p17064.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (13 file + VERSION)
2. Unduh `PATCH_v17.0.64.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: buka `/produk` → foto tetap tampil; ketik `3ml 0.6` → hasil lenyap/update otomatis; ganti kategori/brand → auto ter-update; tombol Terapkan/Reset sudah hilang di desktop & mobile.
