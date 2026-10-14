# Vapekong POS CI4 v17.0.8

## Fix utama: Pembelian Supplier / transaksi database

- Memperbaiki error `There is no active transaction` saat pembelian supplier, terutama produk varian dengan tujuan Gudang.
- `warehouse_install()` dan `marketplace_v16_install()` tidak lagi menjalankan DDL saat PDO transaction aktif.
- Semua schema dependency pembelian dipasang sebelum `beginTransaction()`.
- Menambah pemeriksaan status transaksi sebelum commit untuk diagnosis yang lebih jelas.
- Pembatalan pembelian sekarang variant-aware untuk Gudang maupun Cabang.
- Stok varian dikurangi kembali saat purchase varian dibatalkan; stok induk hanya disinkronkan sebagai total varian.
- Tidak ada perubahan destructive database.

## Penyebab bug

MySQL melakukan implicit COMMIT ketika menjalankan DDL seperti `CREATE TABLE`/`ALTER TABLE`. Helper stok varian lama memanggil installer schema dari dalam transaksi pembelian. Transaksi jadi selesai tanpa diketahui lalu `$db->commit()` memunculkan PDOException `There is no active transaction`.
