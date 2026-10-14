Maintenance Mode

Perubahan:
- Tambah setting maintenance_mode dan maintenance_message di menu Pengaturan.
- Jika maintenance aktif, halaman penting diarahkan ke public/maintenance.php.
- Super Admin dan Owner tetap bisa masuk dan membuka Pengaturan untuk menonaktifkan maintenance.
- Login, logout, assets, uploads, dan halaman maintenance tetap dibuka.
- Request AJAX/JSON mendapat response 503 JSON.
- Tidak mengubah logic transaksi, stok, laporan, retur, piutang, atau data bisnis.
Catatan: setting disimpan otomatis ke tabel settings saat klik Simpan Pengaturan.
