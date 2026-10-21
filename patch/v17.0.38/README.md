# V17.0.38 — Ambil di Toko lewat batas: batal otomatis, stok kembali

Jawaban singkat: **sekarang ya.** Sebelumnya (v17.0.35) hanya ditandai merah "Lewat batas",
stok belum kembali. Mulai v17.0.38:

## Yang terjadi saat batas ambil terlewat

Pesanan **Ambil di Toko** yang **belum dibayar** dan **belum diambil** sampai `pickup_deadline_at`
otomatis:

1. `order_status` → `expired`, `payment_status` → `expired`, `cancelled_at` diisi.
2. **Stok dikembalikan**: `product_branch_stocks` (atau `marketplace_product_variant_stocks` untuk
   varian) + `products.stock` bila cabangnya cabang utama.
3. Voucher & poin yang terpakai dilepas.
4. Timeline pesanan mencatat **"Batas ambil terlewat"**.

Yang **tidak** disentuh:
- Pesanan yang **sudah dibayar** (perlu refund manual lewat admin) — hanya ditandai "Lewat batas".
- Pesanan yang sudah `completed` / `cancelled` / `expired`.

## Kapan dijalankan

- Otomatis saat halaman pesanan / tracking dibuka (self-healing, seperti expire pembayaran).
- Bisa juga lewat cron (disarankan supaya tetap rapi walau tidak ada yang membuka halaman):

```bash
# cek dulu (dry-run, tidak mengubah apa pun)
php tools/expire_pickup_orders.php

# jalankan
php tools/expire_pickup_orders.php --apply
```

Cron tiap 5 menit di cPanel:

```
*/5 * * * * /usr/local/bin/php /home/USER/public_html/tools/expire_pickup_orders.php --apply >> /home/USER/pickup_expire.log 2>&1
```

## Pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_38.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_38.sh
bash apply_patch_v17_0_38.sh
```
Lalu **Purge All** + Ctrl+F5.

## Perbaikan teknis penting

Semua perbandingan waktu ("lewat batas", sisa waktu batal) sekarang memakai **`NOW()` MySQL**,
bukan `time()` PHP. Timezone PHP (UTC) dan MySQL (+08) bisa berbeda sehingga pesanan bisa
dianggap "belum lewat batas" padahal sudah lewat berjam-jam.

## Verifikasi lokal

| uji | hasil |
|---|---|
| Stok cabang saat order dibuat | 18 → 17 |
| Setelah deadline lewat + halaman dibuka | stok 17 → **18**, master ikut kembali |
| Status | `expired/expired`, `stock_released_at` terisi |
| Event timeline | "Batas ambil terlewat" |
| Order yang belum lewat batas | tidak disentuh (`confirmed`) |
| Order sudah dibayar | tidak dibatalkan (`processing` tetap) |

`tests/test_patch_v17_0_38.py` **8/8 OK**; `php -l` bersih (18 file paket).
