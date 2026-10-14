VAPEKONG - FIX KEUANGAN OMZET + FILTER PERIODE V5
===================================================

Perubahan:
1. Total Pemasukan di menu Keuangan sekarang mengambil omzet penjualan POS
   dari mesin Akuntansi yang sama dengan menu Akuntansi.
2. Rumus Total Pemasukan:
   Penjualan Bersih POS (omzet setelah retur) + Pemasukan Lain.
3. Total Pengeluaran dan daftar Transaksi Keuangan mengikuti periode yang dipilih.
4. Filter cepat baru:
   - Daily / Hari Ini
   - 4 Hari
   - Weekly / 7 Hari
   - Month / Bulan Ini
   - Tanggal kustom (Dari - Sampai)
5. Saldo Bersih dihitung ulang sesuai periode:
   Total Pemasukan - Total Pengeluaran.
6. Hutang/Piutang tetap saldo berjalan saat ini dan diberi keterangan agar tidak
   disalahartikan sebagai angka historis periode.
7. Kartu Total Pemasukan menampilkan breakdown Omzet POS + Pemasukan Lain.
8. UI filter dibuat responsif untuk desktop dan HP.

File utama yang diubah:
- public/admin/finance/index.php
- public/assets/css/finance.css

Salinan pada folder pos/ juga disinkronkan:
- pos/public/admin/finance/index.php
- pos/public/assets/css/finance.css

Catatan:
- Tidak ada perubahan struktur database.
- Tidak perlu import SQL.
- Tidak mengubah perhitungan pada menu Akuntansi.
- Default periode pada halaman Keuangan adalah Bulan Ini.
