# Patch v17.0.70 — Referral POS dropdown

Perubahan kumulatif dari v17.0.68:
- Referral POS memakai dropdown member aktif.
- Dropdown menampilkan nama dan nomor HP member pengajak.
- ID, nama, dan nomor HP pengajak divalidasi server.
- Referral tetap hanya berlaku pada transaksi pertama Customer Baru.
- Pengajak mendapat 50% poin reguler Customer Baru.
- Berlaku untuk tiga varian aplikasi dan status QRIS otomatis.

Instalasi:
```bash
cd ~/public_html
wget -qO p17070.sh https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.70/apply.sh
bash p17070.sh
```

Backup otomatis dibuat sebelum file ditimpa. Setelah apply, jalankan Upgrade Otomatis dan purge cache LiteSpeed.
