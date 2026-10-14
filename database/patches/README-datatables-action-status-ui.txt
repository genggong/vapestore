DataTables Action + Status UI

Perubahan tampilan saja:
- Tambah public/assets/css/datatables-actions-status.css
- Tombol aksi di kolom terakhir tabel/DataTables dirapikan.
- Aksi detail/view/edit/print/delete punya warna berbeda.
- Badge status memakai warna sesuai status:
  sukses/lunas/aktif = hijau,
  pending/draft/sebagian = kuning,
  gagal/batal/expired/overdue = merah,
  qris/transfer/info = biru,
  cash/netral = abu.
- Badge status diberi animasi shimmer halus dan pulse untuk pending.
- Menghormati prefers-reduced-motion.
- Tidak mengubah query, transaksi, stok, retur, piutang, laporan, atau logic fitur.
