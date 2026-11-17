# Patch v17.0.69 — Referral poin POS

## Perubahan
- POS menyediakan input **Nomor HP Pengajak** untuk Customer Baru/member.
- Nomor pengajak dinormalisasi (08... dan 62... dianggap sama) dan wajib cocok dengan member aktif.
- Customer Baru memperoleh poin reguler sesuai aturan poin yang sudah ada.
- Pengajak memperoleh **50% dari poin reguler Customer Baru** pada transaksi sukses.
- Referral berlaku **hanya transaksi pertama** Customer Baru; relasi dicatat di `customer_referrals` dengan unique guard.
- Transaksi gagal/pending tidak memberi poin referral. Pencatatan poin idempoten.

## Instalasi
```bash
cd ~/public_html
wget -qO p17069.sh https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.69/apply.sh
bash p17069.sh
```

Patch membuat backup enam file POS/status + VERSION sebelum overwrite. Setelah apply, jalankan Upgrade Otomatis dan purge LiteSpeed cache.
