# V17.0.24 — Foto Kategori · HPP Rata-rata Pembelian · Request Stok Tidak Diblok Stok Gudang

## 1. Foto kategori (menggantikan icon Bootstrap di storefront)

Sebelumnya kartu kategori di storefront selalu SVG statis (`category-liquid.svg` dsb) dan form
Kategori hanya punya isian teks **Icon Bootstrap** — tidak ada cara memasang foto.

Sekarang, di **POS/admin → Kategori → Tambah/Edit**:

- ada isian **Foto Kategori** (JPG/PNG/WEBP, maks 4 MB, otomatis di-resize + WebP di tree legacy),
- foto lama tampil sebagai pratinjau, ada opsi **Hapus foto, kembali pakai icon**,
- **Icon Bootstrap** tetap ada sebagai cadangan bila foto kosong,
- daftar kategori menampilkan thumbnail foto.

Di storefront, kartu kategori memakai foto kalau ada; kalau tidak, jatuh ke icon/SVG seperti dulu.
Kartu produk yang tidak punya gambar juga memakai foto kategorinya sebagai placeholder.

Teknis:
- kolom baru `categories.image VARCHAR(255) NULL` — otomatis dibuat saat halaman Kategori dibuka
  **atau** lewat **System Upgrade → Jalankan Upgrade Otomatis** (sudah masuk daftar harapan + daftar kolom upgrader),
- helper baru `upload_category_image()`, `category_image_url()`, `categories_ensure_image_column()`,
- `storefront_category_image($name, $image = null)` — baca `categories.image`, fallback ke SVG,
- file disimpan di `uploads/categories/`.

## 2. HPP / modal rata-rata di Pembelian

Rumus rata-rata tertimbang sudah benar dan **diverifikasi ulang** dengan pembelian sungguhan
(lewat form, bukan hitungan manual) pada produk varian:

| langkah | stok gudang | modal varian | modal induk |
|---|---|---|---|
| awal | 0 | 250.000 | 250.000 |
| beli 10 @ 300.000 | 10 | **300.000** | **250.000** (tidak berubah) |
| beli 10 @ 100.000 | 20 | **200.000** = (10×300.000 + 10×100.000)/20 | **250.000** (tidak berubah) |

Yang diperbaiki: pembelian **varian** dulu ikut menulis harga rata-rata varian ke
`products.purchase_price` (modal induk). Akibatnya modal produk induk yang punya beberapa varian
berbeda harga jadi kacau — dan itu ikut menggeser angka **Nilai Modal Stok**. Sekarang
modal induk hanya diubah oleh pembelian **produk induk** (tanpa varian).

## 3. Request stok tidak lagi gagal saat stok gudang 0

Penyebab "request stok gagal": pembuatan request menolak bila stok gudang < qty
(`Stok gudang untuk X tidak cukup. Tersedia 0 pcs.`). Setelah stok gudang dikosongkan, **semua**
request jadi gagal.

Sekarang:
- request = **permintaan** cabang, jadi tetap bisa dibuat walau stok gudang 0/kurang,
- muncul peringatan: *"Request terkirim, tetapi stok gudang belum cukup: … Stok akan dicek saat pemenuhan."*,
- pengecekan keras tetap ada di **saat pemenuhan** (`action.php`) ketika stok benar-benar dikeluarkan,
  jadi stok tidak mungkin minus.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_24.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_24.sh
bash apply_patch_v17_0_24.sh
```

Lalu **Purge All** (LiteSpeed Web Cache Manager).

## Verifikasi di server

1. **Kategori**: POS/admin → Kategori → Edit salah satu kategori → pilih foto → Update →
   buka homepage toko (Ctrl+F5) → kartu kategori memakai foto.
2. **Request stok**: buat request dengan stok gudang 0 → harus **berhasil** dengan peringatan kuning.
3. **Pembelian**: beli varian dengan harga berbeda dari modal sebelumnya → modal **varian** berubah
   jadi rata-rata, modal **induk** tidak berubah.
4. `/admin/system-upgrade/index.php` → **v17.0.24**.

## Rollback berkas (kalau ada yang tidak sesuai)

Script apply otomatis menyiapkan rollback di folder `backup_17.0.24_<tanggal>/`:

```bash
cd ~/public_html
bash backup_17.0.24_*/rollback.sh
```

Script ini memulihkan **29 berkas** yang ditimpa (dari `.bak`) dan menyisakan 3 berkas **baru**
(`*/admin/categories/_helpers.php`) — aman dibiarkan. Setelah rollback: **Purge All** lalu tes `/` dan `/admin`.

## Hasil uji lokal (bukan klaim)

- **Foto kategori**: upload PNG lewat form → baris DB berisi `uploads/categories/category-….png`,
  file ada di disk (87 B) dan **HTTP 200** saat diakses, thumbnail muncul di daftar kategori,
  kartu kategori storefront memakai URL foto itu (`…/uploads/categories/category-….png`),
  kategori lain tetap SVG.
- **HPP rata-rata**: dua pembelian varian lewat form (10 @ 300.000 lalu 10 @ 100.000) →
  modal varian 300.000 → **200.000**, modal induk tetap **250.000**, stok 0 → 10 → 20.
- **Request stok**: stok gudang 0, request qty 3 → **berhasil** (`RST-20261021-0001`, status pending).
- `tests/test_patch_v17_0_24.py`: **13/13 OK** (termasuk uji runtime rumus `avg_hpp`), regresi
  17.0.16–17.0.23 tetap hijau, `php -l` bersih untuk 32 file paket.
- Data uji (kategori/pembelian/request) sudah dihapus lagi dari DB lokal.