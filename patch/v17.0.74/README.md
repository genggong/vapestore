# Patch v17.0.74 — QRIS CasaKu bayar-dulu + Tambah Member + Notif WA Referrer

## 1. QRIS CasaKu di POS: bayar dulu, invoice setelah
- Saat pilih QRIS CasaKu lalu **Bayar & Simpan**, kini muncul layar pembayaran QRIS **di paling atas**: 1 QR besar (membesar), status pembayaran, dan tombol **Cek Status Pembayaran** yang bisa diklik (auto-check tiap 6 detik).
- Invoice baru tampil final setelah pembayaran sukses.
- QR dibuat **idempotent** (tidak ganda walau submit/retry 2x), dan baris status "Cek Status" yatim yang tidak berfungsi dihapus.

## 2. Menu "Member Cepat" → "Tambah Member"
- Label diubah jadi **Tambah Member**, styling modal diperbaiki: tombol **Batal** (abu) dan **Simpan & Pilih** (ungu) jadi tombol rapi & jelas.

## 3. Notif WhatsApp ke referrer (via Fonnte)
- Saat pembayaran QRIS CasaKu sukses, referrer otomatis menerima poin referral (diberikan saat itu juga) dan dapat notif WA:
  *"Halo makasih ya [nama] … poin kamu bertambah [X] poin. Sekarang total poin kamu [total]. Yuk ajakin temen kamu yang lain!"*
- Notif hanya dikirim sekali (saat referral baru dibuat); kegagalan WA tidak menggagalkan transaksi.

```bash
cd ~/public_html
wget -qO p17074.sh https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.74/apply.sh
bash p17074.sh
```

Setelah selesai: jalankan **Upgrade Otomatis** lalu **Purge LiteSpeed cache**.
