# 📦 PATCH VAPESTORE POS — v17.0.12 (Gabungan)

> Isi patch ini = **SEMUA perbaikan** sampai versi v17.0.12, jadi satu paket.
> Ikuti langkahnya urut — **backup dulu**, baru timpa, baru jalankan script, baru tes.

---

## 📋 Ringkasan Isi Patch

| # | Perbaikan | File |
|---|-----------|------|
| 1 | Nilai modal stok POS sama dgn Excel/Opname (**61.939.048**) | `admin/reports/stock.php` |
| 2 | Password pelanggan marketplace (admin set/reset) | `admin/customers/edit.php` |
| 3 | Harga beli menjadi **rata-rata tertimbang** (tidak menimpa harga lama) | `admin/purchases/_helpers.php`, `admin/purchases/create.php`, `admin/stock-requests/action.php` |
| 4 | Display request stock ala POS (stok gudang berkurang di keranjang) | `assets/js/stock-request.js` |
| 5 | Script isi password default `vapekong123` utk member yg belum punya | `tools/set_default_password.php` |

> ⚠️ **Penting:** rata-rata tertimbang hanya berlaku **mulai transaksi baru**. Pembelian/request lama **tidak** diubah.

---

## 🛠️ Cara Upload Patch ke Hosting

### Langkah 0 — Back Up Dulu (wajib)
Di cPanel:
1. **File Manager** → folder tempat aplikasi Vapekong (mis. `public_html` atau subfolder)
2. **Select All** → klik kanan → **Compress** → pilih **Zip**
3. Nama misal `backup_sebelum_v1712.zip`
4. Unduh ke komputer via **Download**

> Kalau DB juga mau di-backup: **phpMyAdmin** → pilih database `vapekong_pos` → **Export** → Quick → Go.

---

### Langkah 1 — Download Patch dari GitHub

Opsi A (Zip langsung, paling mudah):
- Buka: https://github.com/genggong/vapestore
- Masuk folder **`patch/v17.0.12/`** → download **`Vapestore_POS_PATCH_GABUNGAN_v17.0.12.zip`**
- Atau klik hijau **`<> Code`** → **Download ZIP** (mengambil semua source, patch ada di dalam `patch/v17.0.12/`)

Opsi B (Sudah punya repo atas):
```bash
cd /tmp && git clone https://github.com/genggong/vapestore.git
cd vapestore/patch/v17.0.12
```

---

### Langkah 2 — Upload & Timpa File

**Di cPanel File Manager:**
1. **Zip** `Vapestore_POS_PATCH_GABUNGAN_v17.0.12.zip` upload ke **satu tingkat di atas** folder aplikasi (mis. `public_html/` jika aplikasi di `public_html/`)
2. Klik kanan zip → **Extract**
3. Hasil ekstrak = folder `legacy/...` → **pindahkan/merge** isinya ke folder aplikasi (semua `legacy/public/admin/...` ditimpa)
4. Pastikan path akhir sesuai tabel ini:

| File di zip | Lokasi di hosting (timpa) |
|---|---|
| `legacy/public/admin/reports/stock.php` | `public_html/legacy/public/admin/reports/stock.php` |
| `legacy/public/admin/customers/edit.php` | `public_html/legacy/public/admin/customers/edit.php` |
| `legacy/public/admin/purchases/_helpers.php` | `public_html/legacy/public/admin/purchases/_helpers.php` |
| `legacy/public/admin/purchases/create.php` | `public_html/legacy/public/admin/purchases/create.php` |
| `legacy/public/admin/stock-requests/action.php` | `public_html/legacy/public/admin/stock-requests/action.php` |
| `legacy/public/assets/js/stock-request.js` | `public_html/legacy/public/assets/js/stock-request.js` |
| `tools/set_default_password.php` | upload ke mana saja, mis. `public_html/tools/` (utk dijalankan sekali) |

> 💡 Jika folder aplikasi bukan `public_html` (mis. `public_html/vapekong`), sesuaikan path-nya.

---

### Langkah 3 — Jalankan Script Password Default (sekali saja)

Cara mudah via **cPanel → Terminal** (atau SSH):
```bash
cd ~/public_html
php tools/set_default_password.php
```
Yang diharapkan keluar:
```
Pelanggan aktif tanpa password: N
Password default 'vapekong123' di-set untuk N pelanggan.
Total pelanggan aktif yg punya password sekarang: ...
SELESAI
```

> ⚠️ **Setelah berhasil: HAPUS folder `tools/`** di hosting — script berisi kredensial DB.

---

### Langkah 4 — Verifikasi

1. **Hard refresh browser** biar JS baru ke-load: `Ctrl + F5` (Windows) / `Cmd + Shift + R` (Mac)
2. Cek **Laporan → Stok** → nilai modal = **Rp 61.939.048**
3. Cek **Pelanggan → Edit** salah satu member → muncul field **"Password Marketplace (opsional)"**
4. Coba **Request Stock** → tambahkan barang ke keranjang → stok gudang di daftar **berkurang** otomatis
5. Coba **Buat Pembelian** produk yg sudah ada dgn harga beda → buka **Laporan Stok** lagi → harga beli produk jadi nilai **rata-rata** (bukan harga baru)

---

## 🧪 Info Teknis (buat yang mau cek)

- Semua file PHP lulus **`php -l`** (lint).
- Unit test fungsi `avg_hpp()`: **5/5 PASS** (10×100 + 10×200 → 150; stok kosong → harga baru; qty 0 → harga tetap, dst).
- Commit GitHub: `257bd89` (v17.0.11b) + `33bdded` (v17.0.12) di branch `main`.

## ♻️ Rollback (kalau ada masalah)

1. Upload lagi **backup_sebelum_v1712.zip** dari Langkah 0 → Extract → timpa semua
2. Kalau DB ikut di-restore: **phpMyAdmin → Import** backup SQL