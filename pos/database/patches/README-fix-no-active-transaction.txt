Fix error POS: There is no active transaction

Penyebab:
branch_sync_product() memanggil branch_install() saat proses transaksi POS.
branch_install() menjalankan CREATE/ALTER TABLE. Pada MySQL, DDL menyebabkan implicit commit.
Akibatnya saat POS memanggil $db->commit(), transaksi sudah tidak aktif dan muncul:
SQLSTATE / PDOException: There is no active transaction

Perbaikan:
- branch_sync_product() tidak lagi memanggil branch_install().
- POS commit dibuat aman dengan cek $db->inTransaction().
