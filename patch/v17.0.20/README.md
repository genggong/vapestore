# V17.0.20 — Cache-bust CSS Storefront (palet baru sampai ke semua pengunjung)

## Kenapa perlu

Header storefront memakai versi **statis** di query string aset:

```php
assets/css/storefront.css?v=15.14.0
assets/css/marketplace-theme.css?v=15.8.0
```

Artinya URL asetnya tidak pernah berubah, jadi browser pengunjung yang sudah pernah membuka
situs akan memakai CSS versi **lama yang tersimpan di cache browser** — palet hitam-putih dari
v17.0.19 hanya terlihat bagi yang menekan Ctrl+F5. Membersihkan cache server (Purge All) tidak
menolong untuk cache browser.

## Perbaikan

Versi query kedua CSS dinaikkan ke rilis sekarang di **3 header storefront**:

| File | Sebelum | Sesudah |
|---|---|---|
| `legacy/public/store/_header.php` | `?v=15.14.0` / `?v=15.8.0` | `?v=17.0.20` |
| `public/store/_header.php` | sama | `?v=17.0.20` |
| `pos/public/store/_header.php` | sama | `?v=17.0.20` |

Efek: URL aset berubah → **semua** browser mengambil CSS baru sekali, tanpa perlu hard refresh.
Ke depan, cukup naikkan angka versi ini setiap kali CSS storefront berubah.

## Cara pasang (urut: v17.0.19 dulu, lalu ini)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_20.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_20.sh
bash apply_patch_v17_0_20.sh
```

Script: backup `.bak` → `unzip -t` → `unzip -o` → verifikasi 3 header memakai `?v=17.0.20` dan
tidak lagi memuat versi lama → memperingatkan bila palet v17.0.19 belum terpasang.

Lalu Purge All (LiteSpeed Web Cache Manager) — khusus untuk HTML/cache halaman.

## Verifikasi

1. Buka `/` → view-source, cari `storefront.css?v=17.0.20` (bukan `15.14.0`).
2. Tampilan langsung hitam-putih tanpa Ctrl+F5 (setelah Purge All).
3. `/admin/system-upgrade/index.php` → **v17.0.20**.

## Rollback

```bash
cd ~/public_html
for b in $(find backup_17.0.20_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
```

## Hasil uji lokal

- `tests/test_patch_v17_0_20.py`: **7/7 OK** (query versi tiap header ≥ 17.0.20, tidak ada versi lama
  tertinggal, kedua CSS tetap dimuat, CSS monokrom terpasang, versi sistem ikut VERSION).
- Render lokal: `/` HTTP 200, HTML meminta `storefront.css?v=17.0.20` &
  `marketplace-theme.css?v=17.0.20`, dan file yang disajikan berisi `--purple:#111111`.
- `php -l` bersih untuk ketiga header.