# RESTORE MASTER v17.0.17 — vapekongstore.com

Pulihkan kode `vapekongstore.com` ke **master v17.0.17** langsung dari terminal hosting.
Tanpa upload manual — script yang mengunduh dan **menimpa** (overwrite) sendiri.

## Satu perintah (dari docroot)

```bash
cd ~/public_html
wget -qO restore_master.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v17.0.17/restore_master.sh"
bash restore_master.sh
```

Untuk versi terbaru ke depan (perintah tidak pernah berubah lagi, versi dibaca otomatis):

```bash
cd ~/public_html
wget -qO update.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/UPDATE_VAPEKONG.sh" && bash update.sh
```

## Yang dilakukan script (6 langkah)

| # | Langkah | Detail |
|---|---------|--------|
| 0 | Cek docroot | wajib ada `index.php`; tolak kalau dijalankan dari folder salah |
| 1 | Backup kode | `~/backup_code_<ts>.tar.gz` (uploads/media & `storage/backups` dikecualikan) — titik pulang sebelum apa pun diubah |
| 2 | Unduh master | `MASTER_VAPEKONG_v17.0.17_CODE.zip` (~4 MB, 2.143 entri) — belum menyentuh file apa pun |
| 3 | Uji zip dulu | `unzip -t` — kalau rusak/unduhan gagal, **tidak ada** file yang diubah |
| 4 | Buang folder nyasar | `store/` dan `admin/` di docroot dipindah ke `~/_backup_stray_<ts>` (ini yang bikin 500 karena menutupi route) |
| 5 | Timpa | `unzip -o` ke docroot + buat folder runtime `writable/*` + bersihkan cache |
| 6 | Verifikasi | VERSION, `index.php`, `.htaccess`, `legacy/public`+`app`+`system`, dan 4 marker patch v17.0.17 (`shipping_income`, guard sync branch, guard sync warehouse, `APP_ASSET_VERSION`) |

Urutan penting: **backup → unduh → uji → baru timpa**. Kalau unduhan gagal atau zip rusak, docroot sama sekali tidak berubah (folder nyasar pun belum dipindah).

## Setelah restore

1. **LiteSpeed Cache Manager → Purge All** (wajib, cache lama bisa menutupi hasil).
2. Buka `/admin/system-upgrade/index.php` → **Jalankan Upgrade Otomatis** (index/tabel DB).
3. Tes: `/` · `/produk` · `/store` · `/kurir/login` · `/admin/`.

## Yang TIDAK ikut dipulihkan

- **Perubahan database** (tabel/index dari "Jalankan Upgrade Otomatis") — file restore tidak me-rollback DDL. Index tambahan aman dibiarkan.
- **File di `uploads/` dan `storage/backups/`** — media live tidak pernah ditimpa.
- **`.env`** — tidak ada di dalam zip, jadi kredensial aman.
- **`hash.php`** — utility lama yang membocorkan hash password (isi default `admin123`) sengaja **tidak** dikirim lagi di master. Hapus file itu di hosting kalau masih ada.

## Rollback

```bash
cd ~/public_html
tar xzf ~/backup_code_<ts>.tar.gz          # isi backup
```

Zip-nya juga tersimpan di repo: `patch/RESTORE_MASTER_v17.0.17/MASTER_VAPEKONG_v17.0.17_CODE.zip`.

## Yang ikut di v17.0.17 (snapshot ini)

- `admin/customers/create.php` (3 tree): field **Password Marketplace (opsional)** + INSERT
  `marketplace_password_hash`/`marketplace_registered_at` (member dari POS langsung bisa login).
- `admin/customers/edit.php` (tree `public/` & `pos/`): fitur set/reset password admin.
- `admin/pos/index.php` (3 tree): daftar member tidak gagal senyap — `error_log` + fallback query
  tanpa `price_level` + pesan error ke kasir.
- `tools/set_default_password.php`: bootstrap docroot-first + baris `DB OK: <dbname>`.
- Snapshot dibangun dari `git ls-files` HEAD (2.143 file, tanpa `patch/`, `tests/`, `uploads/`, `.env`),
  1.543 file PHP lolos `php -l`.
