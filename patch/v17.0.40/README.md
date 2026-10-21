# V17.0.40 — QRIS & Virtual Account: bayar maksimal 30 menit, lewat itu batal otomatis

## Yang berubah

Setiap pesanan QRIS / Virtual Account sekarang punya **batas bayar 30 menit** (bisa diubah lewat
setting `marketplace_payment_timeout_minutes`).

- Halaman pesanan menampilkan **hitung mundur** (*Selesaikan pembayaran dalam 29:12*) dan
  keterangan *"Lewat dari itu pesanan otomatis dibatalkan dan stok dikembalikan."*
- Kalau waktu habis: `payment_status=expired`, `order_status=expired`, **stok kembali**
  (cabang/varian + master), voucher & poin dilepas, timeline mencatat "Pembayaran kedaluwarsa".
- Berlaku juga untuk pesanan yang instruksi pembayarannya **gagal dibuat** (`payment_status=error`)
  yang masih menahan stok.
- COD tidak terpengaruh (COD memang tanpa batas bayar; batasnya batas ambil di toko).

## Kenapa dulu tidak jalan

Perbandingan waktu memakai `time()` PHP sementara `payment_expired_at` diisi MySQL — beda timezone
membuat pesanan tidak pernah dianggap kedaluwarsa. Sekarang semua perbandingan memakai `NOW()`
MySQL, plus fallback `created_at + 30 menit` kalau kolomnya kosong.

## Pembersih otomatis

```bash
php tools/expire_orders.php          # dry-run, lihat daftar
php tools/expire_orders.php --apply  # batalkan + stok kembali
```

Cron tiap 5 menit (ganti `USER`):

```
*/5 * * * * /usr/local/bin/php /home/USER/public_html/tools/expire_orders.php --apply >> /home/USER/expire_orders.log 2>&1
```

Tool ini sekaligus membereskan **batas ambil di toko** yang lewat.

## Pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_40.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_40.sh
bash apply_patch_v17_0_40.sh
```
Lalu **Purge All** + Ctrl+F5.

Kalau docroot masih di bawah 17.0.35, pakai paket kumulatif:
`apply_patch_v17_0_40_FULL.sh`

## Verifikasi lokal

| uji | hasil |
|---|---|
| Buat order QRIS | `payment_status=pending`, `payment_expired_at` = +30 menit |
| Sisa waktu | 1800 detik (= 30 menit) |
| Dicek sebelum lewat | tetap `pending`, stok tetap terpotong |
| Waktu dimundurkan 31 menit | `expired/expired`, **stok kembali** |
| `payment_expired_at` kosong, created 31 menit lalu | tetap `expired`, stok kembali |
| Order error (instruksi gagal) | ikut dibatalkan setelah batas |
| COD | tidak tersentuh |

`tests/test_patch_v17_0_40.py` **8/8 OK**; `php -l` bersih (12 file paket).
