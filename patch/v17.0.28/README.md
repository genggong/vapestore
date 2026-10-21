# V17.0.28 — "0 Pesanan Online" di beranda: sekarang ambil data nyata

## Dari mana angka itu diambil?

Kartu statistik di beranda (`/`) diisi oleh `storefront_stats()` di `store/_data.php`:

| kartu | sumber |
|---|---|
| Total Produk | `COUNT(*) FROM products WHERE is_active = 1` |
| **Pesanan Online** | `COUNT(*) FROM online_orders` ← **hanya pesanan checkout web** |
| Pelanggan Aktif | `COUNT(*) FROM customers` |
| Cabang Toko | `COUNT(*) FROM branches WHERE is_active = 1` |

Jadi angka **0** itu bukan salah hitung: memang semua penjualanmu masuk lewat **kasir POS**
(`transactions`), sedangkan tabel `online_orders` baru terisi kalau ada pelanggan checkout sendiri
di website. Karena belum ada, hasilnya 0 — dan `0+` di beranda justru terlihat buruk.

## Ketemu satu bug serius di jalan

Saat menelusuri, ketemu akar masalah lain: fungsi `storefront_table_exists()` memakai
`SHOW TABLES LIKE ?`. **MySQL tidak mengizinkan `SHOW` di-prepare** (error 1064), dan `catch`-nya
menelan error itu → fungsi **selalu mengembalikan `false`** untuk SEMUA tabel.

Akibatnya (diam-diam, tanpa error):
- filter **brand** di `/produk` tidak pernah aktif,
- data **varian** & stok varian tidak pernah dibaca,
- **voucher/promo** tidak pernah ketemu,
- **Lacak Pesanan** (`/tracking`) selalu "Pesanan belum ditemukan" — termasuk untuk pesanan yang
  benar-benar ada.

Sekarang memakai `information_schema.TABLES`.

## Yang berubah

1. `storefront_table_exists()` → query `information_schema` (3 tree). Semua fitur di atas hidup lagi.
2. `storefront_stats()` → angka pesanan diambil dari **data nyata**:
   `pesanan web (order_status ≠ cancelled)` + `transaksi kasir status = success`, dan pesanan web yang
   sudah dicatat ke POS (`transactions.reference_type = 'online_order'`) **tidak dihitung dua kali**.
3. Label kartu: **"Pesanan Online" → "Pesanan Selesai"** (karena angkanya mencakup kasir + web).
   Kalau mau tetap "Pesanan Online" khusus web, tinggal bilang — angkanya sudah dipisah
   (`orders_online` dan `orders_pos` disimpan terpisah).

Tidak ada perubahan skema database, tidak ada data yang ditulis.

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_28.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_28.sh
bash apply_patch_v17_0_28.sh
```

Lalu **Purge All** + Ctrl+F5 di beranda.

## Hasil uji (data produksi `vapekong_prod`)

| uji | hasil |
|---|---|
| kartu beranda | Total Produk **751+**, **Pesanan Selesai 467+**, Pelanggan **269+**, Cabang **2** |
| angka vs SQL | 1 pesanan web (completed) + 466 transaksi kasir = **467** ✔ |
| `/produk` | 751 produk, filter kategori 199, cari "lunar" 17 — tidak ada perubahan perilaku |
| `/tracking?code=WEB-20261014-152303-F345` | **ditemukan** (sebelumnya selalu "belum ditemukan") |
| `/tracking?code=INV-20261017-0001` | **ditemukan** (invoice POS) |
| pesanan batal (2 baris) | tidak ikut dihitung ✔ |

`tests/test_patch_v17_0_28.py` **7/7 OK** (termasuk uji runtime angka == SQL dan uji tracking);
regresi 17.0.24–17.0.27 hijau; `php -l` bersih (11 file paket).