# Audit v17.0.2 - Script + Database

## A. Penyebab screenshot `/adm`
CI4 bridge v17 sebelumnya memakai catch-all route. Path yang tidak menunjuk langsung ke file/folder PHP akan jatuh ke `legacy/public/index.php` (homepage marketplace). Karena `/adm` bukan file/folder, URL admin tersebut salah dirender menjadi marketplace. Ini diperbaiki dengan explicit clean-route resolver dan alias `/adm`/`/admin` ke dashboard admin.

## B. Penyebab area tengah marketplace tampak kosong
Komponen homepage memakai class `.reveal` yang default `opacity:0` dan baru menjadi terlihat setelah JavaScript `storefront.js` menambahkan `.in-view`. Loader hotfix v17.0.1 sudah dapat hilang walaupun JS gagal, tetapi jika JS gagal, konten reveal masih tersembunyi. v17.0.2 menambahkan fallback untuk selalu membuat konten visible walau JS gagal.

## C. Asset shared hosting
v17.0.2 menambahkan mirror root `/assets` (~kecil) agar CSS/JS dapat dilayani langsung sebagai real file pada cPanel/LiteSpeed. Upload produk tetap berada di `legacy/public/uploads` dan hanya diakses lewat fallback rewrite sehingga tidak menggandakan seluruh media.

## D. Audit database snapshot
Snapshot `vapekong_pos.sql` memiliki 77 tabel. Struktur inti varian sudah ada untuk transaksi, pembelian, request stock, stock opname, marketplace variant stock, dan gudang variant stock.

Gap source-vs-schema yang ditemukan:
- products.brand_id belum ada
- products.weight_gram belum ada
- finance_transactions.branch_id belum ada
- debts.branch_id belum ada
- tabel shipments belum ada, sementara storefront tracking memiliki query kompatibilitas ke tabel tersebut

Gap di atas diperbaiki oleh menu Upgrade Sistem & Database.

## E. Error SQLSTATE 21S01 / MySQL 1136
Source POS diperiksa ulang. Insert `transaction_items` di versi baru memakai daftar kolom eksplisit termasuk jalur varian/non-varian sehingga tidak bergantung pada jumlah seluruh kolom tabel. Upgrader juga memastikan kolom varian tetap tersedia.

## F. Audit menu
54 target menu sidebar admin diperiksa: tidak ada target file yang hilang pada paket v17.0.2.

## G. Syntax
Seluruh file PHP dalam paket v17.0.2 lolos `php -l` tanpa parse error.
