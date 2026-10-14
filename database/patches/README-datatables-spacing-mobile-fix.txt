Fix jarak kosong tabel/DataTables di mobile

Masalah:
Beberapa halaman tabel memiliki jarak kosong besar antara filter/card dan tabel,
terutama di HP. Penyebabnya layout controls DataTables/row yang tidak cocok
dengan tema mobile.

Perbaikan:
- Tambah public/assets/css/datatables-spacing-fix.css
- Load global dari admin-header.php
- Compact DataTables length/search di mobile
- Reset margin wrapper, row pertama, table-responsive, premium-table
