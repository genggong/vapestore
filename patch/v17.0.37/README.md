# V17.0.37 — COD di samping QRIS, notif siapkan uang, batal pesanan 10 menit

## 1. Kartu COD sekarang di samping QRIS

Sebelumnya kartu **Bayar di Tempat (COD)** baru muncul di baris paling bawah setelah memilih
Ambil di Toko. Sekarang kartu COD **selalu ada tepat setelah QRIS**:

- Pilih **Ambil di Toko** / **Kurir Toko** → kartu aktif, bisa dipilih.
- Pilih ekspedisi (JNE/J&T/SiCepat) → kartu tampil **redup & tidak bisa diklik**, dengan
  keterangan *"Khusus Ambil di Toko / Kurir Toko"*. Tidak ada lagi kartu yang hilang/hilang timbul.

## 2. Notifikasi "Siapkan uang" setelah checkout

Setelah klik **Buat Pesanan** (COD), halaman pesanan langsung menampilkan banner hitam besar:

> **Siapkan uang tunai Rp 40.000**
> Bayar saat ambil di toko / pesanan diterima kurir toko — tidak perlu transfer.
> Kode pengambilan **VPK-XXXX**

## 3. Batalkan pesanan sendiri — maksimal 10 menit (semua metode bayar)

Di halaman pesanan ada kotak **"Bisa dibatalkan sendiri"** dengan hitungan mundur `09:38`
dan tombol **Batalkan Pesanan** (konfirmasi dulu).

- Berlaku untuk **QRIS, Virtual Account, dan COD** — semua metode.
- Berlaku juga untuk **pembeli tanpa akun** (cukup link pesanan + token).
- Setelah 10 menit: tombol hilang, diganti *"Pembatalan mandiri sudah ditutup"* →
  arahkan ke **Bantuan Pesanan**.
- Pembatalan mengembalikan **stok** (warehouse/branch + master), melepas voucher & poin,
  dan mencatat event **"Dibatalkan pembeli"** di timeline pesanan.
- Pesanan yang sudah **dibayar** tidak bisa dibatalkan sendiri — tetap lewat Bantuan Pesanan
  (perlu refund manual).
- Batas waktu bisa diubah: setting `marketplace_cancel_window_minutes` (default `10`, maks 180)
  — bisa diisi lewat SQL/Pengaturan lanjutan.

## Pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_37.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_37.sh
bash apply_patch_v17_0_37.sh
```
Lalu **Purge All** + Ctrl+F5.

## Verifikasi lokal (sudah dijalankan)

| uji | hasil |
|---|---|
| Urutan kartu checkout | `qris` → `cod` → `bri_va` (+ hint "Khusus Ambil di Toko / Kurir Toko") |
| Halaman order COD | banner "Siapkan uang tunai Rp 40.000", countdown `09:38`, tombol Batalkan |
| Batal QRIS (0 menit) | `cancelled`, stok kembali `ya` |
| Batal setelah 11 menit | **ditolak**, pesan "Batas pembatalan 10 menit sudah lewat" |
| Sisa waktu di 9 menit | ≤ 120 detik → masih boleh |
| Guest tanpa login | batal via token → `cancelled` |
| Umur pesanan | dihitung dari `NOW()` MySQL (tahan beda timezone PHP/DB) |

`tests/test_patch_v17_0_37.py` **9/9 OK**; `php -l` bersih (25 file paket); mirror-test apply lolos.