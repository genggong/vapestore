# Vapekong POS CI4 v17.0.9 — Pakasir API v2

Tanggal audit: 14 Oktober 2026.

## Audit Pakasir

Pakasir API v1 sudah deprecated dan menurut dokumentasi resmi akan dihentikan pada 20 Oktober 2026. Source v17.0.8 masih memakai endpoint v1 pada QRIS POS dan QRIS piutang, sedangkan Marketplace create transaction sudah memakai v2 tetapi daftar metode pembayaran dan webhook belum sepenuhnya mengikuti v2.

## Upgrade v17.0.9

- POS QRIS create: migrasi ke `POST /api/v2/create-transaction/{slug}/{order_id}`.
- POS status: migrasi ke `GET /api/v2/transaction-status/{slug}/{txn_id}` dengan header `X-Api-Key`.
- POS metadata menyimpan `txn_id`, `qr_string`, `is_sandbox`, dan `api_version=v2`.
- Webhook POS mendukung payload v2 dan verifikasi header `X-Secret`.
- QRIS piutang: create + status dimigrasikan ke API v2 dan tabel `customer_debt_qris` mendapat kolom/index `txn_id`.
- Marketplace: daftar metode pembayaran disesuaikan dengan metode v2 yang tersedia: QRIS, BRI VA, BNI VA, CIMB Niaga VA, Danamon VA, Maybank VA, BNC VA, BSI VA, Muamalat VA, OCBC VA.
- Marketplace webhook mendukung `X-Secret`; URL lama `?token=` masih diterima sebagai fallback migrasi bila secret v2 belum diisi.
- Customer order polling sekarang melakukan fallback status langsung ke Pakasir v2 setiap 9 detik bila order lokal masih pending dan memiliki `payment_txn_id`. Ini membantu bila webhook terlambat/gagal.
- Validasi nominal mengikuti batas dokumentasi v2: QRIS Rp500–Rp10.000.000; VA Rp10.000–Rp50.000.000.
- Halaman Pengaturan POS dan Marketplace mendapat input Webhook Secret Pakasir v2.
- System Upgrade/Database Doctor mendapat audit Pakasir v2 dan pemeriksaan `customer_debt_qris.txn_id`.
- Transaksi lama yang tidak memiliki `txn_id` masih memakai fallback status v1 untuk kompatibilitas histori selama endpoint lama masih tersedia. Semua transaksi baru menggunakan v2.

## Setelah memasang patch

1. Buka **Admin → Pengaturan POS** dan isi `Slug Project`, `API Key`, serta `Webhook Secret (X-Secret)` dari project Pakasir POS.
2. Pasang URL webhook POS yang ditampilkan pada project Pakasir POS.
3. Buka **Admin → Pengaturan Marketplace**, isi credential project Marketplace dan `Webhook Secret Marketplace (X-Secret)`.
4. Pasang URL webhook Marketplace yang ditampilkan pada project Pakasir Marketplace.
5. Buka **Upgrade Sistem & Database v17.0.9** lalu jalankan Upgrade Otomatis sekali agar kolom/index `txn_id` QRIS piutang tersedia.
6. Tes transaksi Sandbox/nominal kecil untuk POS QRIS dan checkout Marketplace.

## Audit teknis

- 968 file PHP diperiksa dengan `php -l`.
- 0 syntax error.
- Tidak ada lagi endpoint v1 `transactioncreate` pada source aktif.
- Sisa endpoint v1 `transactiondetail` hanya berada pada fallback transaksi lama tanpa `txn_id`.
