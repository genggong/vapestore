# Vapekong POS CI4 v17.0.4

## Stock Opname Excel
- Urutan kolom visible disederhanakan menjadi: `name`, `variant`, `category`, `unit`, `system_stock`, `physical_stock`, `purchase_price`, `total_purchase_price`, `note`.
- Kolom `brand` dihapus dari template Stock Opname.
- `physical_stock` sekarang tepat di sebelah `system_stock` agar input fisik tidak perlu scroll jauh.
- `product_id` dan `variant_id` tetap dipertahankan sebagai kolom teknis J/K tetapi disembunyikan di Excel agar import tetap akurat.
- Produk/varian dengan stok 0 tetap tidak dimasukkan.

## Perbaikan Gambar Produk
- Upload produk baru sekarang menggunakan folder canonical `/uploads/products` di root aplikasi agar URL langsung dapat diakses shared hosting.
- Upload gambar varian Marketplace menggunakan `/uploads/storefront/products` canonical.
- Ditambahkan `media.php` compatibility endpoint untuk gambar lama yang masih berada di `/legacy/public/uploads`, `/public/uploads`, `/pos/public/uploads`, atau `/pos/uploads`.
- `legacy_base_url()` sekarang mendeteksi lokasi file upload lama dan menghasilkan URL fallback yang benar.
- Storefront image checker sekarang memeriksa seluruh lokasi upload lama, bukan hanya `legacy/public`.
- Database/System Doctor dapat mendeteksi gambar yang masih ada di folder lama dan memigrasikannya ke `/uploads` utama tanpa menghapus file asal.

## Keamanan & kompatibilitas
- File upload canonical dilindungi `.htaccess` agar PHP/script tidak dapat dieksekusi dari folder upload.
- Validasi upload produk memakai `getimagesize()` dan hanya menerima JPG/PNG/WEBP valid.
- Permission file upload baru diusahakan 0644 dan folder 0775/755 sesuai hosting.
