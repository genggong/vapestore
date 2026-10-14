UPGRADE POS - Member Search, Shift Location, Manual QRIS

1. Member POS
- Selector member lama diganti pencarian responsif.
- Bisa cari nama, kode member, nomor HP, termasuk 4 digit belakang nomor HP.
- Hasil dibatasi 12 item agar nyaman pada 50-100+ member.

2. Shift Kasir
- cashier_shifts otomatis ditambah branch_id dan location_name saat halaman shift/POS dibuka.
- Buka shift sekarang memilih cabang/lokasi dan bisa isi posisi/counter kasir.
- Ringkasan shift difilter berdasarkan cabang shift.

3. QRIS Manual
- Pengaturan > QRIS Manual: aktif/nonaktif, nama, upload JPG/PNG/WEBP maksimum 5MB.
- POS menampilkan gambar QRIS sendiri.
- Kasir wajib mencentang konfirmasi pembayaran sebelum transaksi disimpan sukses.
- Transaksi disimpan dengan payment_method=qris_manual dan masuk ringkasan QRIS dashboard.

Tidak perlu menjalankan SQL manual untuk upgrade tabel shift karena helper melakukan ALTER COLUMN otomatis. Patch cashier shift juga sudah diperbarui untuk instalasi baru.
