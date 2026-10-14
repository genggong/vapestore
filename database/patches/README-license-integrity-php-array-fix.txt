Fix Integrity PHP Array

Masalah:
File app/Licenses/.license-integrity.php sebelumnya memakai format manifest yang tidak valid untuk PHP, sehingga muncul parse error unexpected token "{".

Perbaikan:
- Manifest integrity dibuat ulang sebagai array PHP valid:
  ['file' => 'sha256']
- Integrity file tetap disamarkan/base64.
- Tidak mengubah logic transaksi, stok, retur, piutang, laporan, atau data bisnis.
