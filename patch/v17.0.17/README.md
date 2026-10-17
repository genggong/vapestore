# V17.0.17 — Password Marketplace untuk Member Baru dari POS + Guard Input Member POS

## Masalah yang diperbaiki

1. **Tambah Member dari POS tidak bisa memberi password marketplace.**
   Halaman `admin/customers/create.php` (tombol *Tambah Member* di POS) tidak punya field
   password, dan INSERT-nya tidak mengisi kolom marketplace. Akibatnya member yang dibuat
   dari POS selalu punya `marketplace_password_hash = NULL` → **tidak bisa login marketplace**
   sampai admin buka Edit Pelanggan atau menjalankan `set_default_password`.
2. **Field password hanya ada di satu tree.** Fitur set/reset password administrasi baru ada di
   `legacy/public/admin/customers/edit.php`; salinan di tree `public/` dan `pos/` masih generasi
   lama (tanpa field password) → perilaku berbeda tergantung tree yang disajikan.
3. **POS bisa "gagal input member" tanpa penjelasan.** Query daftar member di `admin/pos/index.php`
   dibungkus `try/catch` yang **membuang error** (`$customers = []`). Kalau kolom baru
   (mis. `price_level`) belum ada di database, dropdown member jadi kosong dan kasir hanya melihat
   tulisan *"Belum ada member aktif"* — padahal data membernya ada. Tidak ada pesan, tidak ada log.

## Perbaikan

- **`admin/customers/create.php` (3 tree)** — field **Password Marketplace (opsional)** + validasi
  minimal 6 karakter + INSERT dengan `marketplace_password_hash` & `marketplace_registered_at`.
  Kolom marketplace dipastikan ada lebih dulu (cek `SHOW COLUMNS` → `ALTER` hanya bila kurang,
  idempotent, tanpa DROP). Field dikosongkan = perilaku lama (member dibuat tanpa login marketplace).
- **`admin/customers/edit.php` (`public/` & `pos/`)** — disinkronkan dengan tree `legacy/`
  (set/reset password admin), helper URL/redirect disesuaikan per tree (`base_url()`/`redirect()`,
  bukan `legacy_*`) supaya tidak 500 di tree non-legacy.
- **`admin/pos/index.php` (3 tree)** — daftar member tidak lagi gagal senyap:
  `error_log('[POS][member] ...')` + **fallback query tanpa `price_level`** (level harga default
  `retail`) sehingga kasir tetap bisa memilih member, dan kalau dua-duanya gagal barulah tampil
  pesan merah *"Gagal memuat daftar member: <pesan>"* beserta saran menjalankan System Upgrade.
- **`tools/set_default_password.php`** — urutan bootstrap diperbaiki (docroot memuat
  `app/bootstrap.php` langsung, bukan `legacy/app/...`), ada baris `DB OK: <dbname>` supaya
  kegagalan senyap bisa dibedakan, dan pesan kolom baru dibuat eksplisit.
- **Versi terlihat di-bump** → `VERSION` = 17.0.17, System Upgrade / Schema Doctor (3 tree)
  dan halaman `/admin/system-upgrade/index.php`.

## File yang terlibat

| File | Perubahan |
|---|---|
| `VERSION` | 17.0.16 → 17.0.17 |
| `{legacy/public,public,pos/public}/admin/customers/create.php` | field + simpan password marketplace |
| `{public,pos/public}/admin/customers/edit.php` | sinkron fitur password dari legacy |
| `{legacy/public,public,pos/public}/admin/pos/index.php` | log + fallback daftar member, pesan gagal |
| `{app,legacy/app,pos/app}/Modules/System/SystemUpgrade.php` | bump versi modul |
| `legacy/public/admin/system-upgrade/index.php` | bump versi terlihat |
| `tools/set_default_password.php` | bootstrap docroot-first + `DB OK` |

Catatan: `legacy/public/admin/customers/edit.php` tidak berubah (fiturnya sudah ada sejak v17.0.12)
— tetap ikut di paket agar tiga tree konsisten setelah apply.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_17.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_17.sh
bash apply_patch_v17_0_17.sh
```

Script: backup file lama → `unzip -t` (cegah arsip rusak) → `unzip -o` (overwrite pasti) →
verifikasi 11 file kunci + 5 marker → jalankan `tools/set_default_password.php` (aman diulang).

Lalu **WAJIB**: cPanel → **LiteSpeed Cache Manager → Purge All**, dan buka
`/admin/system-upgrade/index.php` → klik **Jalankan Upgrade Otomatis** (additive, idempotent).

## Verifikasi setelah pasang

1. POS → **Tambah Member** → isi nama + No HP + password → Simpan → member bisa login
   marketplace pakai No HP + password itu.
2. Pelanggan → **Edit** (dari POS maupun admin) → field *Password Marketplace (opsional)* ada,
   dikosongkan = password tidak berubah.
3. POS → kotak member: ketik 4 digit belakang No HP → member muncul. Kalau daftar kosong,
   sekarang ada pesan merah penyebabnya (bukan sekadar "Belum ada member aktif").
4. `/admin/system-upgrade/index.php` menampilkan **v17.0.17**.

## Perbaikan penuh / restore (kalau situs 500 atau file patut dicurigai)

Snapshot master v17.0.17 sudah disertakan, jadi perintah pemulihan tetap sama:

```bash
cd ~/public_html
wget -qO update.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/UPDATE_VAPEKONG.sh"
bash update.sh            # jalan kalau versi server != 17.0.17
bash update.sh --force    # paksa timpa ulang (mode perbaikan)
```

`update.sh` membaca `patch/latest.txt` (kini `17.0.17|RESTORE_MASTER_v17.0.17`) lalu memanggil
`restore_master.sh` v17.0.17: backup → unduh → uji zip → pindahkan folder nyasar → timpa → verifikasi.
Marker yang diverifikasi kini termasuk `marketplace_password_hash` (create.php),
`Password Marketplace` (edit.php tree public), dan `$customerLoadError` (guard input member POS).
Perbaikan kecil: VERSION yang korup/kosong tidak lagi dianggap "lebih baru" (sebelumnya `sort -V`
menempatkan string non-numerik di atas angka sehingga update berhenti).

## Rollback

```bash
cd ~/public_html
for b in $(find backup_17.0.17_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
```
Lalu Purge All. (Salinan `.bak` hanya untuk file yang benar-benar ditimpa patch.)

## Hasil uji lokal (sebelum dikirim)

- `php -l` pada semua 14 file yang berubah: bersih.
- Kontrak test `tests/test_patch_v17_0_17.py`: **10/10 OK**; regresi `tests/test_patch_v17_0_16.py`: **22/22 OK**.
- Probe MySQL (dump produksi & DB sintetis): jalur lama = daftar member **kosong** (bug) →
  jalur baru = daftar member terisi lewat fallback; member baru dari POS login marketplace
  **BERHASIL** (`password_verify`), password salah ditolak; field kosong tetap NULL.
- Nilai Modal Stok dicek ulang terhadap dump produksi: 61.714.048 + 225.000 (Dripstate
  2 × 112.500) = **61.939.048** = angka Excel (selisih 0) di formula Laporan Stok, Akuntansi, dan Dashboard.
- Skrip apply diuji di docroot palsu: exit 0, 14 file `.bak` dibuat, `.env` tidak tersentuh,
  rollback mengembalikan VERSION 17.0.16.