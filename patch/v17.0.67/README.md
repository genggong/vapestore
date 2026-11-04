# Patch v17.0.67 — COD dari pesanan marketplace digabung ke kas/Tunai (dashboard & akuntansi)

Transaksi COD marketplace yang selama ini masuk akuntansi sebagai metode **"COD"** terpisah,
sekarang **digabung ke Tunai (cash)** — konsisten di dashboard dan akuntansi.

## Perubahan
1. **Dashboard** (`*/admin/dashboard.php`): kartu **"Tunai Hari Ini"** kini ikut menghitung
   `payment_method = 'cod'` (COD = uang tunai diterima di tempat pesanan marketplace) — sebelumnya
   hanya `cash` & `cash_debt`, sehingga COD tidak masuk kartu Tunai.
2. **Akuntansi** (`*/Modules/Accounting/Accounting.php`): label metode
   `'cod' => 'Tunai'` — rincian pembayaran per metode yang tadinya menampilkan "Cod" kini
   menyatu dengan "Tunai". (Angka kas total di akuntansi sudah otomatis mencakup COD via
   `paid_amount`; perubahan ini menggabungkan tampilan labelnya.)

Catatan: Omset (turnover) dashboard sudah mencakup COD sebelumnya; hanya kartu **Tunai** yang
belum. Kini sejalan.

## Isi (7 berkas)
- `public/admin/dashboard.php` · `legacy/public/admin/dashboard.php` · `pos/public/admin/dashboard.php`
- `app/Modules/Accounting/Accounting.php` · `legacy/app/Modules/Accounting/Accounting.php` · `pos/app/Modules/Accounting/Accounting.php`
- `VERSION` → 17.0.67

## Cara pasang
```bash
cd ~/public_html
wget -qO p17067.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.67/apply.sh"
bash p17067.sh
```
### apply.sh — yang dikerjakan
1. Backup → `~/backup_code_<ts>.tar.gz` (7 file + VERSION)
2. Unduh `PATCH_v17.0.67.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — WAJIB
1. `/admin/system-upgrade/index.php` → **Upgrade Otomatis**
2. **Purge LiteSpeed Cache**
3. Tes: buka `/admin/dashboard.php` → "Tunai Hari Ini" sekarang termasuk COD; buka halaman Akuntansi → metode COD tampil sebagai "Tunai" (bukan "Cod").
