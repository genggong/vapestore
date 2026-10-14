# Cara Pasang Patch v17.0.2

1. Backup database lewat phpMyAdmin dan ZIP website aktif.
2. Upload `Vapekong_POS_PATCH_v17.0.2_ROUTER_DB_DOCTOR.zip` ke document root domain yang berisi `index.php`, `app`, `system`, `legacy`.
3. Extract dan pilih overwrite/replace.
4. Buka `/adm`. v17.0.2 akan mengarah ke dashboard admin/login, bukan marketplace.
5. Login admin lalu buka **Sistem > Upgrade Sistem & Database**.
6. Tekan **Jalankan Upgrade Otomatis** sekali.
7. Pastikan Tabel Kurang = 0, Kolom Kurang = 0, Index Kurang = 0. Jika hosting timeout, halaman upgrade aman dijalankan ulang.
8. Jika bagian Media Marketplace menunjukkan FILE HILANG, baru gunakan tombol **Reset Path Media Hilang**. Tombol ini hanya mereset setting visual yang file fisiknya memang tidak ada.
9. Tes POS 1 transaksi, Pembelian dengan varian, Request Stock varian, download Stock Opname, Marketplace, login admin, dan checkout.

Jangan import ulang snapshot `vapekong_pos.sql` ke database produksi. Gunakan database aktif dan halaman upgrader.
