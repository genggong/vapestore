Fix pembayaran piutang QRIS tidak ada respon

Perbaikan:
- debt_record_payment() dan debt_create_qris_payment() tidak lagi memanggil debt_install() di tengah proses pembayaran.
- QRIS piutang tidak dibungkus transaksi database saat request ke Pakasir.
- Halaman bayar piutang punya loading saat submit.
- Tombol submit sticky di atas bottom nav agar tidak ketutup di HP.
