# V17.0.27 — Teks "Poin Member" di kartu akun dibuat putih

Kartu **Poin Member** di halaman `/akun` berlatar **gradient gelap** (`#333333 → #111111`, dari
preset monokrom), tetapi label "Poin Member" masih memakai warna abu `#838383` dari stylesheet
storefront — kontras cuma ±2,6:1, jadi terlihat pudar/tidak terbaca.

Sekarang label (dan angka + ikon) dibuat **putih** di atas kartu gelap: kontras **18,9:1**.

Yang diubah:
- `assets/css/marketplace-theme.css` (+ 3 salinan tree) — aturan override ditaruh **paling bawah**
  supaya menang atas aturan gradient (`!important` sama, urutan yang menentukan):
  `.account-points span / .member-points-card span → color:#fff`,
  `.account-points strong` putih, ikon putih di atas lingkaran putih transparan.
- `store/_header.php` (3 tree) — versi cache-buster CSS yang tadinya **statis** `?v=17.0.20`
  diganti mengikuti `APP_ASSET_VERSION` (fallback `17.0.27`). Tanpa ini browser tetap memakai CSS
  lama dari cache dan perubahan tidak terlihat.

Tidak ada perubahan data, tidak ada query, tidak ada perubahan PHP logic.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_27.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_27.sh
bash apply_patch_v17_0_27.sh
```

Lalu **Purge All** + buka `/akun` dengan **Ctrl+F5** (CSS berubah, wajib hard refresh sekali).

## Verifikasi

1. `/akun` → kartu Poin Member: tulisan "Poin Member" **putih** dan terbaca.
2. Lihat source halaman: `marketplace-theme.css?v=17.0.27` (bukan `17.0.20` lagi).
3. `/admin/system-upgrade/index.php` → **v17.0.27**.

## Hasil uji lokal

Diukur di browser sungguhan (Chromium headless) dengan CSS hasil patch:

| ukuran | hasil |
|---|---|
| latar kartu | `linear-gradient(135deg, rgb(51,51,51), rgb(17,17,17))` |
| warna label | `rgb(255,255,255)` |
| warna angka | `rgb(255,255,255)` |
| kontras label | **18.88:1** (sebelumnya ±2,6:1) |

`tests/test_patch_v17_0_27.py` **5/5 OK**; regresi 17.0.24–17.0.26 hijau; `php -l` bersih (12 file paket).