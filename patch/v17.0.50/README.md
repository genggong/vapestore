# Patch v17.0.50 — Payment Gateway CasaKu (QRIS) switchable

Ganti payment gateway checkout marketplace dari **Pakasir** ke **CasaKu (QRIS otomatis)**. Karena kode dipakai bersama vapekongstore.com (utama) dan sub.vapekongstore.com (backup), integrasi dibuat **switchable** lewat pengaturan — CasaKu diaktifkan di sub, Pakasir tetap jalan di utama.

## Cara kerja CasaKu
- QRIS statis → dinamis; nominal unik per transaksi.
- Pembayaran dideteksi **aplikasi listener di HP Android** dan diverifikasi real-time.
- API base `https://api.casaku.id`, auth header `x-license-key`.
- Verifikasi webhook lunas via **HMAC-SHA256** (`X-Casaku-Signature`).

## Isi (24 berkas, 3 pohon)

### Modul baru `Modules/Marketplace/CasaKuPayment.php`
- `marketplace_payment_gateway()` — baca setting `marketplace_payment_gateway` (`pakasir`|`casaku`).
- `marketplace_payment_methods()` — Casaku → hanya `qris`.
- `marketplace_payment_create()` / `marketplace_payment_sync_status()` — dispatcher gateway aktif.
- `marketplace_casaku_config()`, `marketplace_casaku_create()` (generate QRIS v2), `marketplace_casaku_sync_status()` (check-status), `marketplace_casaku_verify_signature()` (HMAC).
- Base URL bisa dioverride via setting `marketplace_casaku_base_url` (untuk sandbox/staging).

### Dispatcher di Marketplace.php
- `marketplace_pakasir_create(...)` → `marketplace_payment_create(...)` di create_order & retry.
- `marketplace_pakasir_sync_status(...)` → `marketplace_payment_sync_status(...)` di polling order.
- Auto `require` modul CasaKu tiap request marketplace.

### Webhook baru `marketplace-casaku-webhook.php`
- Verifikasi header `X-Casaku-Signature` dengan HMAC-SHA256 raw body vs Webhook Secret (constant-time).
- Body: `{transactionId, amount, status, paidAt}` → cari pesanan via `payment_txn_id` → mark paid (pakai `marketplace_apply_payment_webhook`).
- 403 bila signature salah / secret kosong; 200 dalam 10 detik.

### Pengaturan Marketplace
- Kartu **"CasaKu (QRIS Otomatis)"**: pilih Gateway (`pakasir`/`casaku`), License Key, Webhook Secret, Masa Berlaku QRIS (menit), dan **Webhook URL** (salinan).
- Checkout hanya mengeluarkan metode **QRIS** bila gateway = casaku.

### Checkout & API
- `store/checkout.php` menampilkan metode dari gateway aktif.
- `store/api/payment-status.php` memakai dispatcher sinkron.

## Verifikasi (mock API lokal — tidak nyentuh server/live)
- `marketplace_payment_gateway()` = `casaku`; methods = hanya `qris` ✓
- `marketplace_payment_create()` → txn_id + qr_string + payment_url tersimpan, order `pending` ✓
- Webhook HMAC valid → `payment_status=paid` ✓
- Webhook signature salah → **403** ✓
- `php -l` 0 error (24 berkas, 3 pohon); semua cek kontrak lulus.

## Cara pasang (sub.vapekongstore.com)
```bash
cd ~/public_html
wget -qO apply_patch_v17_0_50.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_50.sh
bash apply_patch_v17_0_50.sh
```
Lalu di **Pengaturan Marketplace**:
1. Gateway = **CasaKu (QRIS)**.
2. Isi **License Key CasaKu** (dari casaku.id/api-keys).
3. Isi **Webhook Secret** lalu salin **Webhook URL** → tempel di dashboard CasaKu (callback lunas).
4. Pastikan mode license: bila "Wajibkan Aplikasi Aktif" aktif, app listener harus online agar generate QRIS berhasil.
5. **Purge All** (LiteSpeed) → Ctrl+F5.

> Catatan: Pakasir tetap jadi gateway default. Untuk hanya mengubah sub, jangan sentuh gateway di vapekongstore.com utama.
