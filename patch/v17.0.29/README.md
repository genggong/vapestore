# V17.0.29 — Tarif Kurir Toko per cabang, kirim dari cabang berbeda, stok katalog gabungan

Sebelumnya: tarif Kurir Toko **satu daftar global** (tidak ada kolom cabang), pesanan online selalu
dipotong dari **satu cabang** (`settings.marketplace_branch_id`), dan stok di web hanya menampilkan
stok cabang itu — jadi produk bisa "Habis" padahal ada di cabang lain.

Sekarang tiga-tiganya diperbaiki.

## 1. Tarif Kurir Toko bisa disetel per cabang

- Tabel `marketplace_store_courier_rates` dapat kolom **`branch_id`** (0 = tarif umum/semua cabang).
- Halaman **Admin → Tarif Kurir Toko** kini punya pilihan **Cabang Pengirim** + kolom **Cabang** di
  daftar tarif ("Semua cabang" / nama cabang).
- Aturan lookup: **tarif khusus cabang menang** atas tarif umum untuk kelurahan yang sama. Kalau
  cabang itu belum punya tarif sendiri → otomatis pakai tarif umum (jadi tarif lama tetap jalan).
- Unique key diganti dari `(destination_id)` menjadi `(destination_id, branch_id)` supaya satu
  kelurahan boleh punya beberapa tarif (satu per cabang). Migrasi idempoten + aman diulang.

## 2. Pesanan dikirim dari cabang yang benar

- Fungsi baru `marketplace_delivery_branch()`: pilih cabang pengirim = **cabang yang mampu memenuhi
  SELURUH isi keranjang** (prioritas cabang marketplace/utama, lalu cabang aktif lain).
- `create_order()` memakai cabang itu, jadi stok dipotong dari cabang pengirim (dengan `FOR UPDATE`
  seperti sebelumnya) dan invoice POS otomatis ikut cabang tersebut.
- API `store/api/shipping-cost.php` menghitung ongkir memakai **cabang pengirim yang sama** sehingga
  tarif per cabang benar-benar terpakai, dan respons menyertakan `sender_branch_id` +
  `sender_branch_name`.
- Ambil di Toko (pickup) tidak berubah: sudah multi-cabang sejak sebelumnya.

## 3. Stok di katalog = gabungan semua cabang aktif

- Katalog (`/produk`, kartu produk, detail produk) menampilkan **jumlah stok semua cabang aktif**
  (`product_branch_stocks`), termasuk **per varian** (`marketplace_product_variant_stocks`).
- Validasi & pemotongan stok tetap **per cabang** saat checkout, jadi tidak ada oversell: kalau
  keranjang melebihi stok satu cabang, sistem memilih cabang lain yang cukup; kalau tidak ada yang
  cukup, checkout menolak dengan pesan stok yang jelas.
- Kalau tabel stok per cabang belum ada, otomatis fallback ke `products.stock` cabang utama.

## Tidak ada perubahan data

Tidak ada DROP/DELETE data. Yang berubah hanya **penambahan kolom + index** dan penggantian unique
key lama ke unique key gabungan (data tarif lama otomatis menjadi `branch_id = 0` = tarif umum).

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_29.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_29.sh
bash apply_patch_v17_0_29.sh
```

Lalu **Purge All** + Ctrl+F5. Migrasi kolom berjalan otomatis saat halaman pertama dibuka.

## Verifikasi

1. **POS → Tarif Kurir Toko**: pilih cabang "Vapekong Slawi", simpan tarif kelurahan → daftar
   menampilkan baris dengan kolom **Cabang = Vapekong Slawi** di atas tarif umum.
2. Cek tarif berbeda untuk kelurahan yang sama (umum Rp 6.000, cabang Rp 9.999).
3. **Website**: produk yang stoknya hanya ada di cabang 2 sekarang **tampil "Tersedia"** dengan
   jumlah gabungan.
4. Checkout dengan qty melebihi stok cabang utama → otomatis dipenuhi cabang lain; cek pesanan di
   POS tercatat pada cabang pengirim.
5. `/admin/system-upgrade/index.php` → **v17.0.29**.

## Hasil uji lokal (data produksi `vapekong_prod`)

| uji | hasil |
|---|---|
| migrasi skema | kolom `branch_id` ada, unique `(destination_id,branch_id)` aktif, unique lama hilang |
| tarif per cabang | kelurahan sama: cabang 2 → **Rp 9.999**, cabang 1 → **Rp 6.000** (tarif umum), tanpa cabang → Rp 6.000 |
| stok katalog | produk tanpa varian cabang1=2 + cabang2=11 → detail & katalog **13** |
| stok varian | varian cabang1=0 + cabang2=6 → halaman detail **"Tersedia 6"** |
| cabang pengirim | stok cabang 1 = 0, cabang 2 = 4, keranjang 3 pcs → `marketplace_delivery_branch` = **2** |
| halaman admin | 200, ada select Cabang + kolom Cabang, baris "Vapekong Slawi" tampil |

`tests/test_patch_v17_0_29.py` **11/11 OK**; regresi 17.0.26–17.0.28 hijau; `php -l` bersih (23 file).