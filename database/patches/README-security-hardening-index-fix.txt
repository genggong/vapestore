Security Hardening + Index Fix

Perbaikan:
- Tambah index.php di root project agar membuka 0.0.0.0:8080 tidak menampilkan "Index of".
- Tambah public/index.php sebagai entry redirect login/dashboard.
- Tambah .htaccess root dan public untuk mematikan directory listing.
- Lindungi folder app, database, storage dari akses web langsung.
- Uploads tidak boleh mengeksekusi PHP.
- Tambah security headers:
  X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- Session diperketat:
  use_strict_mode, use_only_cookies, httponly, SameSite=Lax.
- Guard sederhana untuk path traversal.
- Tidak mengubah logic transaksi, stok, retur, piutang, laporan, atau data bisnis.

Catatan:
Kalau pakai PHP built-in server dan membuka root project, index.php akan redirect ke public.
Kalau pakai Apache, .htaccess akan mematikan directory listing.
