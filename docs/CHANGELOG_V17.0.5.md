# Vapekong POS CI4 v17.0.5

Fokus rilis ini: Pembelian Produk Induk + Varian, checkout customer FK 1452, dan penghematan storage gambar upload.

## Pembelian Produk + Varian
- Memperbaiki bug baris "Tambah Item" yang sebelumnya membuat `<select name="product_id[]">` sementara server membaca `item_key[]`. Akibatnya item tambahan/varian tidak tersimpan.
- Hasil pencarian sekarang menampilkan hirarki Produk Induk -> Varian, bukan hanya nama varian + kode.
- Pencarian membaca nama produk induk, varian, kode, barcode, stok, dan unit.
- Produk tanpa varian tetap dapat dipilih sebagai produk induk.
- Produk yang punya varian mewajibkan memilih varian supaya stok tidak bercampur.
- Harga beli varian ikut diperbarui saat pembelian varian disimpan.

## Checkout Customer
- Memperbaiki error SQLSTATE[23000] / 1452 `fk_online_order_items_order`.
- `online_orders.id` sekarang diambil tepat setelah INSERT sebelum query lain dijalankan.
- Ada fallback lookup berdasarkan `order_number` dan verifikasi parent sebelum insert `online_order_items`.
- System Upgrade memeriksa AUTO_INCREMENT `online_orders` / `online_order_items`, foreign key, dan orphan item.

## Gambar & Storage Hosting
- Upload gambar produk, galeri marketplace, foto varian, logo marketplace, logo brand, dan banner memakai resize + kompresi server-side.
- Bila PHP GD + WebP tersedia, upload baru otomatis disimpan sebagai WebP (maks. 1600/1920 px sesuai jenis aset).
- Jika GD tidak tersedia, upload tetap berjalan dengan format asli.
- Saat foto utama produk diganti atau foto galeri dihapus, file fisik lama hanya dihapus bila sudah tidak direferensikan database.
- System Upgrade menambah tombol `Kompres Gambar Upload` (batch 60 file) tanpa mengubah path database.
- System Upgrade menambah `Bersihkan Duplikat Media Lama`; hanya file legacy/public/pos yang byte-nya identik dengan file canonical `/uploads` yang dihapus.

## Audit
- PHP lint: 338 file, 0 syntax error.
- Halaman PHP publik/admin: 255.
- Target URL lokal literal: 305, tidak ada target hilang.
- Sidebar Admin: 54/54 target tersedia.
- `require/include` berbasis `__DIR__`: 891, tidak ada dependency hilang.
