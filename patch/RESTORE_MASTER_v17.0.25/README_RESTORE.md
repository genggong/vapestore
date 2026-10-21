# RESTORE MASTER v17.0.25 — vapekongstore.com

Pulihkan kode `vapekongstore.com` ke **master v17.0.25** langsung dari terminal hosting.
Tanpa upload manual — script yang mengunduh dan **menimpa** (overwrite) sendiri.

## Satu perintah (dari docroot)

```bash
cd ~/public_html
wget -qO restore_master.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v17.0.25/restore_master.sh"
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
| 2 | Unduh master | `MASTER_VAPEKONG_v17.0.25_CODE.zip` (~4 MB, 2.143 entri) — belum menyentuh file apa pun |
| 3 | Uji zip dulu | `unzip -t` — kalau rusak/unduhan gagal, **tidak ada** file yang diubah |
| 4 | Buang folder nyasar | `store/` dan `admin/` di docroot dipindah ke `~/_backup_stray_<ts>` (ini yang bikin 500 karena menutupi route) |
| 5 | Timpa | `unzip -o` ke docroot + buat folder runtime `writable/*` + bersihkan cache |
| 6 | Verifikasi | VERSION, `index.php`, `.htaccess`, `legacy/public`+`app`+`system`, dan 4 marker rilis (`shipping_income`, guard sync branch, guard sync warehouse, referensi `APP_ASSET_VERSION`) |

Urutan penting: **backup → unduh → uji → baru timpa**. Kalau unduhan gagal atau zip rusak, docroot sama sekali tidak berubah (folder nyasar pun belum dipindah).

## Setelah restore

1. **LiteSpeed Cache Manager → Purge All** (wajib, cache lama bisa menutupi hasil).
2. Buka `/admin/system-upgrade/index.php` → **Jalankan Upgrade Otomatis** (index/tabel DB).
3. Tes: `/` · `/produk` · `/store` · `/kurir/login` · `/admin/`.

## Yang TIDAK ikut dipulihkan

- **Perubahan database** (tabel/index dari "Jalankan Upgrade Otomatis") — file restore tidak me-rollback DDL. Index tambahan aman dibiarkan.
- **File di `uploads/` dan `storage/backups/`** — media live tidak pernah ditimpa.
- **`.env`** — tidak ada di dalam zip, jadi kredensial aman.
- **Konfigurasi milik hosting — DIJAGA, tidak pernah ditimpa:** `.htaccess`, `php.ini`, `.user.ini`,
  dan `*/app/Config/database.php` / `*/app/Config/Database.php` yang sudah ada di server. Ini sesuai
  `docs/DEPLOY_STAGING.md` ("build ini mempertahankan konfigurasi database aplikasi lama"), dan penting
  karena kalau kredensial DB ikut tertimpa, situs langsung mati "Koneksi database gagal".
  Script juga memperingatkan bila `.htaccess` tidak memuat `RewriteRule ^ index.php [L]`.
- **`pos/app/Config/database.php`** — file ini di repo memuat kredensial asli, jadi **tidak** dikirim
  di snapshot. Restore mempertahankan versi milik server; kalau file itu belum ada di server,
  isi manual sesuai database setempat.
- **`hash.php`** — utility lama yang membocorkan hash password (isi default `admin123`) sengaja
  **tidak** dikirim lagi di master. Hapus file itu di hosting kalau masih ada.

## Hasil audit sebelum rilis ini (bukan klaim, semua dijalankan)

- 1.551 file PHP: `php -l` bersih; test bawaan repo 22/22 hijau.
- Alur DB (`vk_sys_upgrade`) dijalankan di MySQL lokal dari baseline "dump lama": **228 langkah, 0 GAGAL,
  0 SKIP**, 39/39 tabel harapan lengkap, 0 kolom kurang, 0 index kurang, dan **idempotent** (tekan dua kali = tidak ada perubahan baru).
- Semua tabel/kolom yang dirujuk 298 file PHP rilis ada di skema hasil repair (**0 rujukan hilang**).
- Restore diuji di docroot tiruan: 38/38 cek lulus, termasuk kredensial DB + `.htaccess` + `php.ini` hosting tetap utuh,
  zip rusak / unduhan gagal tidak mengubah apa pun, dan folder nyasar tetap dipindah walau dijalankan 2x dalam detik yang sama.
- `tools/apply_patch_v17_0_16.sh` sekarang mem-backup **dari isi patch zip**: 303 file ditimpa → 303 file `.bak`
  + `full_docroot.tar.gz` (sebelumnya hanya 52 file yang di-backup dari daftar manual).

## Rollback

```bash
cd ~/public_html
tar xzf ~/backup_code_<ts>.tar.gz          # isi backup
```

Zip-nya juga tersimpan di repo: `patch/RESTORE_MASTER_v17.0.25/MASTER_VAPEKONG_v17.0.25_CODE.zip`.