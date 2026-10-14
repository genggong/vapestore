# Cara Pasang Patch v17.0.4

1. Backup file website dan database terlebih dahulu.
2. Upload ZIP patch v17.0.4 ke document root yang sama dengan `index.php`, `app`, `system`, `legacy`.
3. Extract dan pilih overwrite/replace.
4. Buka `/admin/system-upgrade/index.php`.
5. Tekan **Jalankan Upgrade Otomatis**. Proses ini juga mencoba menyalin gambar yang masih berada di folder upload lama ke `/uploads` utama tanpa menghapus file asal.
6. Jika gambar lama masih belum tampil, tekan **Pulihkan / Migrasikan Gambar Lama** dan lihat bagian **Audit File Upload vs Database**.
7. Tes: Menu Produk, Edit Produk, upload foto baru, detail Marketplace, lalu download Template Stock Opname terbaru.

Template Stock Opname visible harus berurutan:
`name - variant - category - unit - system_stock - physical_stock - purchase_price - total_purchase_price - note`

Kolom teknis `product_id` dan `variant_id` tetap ada tetapi tersembunyi. Jangan hapus kolom tersembunyi tersebut saat file akan di-upload kembali.
