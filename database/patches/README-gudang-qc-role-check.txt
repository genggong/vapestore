Gudang + Quality Control Checklist

Fitur baru:
- Menu Cek Gudang / QC
- Role baru:
  - operator_gudang
  - admin_gudang
  - quality_control
- Operator gudang checklist barang dari data Stock Opname yang sudah final.
- Admin gudang verifikasi checklist operator.
- Quality Control approve/reject hasil cek.
- Hak akses dibedakan per role.
- Fitur ini hanya checklist/verifikasi data, tidak mengubah stok, transaksi, atau hasil Stock Opname.

File baru:
- public/admin/warehouse-checks/index.php
- public/admin/warehouse-checks/detail.php
- public/admin/warehouse-checks/action.php
- public/admin/warehouse-checks/_helpers.php
- public/assets/css/warehouse-qc.css
- database/patches/patch-gudang-qc-role-check.sql

Wajib jalankan patch:
database/patches/patch-gudang-qc-role-check.sql
