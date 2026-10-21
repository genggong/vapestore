# V17.0.36 — Checkout tidak lagi 500 kalau helper belum termuat

## Kenapa

Halaman `/checkout` memanggil dua helper baru secara langsung:

- `marketplace_cod_enabled()` (v17.0.34)
- `marketplace_store_pickup_close_time()` / `_deadline_label()` (v17.0.35)

Kalau file modulnya belum ikut ter-reload (opcache LiteSpeed masih versi lama) atau patch
terpasang tidak lengkap, PHP menghentikan halaman dengan *"Terjadi kesalahan sistem. Periksa writable/logs."*

## Perbaikan

Semua pemanggilan helper baru di halaman toko sekarang dibungkus `function_exists()`:

- `store/checkout.php` → COD card & keterangan batas ambil cukup disembunyikan kalau helper tidak ada.
- `store/order.php` → baris **Batas Ambil** & peringatan lewat batas dilewati.
- `admin/marketplace-orders/detail.php` → baris batas ambil dilewati.

Jadi halaman tetap tampil normal walau modul COD/batas-ambil belum termuat — tidak ada lagi white-screen.

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_36.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_36.sh
bash apply_patch_v17_0_36.sh
```

Lalu **Purge All** + Ctrl+F5.

## Kalau masih error

Jalankan diagnostik (1 menit):

```bash
cd ~/public_html
wget -qO dbg35.php https://raw.githubusercontent.com/genggong/vapestore/main/tools/dbg35.php
```
Buka `https://vapekongstore.com/dbg35.php?k=dbg35` → kirim hasilnya. File akan mencetak versi PHP,
helper mana yang hilang, nilai setting, status DB, dan **exception persis + file:line** saat
`store/checkout.php` dirender. Setelah selesai: `rm dbg35.php`.

Alternatif dari cPanel: `tail -n 20 ~/public_html/writable/logs/log-$(date +%F).log`

## Verifikasi

`tests/test_patch_v17_0_36.py` 5/5 OK; `php -l` bersih (14 file paket); checkout lokal (lewat
jembatan CI4) HTTP 200, 24 KB.
