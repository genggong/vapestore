PATCH FIX PELANGGAN + JATUH TEMPO PIUTANG MEMBER

Perbaikan:
1. Memperbaiki error tambah pelanggan SQLSTATE[21S01] / 1136 karena jumlah kolom, placeholder, dan nilai INSERT tidak sama.
2. Memperbaiki proses edit pelanggan yang sebelumnya juga kurang mengirim nilai price_level.
3. Menambahkan pilihan Level Harga pelanggan dengan tampilan form yang tetap mengikuti tema lama.
4. Mengaktifkan bagian Jatuh Tempo pada metode Cash + Piutang di POS.
5. Menambahkan pilihan cepat jatuh tempo 7 hari, 14 hari, dan 1 bulan, serta tetap bisa memilih tanggal manual.
6. Menambahkan validasi server: transaksi piutang wajib memilih member, wajib memakai metode Cash + Piutang, dan wajib memiliki tanggal jatuh tempo yang valid.
7. Memperbaiki sinkronisasi Level Harga member saat member dipilih di POS.

Database:
- Tidak perlu import SQL tambahan.
- Kolom price_level dan debt_due_date sudah dipastikan otomatis oleh helper bawaan saat halaman dibuka.

Cara pasang:
- Ekstrak ZIP patch ke root project lalu timpa file yang sama.
- ZIP ini hanya berisi file baru atau file yang diperbarui.
