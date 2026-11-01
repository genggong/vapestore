# Patch v17.0.55 — paket QRIS CasaKu dibatasi ke BCA Mobile (`com.bca.msb`)

Rilis di repo **vapestore** (basis yang sudah ber-CasaKu sejak v17.0.50–17.0.54).

## Isi
- `CasaKuPayment.php` di 3 pohon: `app/` + `legacy/app/` + `pos/app/Modules/Marketplace/CasaKuPayment.php`
- `VERSION` → 17.0.55

## Perubahan
Pada `marketplace_casaku_create` (body POST `/api/generate/v2/qris`):
- `packageIds` (daftar aplikasi pembayar yang diprioritaskan QRIS) yang tadinya `[]` (default) diubah jadi `['com.bca.msb']` → QRIS diarahkan ke **BCA Mobile**.

Catatan: file webhook CasaKu tidak diubah — hanya payload package QRIS di source.

## Cara pasang
```bash
cd ~/public_html
wget -qO p1755.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.55/apply.sh"
bash p1755.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (3 CasaKuPayment.php + VERSION)
2. Unduh `PATCH_v17.0.55.zip`
3. `unzip -t` uji validitas
4. `unzip -o` overwrite (path root-relative benar)
5. Bersihkan zip

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Jalankan Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: buat QRIS CasaKu di checkout, pastikan QRIS muncul & tertuju ke BCA Mobile.
