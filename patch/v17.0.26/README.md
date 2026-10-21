# V17.0.26 — Pagination katalog `/produk` (1, 2, 3, …)

Sebelumnya halaman `/produk` memuat maksimal 100 produk sekaligus dan **tidak ada cara** membuka
produk ke-101 dst — produk lain praktis tidak terlihat.

Sekarang:

- **24 produk per halaman** dengan nomor halaman **1, 2, 3, … (… 32)** plus tombol **Sebelumnya /
  Berikutnya** di bawah grid.
- Info jumlah: **"751 Produk ditemukan"** dan **"Menampilkan 25–48 dari 751 produk • halaman 2/32"**.
- Filter **tetap terbawa** saat pindah halaman: `q` (cari), `category`, `brand`, `sort`.
  Contoh: `/produk?category=liquid-freebase&sort=price_asc&page=3`.
- Halaman di luar jangkauan (mis. `?page=999` atau filter menyusut) otomatis jatuh ke halaman terakhir,
  tidak menampilkan halaman kosong.
- Tampilan tombol monokrom (senada tema sekarang), responsif: label "Sebelumnya/Berikutnya" disembunyikan
  di layar kecil, tinggal panah.

Teknis:
- `storefront_products_total($options)` — `COUNT(*)` dengan filter yang sama (dipakai untuk jumlah total),
- `storefront_pagination($page, $pages, $query, $basePath)` — pembangun HTML navigasi (dipakai ulang),
- `products.php` — hitung `$totalPages`, `$offset`, ambil `storefront_products([... 'limit'=>24,'offset'=>...])`,
  render pagination; judul hasil memakai **total**, bukan jumlah baris halaman,
- CSS `.catalog-pagination` ditambahkan ke **4 salinan** `storefront.css` (root + 3 tree).

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_26.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_26.sh
bash apply_patch_v17_0_26.sh
```

Lalu **Purge All** + Ctrl+F5 di halaman `/produk` (CSS-nya ikut berubah).

## Verifikasi

1. Buka `https://vapekongstore.com/produk` → di bawah grid ada **1 2 3 4 … 32**.
2. Klik halaman 2 → URL jadi `/produk?page=2`, produk berbeda, info "Menampilkan 25–48 dari 751".
3. Pilih kategori + urutkan, lalu pindah halaman → filter tidak hilang.
4. `/admin/system-upgrade/index.php` → **v17.0.26**.

## Hasil uji lokal (data produksi `vapekong_prod`)

| uji | hasil |
|---|---|
| halaman 1 | 24 kartu, "751 Produk ditemukan" |
| `?page=2` | 24 kartu, **Menampilkan 25–48 dari 751 • halaman 2/32**, nomor `1 2 3 4 … 32` |
| `?page=32` (terakhir) | **7 kartu**, "Menampilkan 745–751 dari 751", nomor `1 30 31 32` |
| `?category=liquid-freebase&sort=price_asc&page=2` | 199 produk → 9 halaman, link mempertahankan filter |
| `?page=999` | jatuh ke halaman 32 (tidak kosong) |
| produk halaman 1 vs 2 | tidak ada yang sama (offset benar) |

`tests/test_patch_v17_0_26.py` **7/7 OK**; regresi 17.0.22–17.0.25 hijau; `php -l` bersih (15 file paket).