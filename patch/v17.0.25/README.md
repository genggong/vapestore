# V17.0.25 — Perbaikan "Gagal membuat pembelian: Call to undefined function avg_hpp()" + Master Role + Laporan Ongkir Toko

## 1. Fatal saat simpan pembelian (PRIORITAS)

Gejala di server: simpan pembelian gagal dengan
`Call to undefined function avg_hpp()`.

Sebab: halaman `admin/purchases/create.php` memanggil `avg_hpp()` (fungsi rata-rata HPP dari
v17.0.12) yang seharusnya ada di `admin/purchases/_helpers.php`. Di server, file `_helpers.php`
masih generasi lama (belum punya fungsi itu) — kelas masalah "file lama tertinggal" yang sama
seperti kasus `APP_ASSET_VERSION` dan `accounting/index.php`.

Perbaikan dua lapis:
1. `_helpers.php` (3 tree) dikirim ulang di paket ini, jadi fungsi aslinya pasti ada.
2. `create.php` (3 tree) dapat **pengaman**: kalau `avg_hpp()` tetap tidak ada, fungsi didefinisikan
   langsung di halaman dengan rumus yang sama — jadi pembelian **tidak mungkin** fatal lagi karena
   helper tertinggal.

Bukti uji lokal: `_helpers.php` sengaja "dirusak" (fungsi `avg_hpp` dihapus), lalu pembelian
varian 2 pcs @ 400.000 lewat form → **berhasil**, modal varian 250.000 → 400.000, stok gudang 0 → 2.

## 2. Menu Role: Master Role bisa ditambah & dihapus

Sebelumnya menu **Role & Akses** hanya bisa *mengatur* akses role bawaan — tidak ada cara membuat
role baru.

Sekarang di `/admin/roles/index.php`:
- tombol **Tambah Role** → halaman baru `admin/roles/create.php`
  (nama, slug otomatis, deskripsi, dan **"Salin akses dari role"** agar tidak perlu mencentang manual),
- tombol **Hapus** per role (POST + CSRF + konfirmasi) → `admin/roles/delete.php`,
- pengaman: role sistem **Super Admin** & **Owner** tidak bisa dihapus, dan role yang **masih dipakai
  user** ditolak dengan pesan jumlah user-nya,
- slug `super_admin`/`owner` juga ditolak saat membuat role baru (tidak bisa membajak role sistem).

## 3. Permission **Laporan Ongkir Toko** (bisa diberikan ke role mana pun)

Halaman **Laporan Ongkir Kurir Toko** dulu dikunci keras `requireRole(['super_admin','owner'])`,
jadi role lain mustahil diberi akses dan permission-nya tidak ada di daftar centang.

Sekarang ada grup permission baru **Marketplace & Kurir**:
- `courier_income.view` → **Laporan Ongkir Toko**
- `courier_income.export` → **Export Laporan Ongkir (CSV/XLSX)**

Halaman laporan memakai guard baru `Auth::requirePermissionOrRole(['super_admin','owner'], …)`,
artinya **Owner & Super Admin tetap bisa** (kompatibel), dan role lain bisa diberi akses lewat
checklist Atur Akses.

Tambahan penting: `owner_permission_topup()` — permission **baru** otomatis masuk ke Super Admin &
Owner walau tabel `role_permissions` sudah terisi (dulu seed berhenti total begitu tabel ada isinya,
sehingga permission baru tidak pernah sampai ke owner).

Bonus perbaikan laten: `Auth` legacy memanggil `redirect()`, padahal di tree legacy hanya ada
`legacy_redirect()` — ditambahkan alias aman (`function_exists` guard) supaya guard yang menolak
akses tidak berubah jadi 500.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_25.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_25.sh
bash apply_patch_v17_0_25.sh
```

Lalu **Purge All**.

## Verifikasi di server

1. **Pembelian**: buat pembelian (produk/varian apa saja) → harus tersimpan, tanpa fatal `avg_hpp`.
2. **Role**: `/admin/roles/index.php` → **Tambah Role** → mis. "Master Kurir" → salin akses dari Owner
   → tersimpan → tombol Hapus berfungsi (dan menolak role yang masih dipakai user).
3. **Laporan ongkir**: Atur Akses role baru → centang **Laporan Ongkir Toko** → login sebagai user
   role itu → menu laporan terbuka. Owner tetap bisa.
4. `/admin/system-upgrade/index.php` → **v17.0.25**.

## Hasil uji lokal

- Pembelian dengan `_helpers.php` stale → **berhasil** (modal varian 250.000 → 400.000, stok 0 → 2).
- Tambah role "Master Kurir" (salin akses Owner) → role id 10, **35 permission**, termasuk
  `courier_income.view`; owner juga mendapat 2 permission baru dari topup.
- Akses sebagai role tersebut: **Laporan Ongkir 200**, **Export 200 (XLSX)**, **Pembelian 302 (ditolak)**.
- Hapus role uji → berhasil; hapus Owner → **ditolak**, owner tetap ada.
- Halaman Atur Akses menampilkan grup **Marketplace & Kurir** dengan 2 item.
- `tests/test_patch_v17_0_25.py` **14/14 OK**; regresi 17.0.16–17.0.24 hijau; `php -l` bersih (35 file paket).

## Rollback berkas (kalau ada yang tidak sesuai)

```bash
cd ~/public_html
bash backup_17.0.25_*/rollback.sh
```

Memulihkan berkas yang ditimpa (dari `.bak`). Berkas **baru** dari patch ini
(`admin/roles/create.php`, `admin/roles/delete.php`) tidak dihapus otomatis — aman dibiarkan.
Diuji lokal: apply → rollback → `VERSION` kembali **17.0.24**, `.env` & `uploads/` utuh.
