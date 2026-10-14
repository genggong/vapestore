POS Stock Sync Fix

Masalah:
- Setelah transaksi POS, stok cabang berkurang.
- Saat admin tambah/update stok dari menu Produk/Stok, yang berubah bisa hanya products.stock.
- POS membaca stok dari product_branch_stocks, sehingga stok di POS terlihat masih lama.

Perbaikan:
- Tambah helper:
  public/admin/stock/_sync_helper.php
- Produk Create/Edit sekarang sinkron ke stok cabang aktif.
- Stok Masuk sekarang menambah stok cabang aktif dan master stock direkalkulasi.
- Adjustment sekarang set stok cabang aktif dan master stock direkalkulasi.
- List pilihan Stok Masuk/Adjustment menampilkan stok cabang aktif.
- POS diberi no-cache header supaya tidak menampilkan halaman lama dari browser.

Patch opsional:
- database/patches/patch-pos-stock-sync-active-branch.sql
  Gunakan hanya jika ingin menyamakan stok cabang utama dengan stok produk master.

Catatan:
- Untuk multi cabang, pastikan admin memilih cabang aktif yang benar sebelum stok masuk/adjustment.
- Tidak mengubah fitur bisnis lain.
