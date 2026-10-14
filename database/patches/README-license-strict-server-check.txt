License Strict Server Check

Perbaikan bug:
- Cache lokal tidak lagi meloloskan akses.
- Setiap buka halaman penting wajib cek ke server lisensi.
- Jika license inactive/not_found/deleted/expired/domain mismatch/fingerprint mismatch, POS langsung terkunci.
- Jika license server di-reset dan domain/fingerprint kosong, POS wajib aktivasi ulang.
- License key/cache lama di database dibersihkan saat status invalid tertentu, supaya database yang ikut terjual tidak otomatis membawa akses lama.

Patch opsional:
- database/patches/patch-license-strict-clear-local-cache.sql
  Jalankan pada database master sebelum dijual/dibagikan agar license_key/cache lama kosong.

Catatan:
Pembeli tetap harus punya license key dari panel server license dan aktivasi sesuai domain/fingerprint.
