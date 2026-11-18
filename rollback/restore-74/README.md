# RESTORE ke v17.0.74 — dan CHECKLIST bangun ulang

## Cara restore
```bash
cd ~/public_html
wget -qO r74.sh https://raw.githubusercontent.com/genggong/vapestore/main/rollback/restore-74/apply.sh && bash r74.sh
```
Lalu: **Upgrade Otomatis (Admin → Database & System Doctor)** + **Purge LiteSpeed** + Ctrl+F5.
State sebelum restore otomatis dibackup ke `~/backup_pre_restore74_<TS>.tar.gz`.

## Yang dikembalikan ke v17.0.74 (23 file)
- app/Modules/Marketplace/Marketplace.php (3 tree) — v17.0.76 pakai ini utk WA referral / QRIS JSON / kurir toko
- assets/css/storefront.css (3 tree) — v17.0.75
- public/admin/casaku/status.php, customers/index.php, settings/index.php (3 tree) — v17.0.76
- store/api/resolve-destination.php (3 tree) — v17.0.78 (relaksasi guard dibatalkan)
- legacy/public/store/_header.php
- (checkout.php & marketplace-checkout.js sudah kembali ke 74 lewat v17.0.79)

## CHECKLIST bangun ulang (fitur yang dirollback, alur aslinya)
Berikut indeks preferensi user & fitur dari riwayat — untuk diterapkan ulang SEDIKIT DEMI SEDIKIT + uji E2E tiap langkah (jangan sekaligus):
1. **v17.0.75 — Autocomplete tunggal alamat checkout** (Tom Select, debounce 400ms, min 3 char, endpoint RajaOngkir). ⚠️ Pernah gagal live: notif berulang → Kurir Toko hilang → gagal petakan. Jika diulang: cek dulu mapping RajaOngkir dgn `marketplace_rajaongkir_resolve_direct_destination` + tarif Kurir Toko (id/nama konsisten) SEBELUM live.
2. **v17.0.76 — Kurir Toko restore**, hapus pengaturan **Pakasir**, **teks WA referral (fonnte)** ke referrer, **kolom data referral di menu Pelanggan**, debug QRIS Casaku (JSON valid).
3. **v17.0.77 — perbaiki notif "Pilih wilayah sampai Kelurahan/Desa."** (bagian dari autocomplete).
4. **v17.0.78 — Kurir Toko via always-resolve** (id cocok tarif).

Catatan kunci user (memori): uji kirim jgn di grup (malu2in) — kirim ke channel/DM sendiri; cek akar masalah → reproduksi → fix → uji E2E sebelum suggest deploy.

BRAIN DUMP UTK NANTI (fitur "TODO" yg belum sempat / menunggu user saat autocomplete live gagal):
- Definisi sukses autocomplete = Kurir Toko MUNCUL utk area tarif (Mejasem Barat/Kramat/Tegal, Panggung/Tegal Timur/Tegal).
- `marketplace_rajaongkir_search` (domestic-destination) vs `region-options` (sub-district) — beda id-space → tarif Kr Toko cocok via `marketplace_v16_store_courier_rate` (id dulu, lalu nama dgn `marketplace_v16_norm` yg TIDAK buang prefiks Kota/Kec).
