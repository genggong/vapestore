# Changelog v17.0.3

- Fix fatal routing untuk semua URL multi-segment setelah migrasi CI4.
- Catch-all CI4 sekarang membaca URI langsung, bukan `handle/$1`.
- Aktifkan `multipleSegmentsOneParam=true` sebagai compatibility guard.
- Emulasi `SCRIPT_NAME`, `PHP_SELF`, `SCRIPT_FILENAME`, `DOCUMENT_URI`, dan cwd untuk file legacy.
- Fix halaman detail storefront `/produk/{id}-{slug}`.
- Fix menu admin bertingkat `/admin/...`.
- Fix endpoint API marketplace `/store/api/...`.
- Fix konteks Tambah/Edit modul yang membaca `SCRIPT_NAME` (contoh Promo).
- Upgrade halaman System & Database menjadi v17.0.3 dengan audit routing/navigasi.
- Audit 54 menu admin dan 304 target URL lokal.
- Perluas pemeriksaan schema core POS, user, cabang, transaksi, finance, dan marketplace.
