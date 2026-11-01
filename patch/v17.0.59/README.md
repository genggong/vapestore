# Patch v17.0.59 — Checkout fokus QRIS CasaKu (sembunyikan semua metode Pakasir / kartu VA)

Rilis di repo **vapestore**.

## Isi (7 berkas)
- `store/checkout.php` di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `Modules/Marketplace/CasaKuPayment.php` di 3 pohon: `app/`, `legacy/app/`, `pos/app/`
- `VERSION` → 17.0.59

## Perubahan
### 1. Checkout hanya tampilkan QRIS (CasaKu); kartu VA/Pakasir disembunyikan
Di halaman checkout, kartu **BRI Virtual Account**, **BNI Virtual Account**, dan
**Virtual Account Lain** sebelumnya selalu dirender (hardcode) tanpa mengecek gateway—
jadi meski paket CasaKu aktif, kartu VA tetap muncul.

Sekarang kartu VA tersebut dibungkus `<?php if (!$isCasaku): ?> ... <?php endif; ?>`.
Saat gateway `casaku` aktif, checkout hanya menampilkan **QRIS (CasaKu)** (plus COD
apabila diaktifkan di toko). Semua metode Pakasir/VA tersembunyi.

### 2. Default gateway payment diarahkan ke CasaKu
`marketplace_payment_gateway()` default dari `pakasir` → `casaku` (dan fallback invalid
juga `casaku`). Instalasi/fresh serta toko tanpa setting gateway eksplisit otomatis
berfokus CasaKu.

Catatan: jika setting tersimpan `marketplace_payment_gateway 'pakasir'` masih ada di
DB, hasil tetap pakasir. Pastikan di Pengaturan Marketplace gateway-nya **casaku**
(biasanya sudah, karena paket CasaKu aktif). Perubahan kode ini memastikan default = casaku.

## Cara pasang
```bash
cd ~/public_html
wget -qO p1759.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.59/apply.sh"
bash p1759.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (6 file + VERSION)
2. Unduh `PATCH_v17.0.59.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu.
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: buka halaman checkout → pastikan hanya QRIS (CasaKu) yang muncul, kartu VA hilang; lakukan checkout sampai QRIS tampil.
