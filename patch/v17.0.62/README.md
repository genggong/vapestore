# Patch v17.0.62 — Katalog auto-apply filter + search multi-token + tombol Terapkan/Reset dihapus

Rilis di repo **vapestore**.

## Isi (13 berkas)
- `store/products.php` · `store/_data.php` · `assets/js/storefront.js` · `assets/css/storefront.css` di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `VERSION` → 17.0.62

## Perubahan
1. **Tombol "Terapkan" dan "Reset" dihapus** (semua versi). Filter sekarang **auto-apply**:
   - Search: **debounce 300ms** — hasil ter-update otomatis saat berhenti mengetik.
   - Kategori / Brand / Urutkan: langsung ter-update saat ganti pilihan (change).
2. **Search multi-token** — misal cari `Cartridge Oxva Xlim V3 0.6`, ketik `3ml 0.6` → ketemu.
   Setiap kata harus cocok minimal di satu kolom (nama / kode / deskripsi / kategori) — AND antar kata, OR antar kolom.
3. **Mobile (≤680px)**: search bar jadi baris penuh horizontal yang simpel; kategori+brand sejajar, urutkan di baris penuh.

## Cara pasang
```bash
cd ~/public_html
wget -qO p17062.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.62/apply.sh"
bash p17062.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (13 file + VERSION)
2. Unduh `PATCH_v17.0.62.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: buka `/produk`, ketik `3ml 0.6` → hasil muncul otomatis ±300ms; ganti kategori/brand → auto ter-update; pastikan tombol Terapkan/Reset sudah hilang di desktop & mobile.
