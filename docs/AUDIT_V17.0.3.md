# Vapekong POS CI4 v17.0.3 — Audit Routing, Menu, Detail & Database

## Akar masalah utama yang diperbaiki

Pada v17.0.2 route catch-all CI4 memakai `(:any) -> LegacyController::handle/$1`, sementara `Config\\Routing::$multipleSegmentsOneParam` masih `false`.
Untuk URL bertingkat, CI4 mengganti `$1` lalu memecah handler berdasarkan `/`. Akibatnya URL seperti:

- `/admin/products/index.php`
- `/admin/marketplace-products/detail.php?id=...`
- `/store/api/create-order.php`
- `/produk/4961-nama-produk`

bisa berubah menjadi beberapa parameter controller, padahal `handle()` hanya menerima satu parameter path. Ini menjelaskan kenapa URL satu segmen seperti `/adm` dapat masuk, tetapi menu admin dan detail produk gagal.

v17.0.3 menghilangkan back-reference `$1` dari catch-all. Controller membaca `REQUEST_URI` secara langsung sehingga seluruh path bertingkat tetap utuh. `multipleSegmentsOneParam=true` juga diaktifkan sebagai compatibility guard.

## Perbaikan bridge legacy

Sebelum menjalankan file lama, bridge sekarang meniru environment eksekusi file asli:

- `SCRIPT_NAME`
- `PHP_SELF`
- `SCRIPT_FILENAME`
- `DOCUMENT_URI`
- current working directory (cwd)

Ini penting karena beberapa halaman lama membaca `SCRIPT_NAME`. Contoh yang ditemukan: modul Promo menentukan mode Tambah/Edit dari nama script. Di bridge lama nilainya selalu `/index.php`, sehingga konteks halaman bisa salah.

## Audit navigasi/source

Hasil static audit build v17.0.3:

- 967 file PHP: 0 syntax error (`php -l`)
- 255 file PHP area legacy/public dipindai untuk navigasi
- 304 target URL lokal unik dari `legacy_base_url(...)`
- 0 target URL lokal hilang setelah alias/clean-route diperhitungkan
- 54 menu sidebar admin diperiksa
- 54/54 target menu tersedia
- 890 `require/include` literal berbasis `__DIR__` diperiksa
- 0 dependency file hilang
- root `/assets` dan `legacy/public/assets` sinkron: 90 file vs 90 file

## Audit database dump yang dikirim

Dump memiliki 77 tabel. Dibanding schema kritis yang dipakai build v17.0.3, perbedaan yang ditemukan adalah:

1. `products.brand_id` belum ada
2. `products.weight_gram` belum ada
3. `finance_transactions.branch_id` belum ada
4. `debts.branch_id` belum ada
5. tabel `shipments` belum ada

Upgrade System & Database menangani seluruh perbedaan ini secara additive (CREATE/ALTER/INDEX) tanpa DROP/TRUNCATE.

Audit dump INSERT:

- 123 statement INSERT
- 44.584 row value diperiksa
- 0 mismatch jumlah kolom vs jumlah value di dump

Jadi error MySQL 1136 yang terlihat pada transaksi bukan berasal dari row dump rusak; sumbernya adalah query runtime aplikasi. Query `transaction_items` pada POS v17 sekarang memakai daftar kolom eksplisit dan jumlah placeholder yang sesuai, termasuk varian/non-varian/bundle.

## Halaman Upgrade Sistem & Database v17.0.3

Halaman sekarang menambahkan:

- status CI4 Bridge & Routing
- audit 54 menu admin
- jumlah file PHP/URL lokal yang dipindai
- target navigasi yang hilang (jika ada)
- schema database kritis
- index performa
- file asset dan media marketplace

## Pengujian yang tetap harus dilakukan di hosting

Karena build environment tidak memiliki koneksi ke database produksi dan konfigurasi webserver cPanel pengguna, pengujian berikut wajib dilakukan setelah patch:

1. `/adm`
2. Dashboard -> Produk
3. Produk -> Tambah/Edit
4. Produk Marketplace -> Detail/Kelola
5. storefront -> detail produk `/produk/{id}-{slug}`
6. POS -> transaksi Cash
7. Pembelian -> Detail
8. Request Stock -> Detail
9. Stock Opname -> Detail/Edit/Template
10. Marketplace -> Checkout/API
11. semua 54 menu admin via sidebar
12. Upgrade Sistem & Database sampai semua pemeriksaan inti OK

## Audit file upload vs database

Halaman doctor v17.0.3 juga membandingkan path `uploads/` yang tersimpan di database dengan file fisik pada hosting. Yang dipindai antara lain gambar produk, galeri marketplace, gambar varian, brand, banner, avatar user, bukti kurir, dan setting media.

Catatan penting: dump yang dikirim mereferensikan banyak file gambar yang tidak berada di ZIP source yang diunggah. Ini tidak berarti file tersebut pasti hilang di server produksi; file upload sering hanya berada di hosting dan memang tidak ikut ZIP source. Karena itu doctor hanya menandai dan tidak menghapus/reset gambar produk secara massal. Jika file dinyatakan hilang di hosting, pulihkan dari backup uploads ke path yang sama.
