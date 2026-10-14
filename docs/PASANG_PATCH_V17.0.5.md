# Cara Pasang PATCH v17.0.5

1. Backup database dan folder website terlebih dahulu.
2. Upload `Vapekong_POS_PATCH_v17.0.5_PURCHASE_CHECKOUT_MEDIA.zip` ke document root yang berisi `index.php`, `app`, `system`, dan `legacy`.
3. Extract dengan **Overwrite / Replace**.
4. Buka `/admin/system-upgrade/index.php`.
5. Tekan **Jalankan Upgrade Otomatis**.
6. Pastikan bagian **Integritas Checkout Customer** semuanya hijau/OK.
7. Untuk media: jalankan **Pulihkan / Migrasikan Gambar Lama**, lalu **Bersihkan Duplikat Media Lama**, lalu **Kompres Gambar Upload**. Tombol kompres boleh ditekan berulang karena diproses per batch.
8. Tes Pembelian: cari nama produk induk, pilih varian, tambah item kedua, simpan.
9. Tes Checkout customer sampai redirect ke invoice/pembayaran.

Catatan: kompresi otomatis terbaik memerlukan extension PHP `gd`. Tanpa GD, upload tetap disimpan tetapi tidak di-resize/kompres oleh PHP.
