# V17.0.19 — Palet Storefront Hitam‑Putih + Pemulihan Tampilan "Buat Request Stock"

## 1. Palet jadi putih–hitam (biru/ungu → hitam)

Tema marketplace di server disetel `graphite` (`#334155`, biru keabu-abuan) dan warna dekoratif ungu
masih tersebar sebagai nilai literal di CSS storefront, jadi mengganti preset saja tidak cukup.
Yang dikerjakan:

- **`storefront.css` + `marketplace-theme.css`** ditulis ulang ke palet monokrom di **4 salinan tree**
  (`assets/css`, `legacy/public/assets/css`, `public/assets/css`, `pos/public/assets/css`):
  - aksen ungu/biru (`#7c3aed`, `#6d28d9`, `#5b21b6`, `#8b5cf6`, `#a855f7`, `#2563eb`, `#4f46e5`, …) → **`#111111` / `#333333` / `#a3a3a3`**
  - tint ungu muda (backgrounds/border seperti `#f2eaf9`, `#eee7f0`, `#e8d7ff`, `#ddd0e8`, …) → abu netral **dengan kecerahan sama** (kontras tetap, cuma warna hue-nya hilang)
  - `rgba(124,58,237,…)` → `rgba(17,17,17,…)`
  - **warna status tetap semantik**: merah `#be123c`/`#e82929`, hijau `#047857`, kuning/amber tidak diubah
  - total konversi: **1.532 nilai hex + 136 rgba**; hasil audit: **0 warna biru/ungu dekoratif** tersisa
- **Preset tema baru `monochrome` ("Hitam Putih (Monokrom)")** ditambahkan di `Marketplace.php` (3 tree)
  → `primary #111111`, `secondary #000000`, `accent #333333`. Preset lama (purple, blue, graphite, dst.) tetap ada.
- **`tools/set_theme_mono.php`** — set preset tema dari terminal: `php tools/set_theme_mono.php`
  (kembalikan: `--revert`, atau `--preset=blue`). Aman diulang, hanya menyentuh baris setting `marketplace_theme_*`.

## 2. Tampilan "Buat Request Stock" dipulihkan

Gejala: daftar produk di halaman **Buat Request Stock** tidak muncul. Di repo, halaman ini render
normal (751 grup produk / 937 baris dari data produksi) — jadi masalahnya file di server yang
bercampur generasi: `stock-request.js` yang disajikan terukur **4.234 / 1.242 / 4.026 byte** di
empat jalur, semuanya berbeda dari versi repo (5.086 byte).

Yang dikirim ulang (semua tree, supaya tidak ada generasi bercampur):

| File | Isi |
|---|---|
| `{legacy/public,public,pos/public}/admin/stock-requests/{create,index,detail,action}.php` | halaman + markup daftar produk (`data-product-group`, `request-product-row`, stok gudang) |
| `{assets,legacy/public/assets,public/assets,pos/public/assets}/js/stock-request.js` | JS versi terbaru (sync stok gudang di keranjang + filter berkelompok) |
| `{assets,legacy/public/assets,public/assets,pos/public/assets}/css/warehouse.css` | gaya panel daftar produk |
| `{app,legacy/app,pos/app}/Modules/CatalogFast.php` | modul pembaca katalog massal yang di-`require` halaman itu |

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_19.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_19.sh
bash apply_patch_v17_0_19.sh
```

Script: backup `.bak` (39 file) → `unzip -t` → `unzip -o` → verifikasi 7 file + 4 marker
(palet hitam, preset monochrome, markup daftar produk, JS terbaru) → jalankan `set_theme_mono.php`.

Lalu **WAJIB**: LiteSpeed Cache Manager → **Purge All**, dan **Ctrl+F5** di browser
(aset CSS/JS lama masih tersimpan di cache browser per rilis sebelumnya).

## Verifikasi

1. `/` dan `/produk` → sekarang putih–hitam (tidak ada lagi tombol/badge ungu atau biru).
2. `/admin/stock-requests/create.php` → daftar produk muncul (grup "PRODUK INDUK" + baris varian, badge stok gudang).
3. `/admin/system-upgrade/index.php` → **v17.0.19**.
4. Cek server-side kalau daftar masih kosong:
   ```bash
   wget -qO tools/dbg_admin_error.php https://raw.githubusercontent.com/genggong/vapestore/main/tools/dbg_admin_error.php
   # buka: https://vapekongstore.com/tools/dbg_admin_error.php?k=dbg17&p=admin/stock-requests/create.php
   ```
   Output `PENANDA` akan menyebut `data-product-group=751 | request-product-row=937` bila daftar
   benar-benar terkirim dari server. Lalu hapus: `rm -f tools/dbg_admin_error.php`.

## Rollback

```bash
cd ~/public_html
for b in $(find backup_17.0.19_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
php tools/set_theme_mono.php --revert     # warna tema kembali ke graphite
```

## Hasil uji lokal sebelum dikirim

- Palet: 6 halaman storefront (`/`, `/produk`, `/store`, `/masuk`, `/keranjang`, `/daftar`) HTTP 200,
  **0** warna biru/ungu di HTML yang disajikan; `--purple:#111111`.
- Request stock: halaman render 751 grup / 937 baris; JS 4 jalur identik (5.086 byte).
- `tests/test_patch_v17_0_19.py`: **12/12 OK** (audit hue CSS, variabel tema hitam, warna status utuh,
  preset monochrome 3 tree, penanda daftar produk 3 tree, JS 4 jalur identik, versi).
- Skrip apply diuji di docroot palsu kondisi pra-patch: exit 0, semua marker lolos, `.env` tidak tersentuh,
  14 file `.bak` dibuat untuk rollback.
- `php -l` bersih untuk seluruh isi paket (40 file).