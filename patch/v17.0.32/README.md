# V17.0.32 — Password member otomatis `vapekong123` + login pakai No HP lebih toleran

## Masalah yang ditemukan

Di data produksi, **266 dari 269 member tidak punya password marketplace** (`marketplace_password_hash` kosong).
Form Tambah Pelanggan hanya mengisi password kalau kolomnya diisi manual — jadi member baru
belum bisa login sama sekali, bukan "salah password vapekong123".

## 1. Admin daftar member → password otomatis

- Kolom **Password Marketplace** sekarang bertuliskan *"Kosongkan = otomatis vapekong123"*.
- Kalau dikosongkan, sistem **langsung membuat password `vapekong123`** (hash, bukan teks biasa)
  dan menampilkannya di notifikasi: *"Password login marketplace: vapekong123 (No HP + password ini)."*
- Kalau admin mengisi sendiri, password itulah yang dipakai (minimal 6 karakter).
- Password default bisa diubah lewat setting `member_default_password`.

## 2. Member lama otomatis bisa login

Login marketplace sekarang **self-heal**: member yang belum punya password diterima saat masuk
memakai password default, dan hash-nya **langsung disimpan** (jadi tidak dihitung ulang setiap login).
Password yang sudah pernah diset **tidak ditimpa**.

Nomor HP juga lebih toleran: `0812…`, `+62812…`, `62812…`, pakai spasi/tanda hubung — semuanya cocok.

## 3. Isi password massal (opsional)

```bash
php tools/set_default_password.php            # DRY-RUN: hanya menghitung
php tools/set_default_password.php --apply    # tulis password default ke member yang kosong
```
Hanya yang kosong yang diisi; password lama tidak ditimpa. Aman diulang.
Hapus file tool ini dari hosting setelah dipakai.

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_32.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_32.sh
bash apply_patch_v17_0_32.sh
```

Lalu **Purge All** + Ctrl+F5.

## Verifikasi (sudah diuji lokal)

| uji | hasil |
|---|---|
| Tambah Pelanggan tanpa isi password | member dibuat, hash `$2y$10$…` tersimpan, notifikasi menyebut `vapekong123` |
| Login `0899607230` + `vapekong123` | berhasil |
| Login format `+62…` (nomor sama) | berhasil |
| Login password salah | ditolak |
| Member lama tanpa password, login default | berhasil + hash tersimpan |
| `tools/set_default_password.php` tanpa `--apply` | DRY-RUN, tidak menulis |
| Member aktif punya password (data uji) | 269 / 269 |

`tests/test_patch_v17_0_32.py` **8/8 OK**; regresi 17.0.29–17.0.32 hijau; `php -l` bersih (12 file paket).