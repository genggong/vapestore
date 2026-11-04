# Patch v17.0.65 — Fix: auto-apply JS/CSS tidak ter-load di LIVE

Gejala: setelah pasang v17.0.64, **auto-apply tidak jalan** — di mobile ketik tidak muncul produk, di desktop harus Enter dulu. (Nge-dropdown juga tidak auto-apply.)

## Akar masalah
JS `storefront.js` dan CSS `storefront.css` yang benar-benar diserve browser di
`https://vapekongstore.com/assets/...` berasal dari folder **ROOT `assets/`**
(bukan dari pohon `public/`, `legacy/public/`, `pos/public/`). Patch v17.0.64 hanya
mengubah 3 pohon itu, jadi perubahan JS/CSS-nya tidak pernah sampai ke file yang
di-load. Deploy-an sebelumnya memang mengaktifkan css (`?v=17.0.64`), tapi **JS
masih `?v=17.0.2`** → browser tetap pegang JS lama yang tanpa auto-apply.

## Perbaikan (v17.0.65)
1. **ROOT `assets/js/storefront.js`** — tambah blok auto-apply: search debounce 300ms → submit otomatis; dropdown (kategori/brand/sort) → submit saat change.
2. **ROOT `assets/css/storefront.css`** — search bar jadi baris penuh horizontal simpel di mobile; sort select full-width.
3. **Bump cache version** di footer/header legacy:
   - `storefront.js?v=17.0.2` → `storefront.js?v=17.0.65`
   - `storefront.css?v=...` → `storefront.css?v=17.0.65`
   Supaya browser tidak menyajikan JS/CSS lama yang ter-cache.

Catatan: server-side (hapus tombol Terapkan/Reset + search multi-token) sudah aktif sejak v17.0.64; patch ini hanya mengaktifkan sisi JS/CSS yang tadi tidak terpasang.

## Isi (5 berkas)
- `assets/js/storefront.js` · `assets/css/storefront.css` (ROOT — yang diserve)
- `legacy/public/store/_header.php` · `legacy/public/store/_footer.php` (versi cache)
- `VERSION` → 17.0.65

## Cara pasang
```bash
cd ~/public_html
wget -qO p17065.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.65/apply.sh"
bash p17065.sh
```
### apply.sh — yang dikerjakan
1. Backup → `~/backup_code_<ts>.tar.gz` (5 file + VERSION)
2. Unduh `PATCH_v17.0.65.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — WAJIB
1. `/admin/system-upgrade/index.php` → **Upgrade Otomatis**
2. **Purge LiteSpeed Cache** (tanpa ini, browser bisa tetap pakai JS lama)
3. Tes: buka `/produk` (idealnya tab incognito/hard refresh) → ketik `3ml 0.6` → hasil muncul otomatis ±300ms; ganti kategori/brand → langsung berubah; foto tetap tampil.
