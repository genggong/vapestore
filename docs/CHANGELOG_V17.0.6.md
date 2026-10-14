# Vapekong POS CI4 v17.0.6 — System Upgrade Scanner Fix

## Temuan audit

Pada v17.0.5 banner merah `Ditemukan X item yang perlu perhatian` memakai satu total gabungan untuk:

- schema database (tabel/kolom/index),
- integritas checkout,
- routing/bridge/menu,
- asset inti,
- media/gambar lama yang hilang.

Akibatnya upgrade database dapat selesai dan schema sudah sehat, tetapi banner merah tetap muncul hanya karena satu gambar/media lama tidak ditemukan. Media tersebut tidak dapat diperbaiki oleh `CREATE/ALTER/INDEX`, sehingga pesan `Tekan Jalankan Upgrade Otomatis` menyesatkan.

Audit juga menemukan dua potensi hitung ganda:

1. path media yang sama dapat muncul di `media` dan `mediaStorage`;
2. target menu yang sama dapat muncul di `missing_targets` sekaligus `menu_bad`.

Selain itu pesan sukses v17.0.5 selalu ditampilkan setelah tombol upgrade selesai dipanggil, walaupun hasil scan ulang masih menunjukkan masalah database.

## Revisi v17.0.6

- Pisahkan **masalah inti** dari **peringatan media**.
- Database/checkout, routing/menu, asset inti, dan media memiliki counter sendiri.
- Peringatan media tidak lagi membuat upgrade database terlihat gagal.
- Path media dideduplikasi sebelum dihitung.
- URL menu/navigasi dideduplikasi sebelum dihitung.
- Setelah POST upgrade, status database dipindai ulang sebelum menentukan pesan akhir.
- Jika schema/index/checkout sudah sehat, tampil pesan sukses database yang eksplisit.
- Jika masih ada masalah database, pesan hasil upgrade berubah menjadi error dan menunjukkan jumlah yang tersisa.
- Jika hanya media lama yang hilang, tampil banner kuning: **Database & fitur inti sudah sehat** dan pengguna diarahkan ke Audit File Upload vs Database.
- Tambah ringkasan 5 kartu: DB/Schema, Checkout, Routing/Menu, Asset Inti, Peringatan Media.

## Keamanan

Patch ini tidak melakukan DROP/TRUNCATE dan tidak mengubah data bisnis. Perubahan hanya pada scanner/status halaman System Upgrade. Fungsi upgrade database v17.0.5 tetap dipertahankan.
