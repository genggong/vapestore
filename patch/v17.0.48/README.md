# Patch v17.0.48 — Menu Input Operasional untuk Kasir

Menu baru **Input Operasional** di sidebar **Operasional**, tepat di bawah **Request Stock**. Khusus memungkinkan **kasir** mencatat beban operasional **tanpa bisa membuka menu Keuangan**. Input otomatis menjadi **beban operasional** di Keuangan & Akuntansi — data keuangan yang sudah ada **tetap, tidak hilang**.

## Isi (38 berkas)

### Halaman baru `admin/operasional/index.php` (3 pohon)
- Form catat beban: jenis (Listrik, Air/Galon, Gaji, Internet, Sewa, Perlengkapan, Kebersihan, Transportasi, Lainnya), nominal, keterangan, tanggal.
- Guard `Auth::guard_finance_operational()` — kasir (punya `finance.operational`) maupun admin (punya `finance.manage`/`finance.view`) boleh masuk.
- Simpan ke `finance_transactions` type=`expense` + kategori **Operasional** (otomatis dibuat bila belum ada) + `reference_type='operational'`.
- Jadi otomatis masuk **Keuangan** (riwayat pengeluaran) dan **Akuntansi** (Biaya Operasional = beban laba bersih). Tidak menambah HPP/kas keluar pembelian.
- Riwayat beban bulan berjalan + total.

### Guard menu Keuangan (3 pohon × 8 file)
Semua halaman `admin/finance/*` kini `Auth::requirePermission('finance.view')`. Kasir **tanpa** `finance.view` tidak bisa membuka Keuangan (dan Akuntansi sudah di-guard `finance.view` sejak awal) — tidak bisa lewat URL langsung sekalipun.

### Permission + Role (`admin/roles/_helpers.php`, 3 pohon)
- Permission baru **`finance.operational`** (Input Operasional) di grup "Keuangan & Laporan".
- Role **cashier** otomatis dapat `finance.operational` (seed + topup) tanpa `finance.view`.
- Super Admin & Owner otomatis dapat semua permission via `owner_permission_topup`.

### Auth core (`app|legacy/app|pos/app/Core/Auth.php`)
- Method baru `guard_finance_operational()`.

`VERSION` → 17.0.48; SystemUpgrade + halaman System Upgrade.

## Verifikasi (E2E lokal, user role kashir)
- Halaman Input Operasional → **200**.
- Kasir submit Listrik Rp150.000 → **tersimpan** di `finance_transactions` (type=expense, `reference_type='operational'`, kategori Operasional).
- Kasir coba buka **Keuangan** / **Akuntansi** → **diblokir** ("tidak punya hak").
- Data keuangan lama tidak disentuh; hanya menambah baris beban.
- `php -l` 0 error semua berkas di 3 pohon; 24 cek kontrak lulus.

## Pasang
```bash
cd ~/public_html
wget -qO apply_patch_v17_0_48.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_48.sh
bash apply_patch_v17_0_48.sh
```
Lalu **Purge All** (LiteSpeed) + Ctrl+F5.
