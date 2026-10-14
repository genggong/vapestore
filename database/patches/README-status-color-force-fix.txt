Status Color Force Fix

Masalah:
Beberapa badge status tetap abu karena CSS lama atau class status berbeda.

Perbaikan tampilan saja:
- Tambah CSS paling akhir: public/assets/css/status-color-force.css
- Tambah JS force scanner: public/assets/js/status-color-force.js
- Status dibaca dari teks badge/kolom status setelah DataTables render.
- Warna dipaksa inline:
  success/lunas/aktif/selesai = hijau
  pending/draft/belum/sebagian = kuning
  failed/batal/expired/overdue/refunded = merah
- Tidak mengubah logic, database, transaksi, stok, retur, piutang, laporan, atau perhitungan.
