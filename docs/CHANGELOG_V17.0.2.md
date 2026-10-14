# Vapekong POS CI4 v17.0.2

## Perbaikan utama

- Memperbaiki CI4 LegacyController agar URL bersih lama tidak lagi jatuh ke homepage marketplace.
- Alias `/adm` dan `/admin` sekarang membuka dashboard admin (atau diarahkan login oleh Auth jika belum login).
- Memulihkan route `/login`, `/admin/login`, `/staff/login`, `/produk`, `/masuk`, `/daftar`, `/akun`, `/checkout`, `/tracking`, portal kurir, detail produk, detail bantuan, dan detail pesanan.
- Unknown route sekarang 404 dan tidak lagi salah menampilkan homepage.
- Menambahkan root `/assets` mirror untuk shared hosting agar CSS/JS tidak bergantung penuh pada rewrite ke `legacy/public`.
- Menambahkan fallback reveal: konten marketplace tidak akan blank walaupun `storefront.js` gagal dimuat.
- Menambahkan fallback teks untuk logo jika file logo dan fallback SVG sama-sama gagal.
- Menambahkan versi ringan logo/favicon/hero di root `/uploads/storefront` tanpa menggandakan seluruh upload produk.

## Upgrade Sistem & Database

Menu baru: **Sistem > Upgrade Sistem & Database**
URL langsung: `/admin/system-upgrade/index.php`

Scanner memeriksa:
- tabel dan kolom produk/varian;
- transaction_items POS;
- pembelian dan purchase_items;
- gudang dan stok varian gudang;
- request stock + varian;
- stock opname + varian;
- marketplace order/items/brand/variant;
- finance/debts multi-cabang;
- index performa;
- file CSS/JS inti;
- path logo/favicon/hero/promo marketplace.

Upgrade utama bersifat additive: CREATE TABLE, ADD COLUMN, ADD INDEX. Tidak melakukan DROP/TRUNCATE atau menghapus produk/order/transaksi.

## Temuan dari database snapshot 10 Okt 2026

Database snapshot sudah berisi 77 tabel dan struktur varian utama. Scanner source-vs-schema menemukan gap penting yang perlu diselaraskan:
- `products.brand_id`
- `products.weight_gram`
- `finance_transactions.branch_id`
- `debts.branch_id`
- tabel kompatibilitas `shipments` untuk tracking storefront

Kolom varian `transaction_items`, `purchase_items`, `branch_stock_request_items`, `stock_opname_items`, dan `online_order_items` sudah ada di dump yang diaudit.
