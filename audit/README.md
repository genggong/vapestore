# Kit audit pra-deploy (backup)

Perangkat yang dipakai untuk mengaudit rilis **sebelum** dipasang ke hosting. Semua dijalankan lokal —
tidak menyentuh server produksi. Dipakai berulang setiap rilis: jalankan, bereskan temuannya, baru paket.

## Prasyarat

- PHP 8.x CLI (`php -v`) + ekstensi `pdo_mysql`
- MySQL 8 lokal untuk audit alur DB (`02_db_flow.php`, `03_code_schema_map.py`)
- `gh` CLI terautentikasi bila ingin cek URL GitHub (opsional)
- Python 3.11+ (stdlib saja)

Semua target bisa diatur lewat env var; default aman untuk uji:

```bash
export VK_DB_HOST=127.0.0.1
export VK_DB_NAME=vapekong_audit     # database uji, TIDAK dipakai produksi
export VK_DB_USER=vkaudit
export VK_DB_PASS=...                # password user uji
export VK_REPO=$PWD                  # default: root repo (parent folder audit/)
```

Database uji dibuat sekali (butuh akses admin MySQL):

```bash
mysql -u root -e "CREATE DATABASE vapekong_audit CHARACTER SET utf8mb4;
  CREATE USER 'vkaudit'@'127.0.0.1' IDENTIFIED BY '<pass>';
  GRANT ALL ON vapekong_audit.* TO 'vkaudit'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

## Urutan menjalankan

```bash
# 1) croscek script & perintah + kesepakatan versi (tanpa DB)
python3 audit/01_scripts.py

# 2) alur database DIJALANKAN: baseline "dump lama" -> vk_sys_upgrade -> kelengkapan + idempotensi
php audit/02_db_flow.php

# 3) peta kode <-> skema: semua tabel/kolom yang dipakai file patch harus ada di skema hasil repair
python3 audit/03_code_schema_map.py

# 4) suite restore di docroot tiruan (HOME palsu, server HTTP lokal, tidak menyentuh hosting)
bash audit/test_restore.sh

# 5) suite apply patch di docroot tiruan (uji backup .bak + full tar + verifikasi marker)
bash audit/test_apply_patch.sh
```

Semua skrip keluar dengan exit code `0` bila lulus, `1` bila ada temuan.

## Generator (dipakai saat memperbaiki, bukan hanya memeriksa)

- `derive_indexes.py` — kumpulkan definisi index dari semua `CREATE TABLE` installer, bandingkan dengan
  `vk_sys_expected_indexes()` + `$indexJobs`, cetak yang belum bisa dibuat ulang.
- `derive_columns.py` — kumpulkan kolom yang dideklarasikan installer tapi tidak ada di
  `vk_sys_expected_schema()` (kandidat kolom repair untuk dump lama).
- `build_master_zip.py` — bangun `MASTER_VAPEKONG_v<VER>_CODE.zip` dari `git ls-files`:
  membuang `patch/`, `tests/`, `uploads/`, `.git*`, `hash.php`, dan **file config yang menyimpan
  kredensial asli**; menambah skeleton `writable/`; memverifikasi `testzip()` + file kunci.

## Catatan penting (pengalaman rilis v17.0.16–17.0.18)

- `CREATE TABLE IF NOT EXISTS` **tidak** menambah kolom pada tabel yang sudah ada — kolom baru hanya
  masuk bila tabelnya benar-benar baru. Karena itu daftar `$columnsTerdeteksi` di
  `app/Modules/System/SystemUpgrade.php` (3 tree, wajib identik) yang menjaga halaman tidak fatal
  "Unknown column" pada dump lama.
- Semua installer di `vk_sys_run_installers()` harus path-nya benar; `SKIP INSTALLER (fungsi tidak
  ditemukan)` = jalur require salah, bukan gangguan sesaat.
- `CONSTRAINT ... FOREIGN KEY` jangan inline di dalam `CREATE TABLE`: satu ketidakcocokan tipe
  membatalkan seluruh pembuatan tabel. Pasang terpisah dalam blok `try/catch` (best-effort).
- Skrip CLI di `tools/` wajib menyalakan ulang `display_errors` SETELAH bootstrap dan mencetak
  `DB OK: <db>` — tanpa itu kegagalan fatal terlihat seperti "sukses tanpa output".
- Saat paket/generate: pastikan tiga titik versi sepakat — `VERSION`, `patch/latest.txt`, dan folder
  `patch/RESTORE_MASTER_v<VER>/`. `latest.txt` yang tertinggal membuat `update.sh` menolak berjalan.