# Audit Vapekong POS CI4 v17.0.4

## Fokus upgrade
- Template Stock Opname disusun ulang sesuai permintaan: `name - variant - category - unit - system_stock - physical_stock - purchase_price - total_purchase_price - note`.
- Brand dihapus dari kolom visible template Stock Opname.
- `product_id` dan `variant_id` dipertahankan sebagai kolom tersembunyi agar import tidak salah mencocokkan nama produk yang mirip.
- Penyimpanan gambar produk baru diarahkan ke `/uploads/products` root aplikasi.
- Ditambahkan kompatibilitas gambar lama dari `/legacy/public/uploads`, `/public/uploads`, `/pos/public/uploads`, dan `/pos/uploads`.
- Storefront dan halaman admin Produk menggunakan resolver path upload baru.
- System Upgrade/Database Doctor dapat memindai serta menyalin gambar lama ke lokasi canonical tanpa menghapus file sumber.

## Audit statis
- PHP lint seluruh source `app`, `legacy`, `public`, `tools`: tidak ditemukan syntax error.
- 255 halaman PHP publik/admin dipindai.
- 305 target `legacy_base_url()` statis terdeteksi; clean/dynamic route tetap dipertahankan oleh bridge CI4.
- 54 menu sidebar Admin diperiksa dan seluruh target file tersedia.

## Catatan media
Database menyimpan path relatif `uploads/...`, sementara pada versi lama file dapat berada di beberapa folder fisik berbeda. v17.0.4 tidak mengubah path database secara massal. Resolver memilih file yang benar berdasarkan file yang benar-benar ada di disk. Tombol **Pulihkan / Migrasikan Gambar Lama** menyalin file yang ditemukan ke `/uploads` utama, tanpa delete file asal.
