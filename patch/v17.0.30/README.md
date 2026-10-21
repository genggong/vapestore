# V17.0.30 — Sort "Terlaris" + notifikasi WhatsApp pesanan ke nomor cabang

## 1. Sort produk: Terlaris & stok habis selalu di bawah

Halaman `/produk` sekarang punya pilihan urutan **Terlaris** dan **semua urutan** (Terlaris, Nama A-Z,
Terbaru, Harga Terendah, Harga Tertinggi, Stok Terbanyak) otomatis menaruh produk **stok habis di
paling bawah** — sebelumnya hanya "Nama A-Z" yang begitu, dan halaman produk di tree `public/` belum
punya opsi "latest" sama sekali.

Angka terlaris = **jumlah unit terjual** dari:
- transaksi kasir POS (`transaction_items` pada `transactions.status='success'`), dan
- pesanan online (`online_order_items` yang tidak `cancelled`/`expired`).

Kalau tabel penjualan belum ada di server, ekspresi otomatis jadi `0` (tidak error).

## 2. Notifikasi WhatsApp otomatis ke nomor WA cabang

Setiap pesanan marketplace baru masuk → WA otomatis ke **nomor WhatsApp cabang pengirim**
(`branches.phone`). Isi pesan: nomor pesanan, cabang, nama+WA penerima, alamat singkat, metode
pengiriman, daftar item + harga, total + ongkir, status pembayaran, waktu, dan instruksi memproses.

Cara kerja:
- Pesanan **dicatat dulu ke antrean** tabel `marketplace_order_notifications` (cepat, checkout tidak
  menunggu API WA).
- Pengiriman dilakukan **setelah respons checkout terkirim** ke pembeli (`fastcgi_finish_request` /
  `litespeed_finish_request`), jadi pembeli tidak ikut menunggu.
- Gateway dipakai berurutan: **WhatsApp Marketplace (Teknorilis)** bila aktif → fallback **Fonnte**
  (modul WhatsApp POS) → kalau tidak ada yang aktif baris ditandai `failed` dengan alasannya.
- Gagal kirim tidak pernah membatalkan pesanan. Bisa dikirim ulang:
  ```bash
  php tools/send_pending_order_notifications.php            # lihat antrean (dry-run)
  php tools/send_pending_order_notifications.php --send --retry
  ```

Pengaturan baru di **POS → Pengaturan Marketplace → WhatsApp Marketplace**:
- **Notifikasi Pesanan Masuk**: Aktif (kirim ke cabang) / Nonaktif — default **Aktif**.
- **Nomor WA Tujuan Notifikasi (opsional)**: kosongkan untuk memakai nomor WA cabang; boleh lebih dari
  satu nomor (pisahkan koma).

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_30.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_30.sh
bash apply_patch_v17_0_30.sh
```

Lalu **Purge All** + Ctrl+F5.

## Verifikasi

1. `/produk` → dropdown urutan ada **Terlaris**; pilih itu → produk paling laku di atas.
2. Halaman terakhir urutan apa pun → semua kartu bertanda **Habis**.
3. Buat pesanan uji di website (boleh pesanan murah) → nomor WA cabang menerima pesan
   "*PESANAN ONLINE BARU*". Kalau belum masuk, cek antrean:
   `php tools/send_pending_order_notifications.php`
4. `/admin/system-upgrade/index.php` → **v17.0.30**.

## Hasil uji lokal (data produksi `vapekong_prod`)

| uji | hasil |
|---|---|
| sort Terlaris | 3 teratas = Cartridge Oxva Xlim V3 (12 terjual), Geekvape Wenax M1 (6), Foom X (5) — sama dengan hitungan SQL |
| sort Harga Terendah | halaman 1 semua berstok, **halaman 32 semuanya "Habis"** |
| antrean notifikasi | pesanan #10 → target `08981111413` (WA cabang Mejasem), status `pending`, isi pesan lengkap |
| gateway mati | dispatch mencoba, hasil `failed` + alasan "Token Fonnte belum diisi" — **tidak ada WA terkirim**, pesanan tidak terganggu |
| pengaturan | halaman Marketplace Settings 200, dua field baru tampil |
| tool kirim ulang | `php tools/send_pending_order_notifications.php` → "Antrean ... 0 baris" (dry-run) |

`tests/test_patch_v17_0_30.py` **12/12 OK**; regresi 17.0.27–17.0.29 hijau; `php -l` bersih (18 file paket).