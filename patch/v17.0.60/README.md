# Patch v17.0.60 — POS search filter dynamic: debounce 300ms (antisipasi lag)

Rilis di repo **vapestore**.

## Isi (4 berkas)
- `admin/pos/index.php` di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `VERSION` → 17.0.60

## Perubahan
Search pintar POS (fitur v17.0.42) sebelumnya mengeksekusi `applyPosSmartSearch()`
**langsung di setiap ketikan** → pada katalog besar terasa lag/kepala putar.

Sekarang diberi **debounce 300ms**: pencarian dijalankan 300ms SETELAH jeda mengetik
(clearTimeout + setTimeout 300). Hasil tetap sama akurat, tapi tidak memfilter ulang
tiap huruf — lebih ringan & tidak lag.

Agar fitur **Enter → produk tinggal 1 langsung masuk keranjang** (17.0.42) tetap akurat
kalau user menekan Enter cepat (< 300ms), handler Enter kini **flush** pencarian tertunda
(clearTimeout + applyPosSmartSearch) sebelum membaca hasil.

## Cara pasang
```bash
cd ~/public_html
wget -qO p17060.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.60/apply.sh"
bash p17060.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (3 file + VERSION)
2. Unduh `PATCH_v17.0.60.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu.
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes di POS: ketik "xli 3ml" — hasil menyesuaikan dengan jeda ketik (tidak kaku per huruf); tekan Enter saat hasil satu → langsung masuk keranjang.
