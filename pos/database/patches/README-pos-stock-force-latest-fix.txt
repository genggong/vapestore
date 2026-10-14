POS Stock Force Latest Fix

Masalah:
- Produk/Stok sudah bertambah, tetapi POS masih menampilkan stok lama.
- Penyebab umum: POS membaca product_branch_stocks, sementara update stok terbaru masuk ke products.stock atau updated_at produk lebih baru dari stok cabang.

Perbaikan:
- POS sekarang menampilkan stok efektif:
  Jika products.updated_at lebih baru dari product_branch_stocks.updated_at dan stok produk lebih besar, POS memakai products.stock.
  Selain itu POS tetap memakai stok cabang.
- Saat POS dibuka, stok cabang aktif otomatis disinkron dari products.stock hanya untuk produk yang data master-nya lebih baru dan stoknya lebih besar.
- Query cek stok transaksi POS memakai stok efektif yang sama.
- Produk index juga diarahkan membaca stok cabang aktif jika pola query-nya cocok.
- Tidak mengubah transaksi lama.

Catatan:
Untuk multi cabang, stok cabang tetap dipakai. Sinkron hanya terjadi jika stok master produk memang lebih baru dari row stok cabang.
