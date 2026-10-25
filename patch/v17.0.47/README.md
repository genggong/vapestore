# Patch v17.0.47 — fix edit tarif kurir + laba bersih minus ongkir + kartu Biaya Operasional

## Isi (11 berkas)

### 1. Fix tombol Edit tarif Kurir Toko
Tombol **Edit** pada daftar tarif kurir toko sebelumnya tidak berfungsi ("gabisa klik"). Dua masalah JS:
- `fillEdit`/`cancelEdit` didefinisikan di dalam IIFE sehingga bukan global, padahal `onclick="fillEdit(this)"` memanggilnya global → *"fillEdit is not defined"*. Sekarang diekspos ke `window`.
- Di dalam `fillEdit`, variabel `d` dipakai sebagai data JSON dan menimpa elemen `<select id=rateDistrict>`; `setOne(d,...)` menerima objek data (bukan elemen) → *"el.querySelector is not a function"*. Variabel dipecah (`data`, `p2/c2/d2/s2`).

Berlaku untuk 3 pohon: `admin/marketplace-courier-rates/index.php` (legacy/public, public, pos/public).

### 2. Laba bersih dikurangi ongkir
`Accounting.php` (3 pohon): `net_profit` diubah dari
`gross + shipping + other - operating`
menjadi
`gross + other - operating - shipping`.
Ongkir kini diperlakukan sebagai **beban/biaya** (bukan pendapatan), sesuai keputusan. Berlaku di tingkat cabang, harian, dan ringkasan.

### 3. Kartu Biaya Operasional
`admin/accounting/index.php` (3 pohon): tambah KPI **Biaya Operasional** (diambil dari `summary['operating_expense']` = beban menu Keuangan), plus label Pendapatan Ongkir diperjelas "(dikurangi dari laba bersih)".

`VERSION` → 17.0.47; SystemUpgrade + halaman System Upgrade.

## Verifikasi
- Edit tarif: browser E2E → klik Edit → `rateId` terisi, banner "Mode Edit" tampil ✓ (sebelumnya error `fillEdit is not defined` & `el.querySelector is not a function`).
- Accounting lokal: gross 3.349.448,51 − operasional 438.000 − ongkir 6.000 = laba bersih **2.905.448,51** ✓ (ongkir tidak lagi ditambahkan).
- `php -l` 0 error semua berkas di 3 pohon; 11 cek kontrak lulus.

## Pasang (repo public, wget langsung)
```bash
cd ~/public_html
wget -qO apply_patch_v17_0_47.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_47.sh
bash apply_patch_v17_0_47.sh
```
Lalu **Purge All** (LiteSpeed) + Ctrl+F5.
