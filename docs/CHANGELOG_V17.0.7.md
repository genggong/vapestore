# Vapekong POS CI4 v17.0.7 — POS Product Identity & Purchase Picker Sync

## Perubahan utama
- Kartu/list produk POS sekarang menampilkan Nama Produk Induk, Varian, Barcode, Kode/SKU, kategori, stok dan harga secara terpisah.
- Pencarian POS tetap mencakup nama produk, nama varian, kode/SKU dan barcode.
- Picker Pembelian sekarang menampilkan Produk Induk + Varian + Barcode + Kode + Stok agar pemilihan manual tidak membingungkan.
- Bug sisa baris Tambah Item Pembelian diperbaiki: field dinamis kembali memakai `item_key[]`, sama dengan backend.
- Root `/assets/js/purchases.js` dan `/assets/css/purchases.css` disinkronkan dengan copy legacy terbaru. Root assets diprioritaskan Apache, jadi sinkronisasi ini penting agar fix benar-benar aktif.
- Tidak ada perubahan/destructive migration database pada versi ini.

## Target hasil
Contoh item bervarian di POS:
- Nama: Pod Foom X Switch Prime Bundling
- Varian: White GT
- Barcode: 899...
- Kode: SKU/PRD...
- Kategori / stok
- Harga
