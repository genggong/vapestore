Admin UI Rescue Fix

Masalah:
- Tampilan admin menjadi polos/link biru karena CSS layout admin tidak kebaca/ketabrak.

Perbaikan:
- Tambah CSS fallback: public/assets/css/admin-ui-rescue.css
- Tambah JS toggle sidebar: public/assets/js/admin-ui-rescue.js
- Include di admin-header/admin-footer.
- Tidak mengubah logic, menu, role, database, transaksi, stok, atau fitur Gudang/QC.
