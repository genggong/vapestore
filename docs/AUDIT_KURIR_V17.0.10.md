# Audit Portal Kurir - Vapekong POS CI4 v17.0.10

## Akar masalah CSS
Pada bridge CI4, route `/kurir/login` menjalankan file legacy `courier/login.php` dengan `SCRIPT_NAME=/courier/login.php`. Helper `legacy_base_url()` versi sebelumnya hanya mengenali marker `/public/`, `/store/`, dan `/admin/`. Akibatnya URL asset kurir dibentuk menjadi `/courier/assets/...` padahal asset berada di `/assets/...`. Browser menerima 404 CSS dan halaman tampil seperti HTML polos.

## Perbaikan v17.0.10
- Helper base URL mengenali `/courier/` dan tetap aman jika aplikasi dipasang di subfolder.
- Header/footer portal kurir menggunakan asset root dengan cache busting `filemtime()`.
- Ditambahkan fallback CSS minimum supaya login tetap usable jika asset utama gagal termuat.
- Login kurir diperhalus untuk mobile, CSRF tetap aktif, throttle login sederhana, tombol lihat password, dan loading state.
- Route bersih ditambahkan untuk `/kurir/aksi` dan `/kurir/riwayat`.
- Flow paket tetap: assigned -> accepted -> picked_up -> on_the_way -> delivered/failed/returned.
- Bukti pengantaran dikompres otomatis melalui helper optimized image dan disimpan ke upload canonical.
- Lokasi kurir bersifat manual/opsional; hanya disimpan ketika kurir menekan tombol berbagi lokasi.
- Admin Pengiriman Kurir Toko sekarang dapat melihat lokasi terakhir bila tersedia.
- System Upgrade mendapat Audit Portal Kurir termasuk file CSS/JS, route, tabel/index, orphan assignment, status invalid, dan writable upload folder.

## Hasil audit statis
- PHP lint: 968 file, 0 syntax error.
- JS courier-portal.js: syntax OK.
- Halaman PHP yang dipindai navigator: 255.
- Target URL lokal unik: 307, target hilang: 0.
- Menu Admin: 54/54 valid.
- Referensi require/include sederhana berbasis `__DIR__`: 893, hilang: 0.
- Asset mirror `/assets` vs `/legacy/public/assets`: 91 file sama, 0 berbeda.
- Bridge CI4: catch-all URI, multi-segment, SCRIPT_NAME, base URL courier, rewrite dan media endpoint semuanya OK.

## Tes setelah upload patch
1. Buka `/kurir/login` menggunakan Incognito.
2. Login dengan akun kurir aktif.
3. Pastikan daftar tugas hanya berisi paket yang ditugaskan ke akun tersebut.
4. Buka detail paket dan uji Terima -> Ambil -> Dalam Perjalanan.
5. Uji Bagikan Lokasi (opsional).
6. Uji Selesai dengan nama penerima + foto bukti.
7. Periksa Admin -> Pengiriman Kurir Toko, status/timeline/lokasi terakhir harus ikut berubah.
