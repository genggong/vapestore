#!/usr/bin/env bash
# V17.0.18 — HOTFIX "Terjadi kesalahan sistem" di halaman admin:
#   Undefined constant "APP_ASSET_VERSION" (layout admin memakai konstanta yang tidak pernah
#   didefinisikan karena helpers.php di server masih versi lama / file helper aset hilang).
# Dijalankan DARI docroot hosting (folder berisi index.php CI4).
#   wget -qO apply_patch_v17_0_18.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_18.sh
#   bash apply_patch_v17_0_18.sh
set -uo pipefail

VERSION="17.0.18"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.18/PATCH_v17.0.18.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot (folder berisi index.php)." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Backup file lama ke: ${BACKUP_DIR}/"

FILES="
VERSION
app/bootstrap.php
app/Helpers/asset_helpers.php
app/Helpers/helpers.php
app/Modules/System/SystemUpgrade.php
legacy/app/bootstrap.php
legacy/app/Helpers/asset_helpers.php
legacy/app/Helpers/helpers.php
legacy/app/Modules/System/SystemUpgrade.php
legacy/public/admin/system-upgrade/index.php
pos/app/bootstrap.php
pos/app/Helpers/asset_helpers.php
pos/app/Helpers/helpers.php
pos/app/Modules/System/SystemUpgrade.php
"

mkdir -p "${BACKUP_DIR}"
for f in $FILES; do
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done
echo "    (file lama tersimpan: $(find "${BACKUP_DIR}" -name '*.bak' | wc -l) file)"

echo "==> Unduh patch..."
if ! wget -qO "${ZIP}" "${ZIP_URL}"; then
  echo "!! Gagal mengunduh patch. Docroot TIDAK diubah." >&2; exit 1
fi
if ! unzip -tq "${ZIP}" >/dev/null; then
  echo "!! Arsip patch rusak (unzip -t gagal). Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1
fi
unzip -o "${ZIP}"
rm -f "${ZIP}"

echo "==> Verifikasi file kunci..."
OK=1
for f in legacy/app/bootstrap.php legacy/app/Helpers/helpers.php legacy/app/Helpers/asset_helpers.php \
         app/bootstrap.php app/Helpers/helpers.php app/Helpers/asset_helpers.php \
         pos/app/bootstrap.php pos/app/Helpers/helpers.php pos/app/Helpers/asset_helpers.php ; do
  if [ ! -s "$f" ]; then echo "  [MISSING/KOSONG] $f"; OK=0; continue; fi
  echo "  [OK] $f"
done
[ $OK -eq 1 ] || { echo "!! Ada file patch tidak lengkap — jangan lanjut sebelum dicek."; exit 1; }

# Catatan penting kalau skrip berhenti di tahap marker: file SUDAH tertimpa (unzip jalan lebih dulu).
# Lanjutkan dengan Purge All + tes halaman; JANGAN panik, tidak ada yang perlu di-upload ulang.

echo "==> Verifikasi marker..."
grep -q "if (!defined('APP_ASSET_VERSION'))" legacy/app/bootstrap.php \
  || { echo "!! Guard konstanta aset tidak ada di bootstrap legacy."; exit 1; }
grep -q "if (!defined('APP_ASSET_VERSION'))" app/bootstrap.php \
  || { echo "!! Guard konstanta aset tidak ada di bootstrap root."; exit 1; }
grep -q "asset_helpers.php" legacy/app/Helpers/helpers.php \
  || { echo "!! helpers.php legacy masih tanpa require asset_helpers."; exit 1; }
# Referensi konstanta bisa ada di layout (versi baru) ATAU di halaman admin (versi 17.0.16c).
# Yang penting: guard sudah dipasang supaya konstanta terdefinisi apa pun versi file-nya.
REF=$(grep -l "APP_ASSET_VERSION" \
        legacy/public/layouts/admin-header.php \
        legacy/public/admin/pos/index.php \
        legacy/public/admin/customers/edit.php \
        legacy/public/maintenance.php 2>/dev/null | wc -l | tr -d ' ')
[ "$REF" -ge 1 ] \
  || { echo "!! Tidak ada file yang memakai APP_ASSET_VERSION — paket tidak sesuai target."; exit 1; }
echo "  [OK] guard + helper terpasang; referensi APP_ASSET_VERSION ditemukan di ${REF} file"

echo "==> Uji runtime konstanta (butuh koneksi DB dari aplikasi)..."
if php -r 'require "legacy/app/bootstrap.php"; echo defined("APP_ASSET_VERSION") ? "APP_ASSET_VERSION=" . APP_ASSET_VERSION : "APP_ASSET_VERSION=TIDAK TERDEFINISI";' 2>/dev/null; then
  echo
else
  echo "    (uji runtime dilewati — bootstrap butuh konteks web/env DB; lanjut)"
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: cPanel -> LiteSpeed Cache Manager -> Purge All."
echo "==> Tes: /admin/pos/index.php  dan  /admin/customers/edit.php?id=<id>  (harus tampil normal)"
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done  lalu Purge All."