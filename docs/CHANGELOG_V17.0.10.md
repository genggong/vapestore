# Vapekong POS CI4 v17.0.10 — Courier Portal Repair & Audit

## Akar masalah Portal Kurir tanpa CSS

CI4 legacy bridge meniru `SCRIPT_NAME=/courier/login.php` ketika route bersih `/kurir/login` dieksekusi. Helper `legacy_base_url()` sebelumnya hanya mengenali folder virtual `/public/`, `/store/`, dan `/admin/`. Karena `/courier/` tidak dikenali, base URL menjadi `/courier`, sehingga CSS/asset dipanggil sebagai `/courier/assets/...` dan 404.

v17.0.10 menambahkan `/courier/` sebagai marker root aplikasi. Hasilnya asset Portal Kurir kembali ke `/assets/...`, termasuk bila aplikasi dipasang pada subfolder.

## Upgrade Portal Kurir

- Fix CSS/JS dan semua URL Portal Kurir pada CI4 bridge.
- Cache-busting asset menggunakan `filemtime`, tidak lagi versi statis lama.
- Clean action route baru `/kurir/aksi`; route lama fisik tetap kompatibel.
- Login dibuat lebih rapi dan mobile friendly, show/hide password, submit loading, dan throttle ringan per sesi untuk percobaan login berulang.
- Tombol opsional **Bagikan Lokasi Saat Ini** pada detail paket. Lokasi hanya dikirim ketika kurir menekan tombol dan dapat dibuka admin melalui Maps.
- Bukti foto pengantaran sekarang memakai helper kompresi yang sama dengan media produk: resize maksimal 1600px dan WebP bila GD mendukung.
- Preview foto bukti sebelum status Terkirim disimpan.
- Prevent double-submit pada form Portal Kurir.
- Admin Monitor Paket menampilkan lokasi terakhir kurir bila tersedia.
- Media scanner kini juga menghitung `marketplace_courier_assignments.delivery_proof`, bukan hanya event proof.

## Database/System Doctor

- Tambah schema audit untuk `marketplace_couriers`, `marketplace_courier_assignments`, dan `marketplace_courier_events`.
- Tambah audit index kurir.
- Tambah **Audit Portal Kurir** untuk file CSS/JS, routing, kompresi bukti, tabel, orphan assignment, status assignment, akun aktif, dan permission folder upload.
- Upgrade Otomatis tetap additive: CREATE/ALTER/INDEX, tanpa DROP/TRUNCATE data bisnis.

## Pemasangan

Untuk instalasi yang sudah v17.0.9, cukup upload PATCH v17.0.10 ke root website dan overwrite file yang sama. Setelah itu buka `/admin/system-upgrade/index.php`. Upgrade Database hanya perlu dijalankan bila bagian Audit Portal Kurir/Schema menunjukkan kolom atau index database yang kurang.
