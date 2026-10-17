#!/usr/bin/env bash
# V17.0.17 — Password marketplace di Tambah Member POS + sinkron halaman Pelanggan 3 tree
#                 + guard input member di POS (tidak lagi gagal senyap).
# Dijalankan DARI docroot hosting (folder berisi index.php CI4).
#   wget -qO apply_patch_v17_0_17.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_17.sh
#   bash apply_patch_v17_0_17.sh
set -uo pipefail

VERSION="17.0.17"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.17/PATCH_v17.0.17.zip"
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
legacy/public/admin/customers/create.php
legacy/public/admin/customers/edit.php
legacy/public/admin/pos/index.php
legacy/public/admin/system-upgrade/index.php
legacy/app/Modules/System/SystemUpgrade.php
public/admin/customers/create.php
public/admin/customers/edit.php
public/admin/pos/index.php
pos/public/admin/customers/create.php
pos/public/admin/customers/edit.php
pos/public/admin/pos/index.php
pos/app/Modules/System/SystemUpgrade.php
app/Modules/System/SystemUpgrade.php
"

mkdir -p "${BACKUP_DIR}"
for f in $FILES; do
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done
# Cadangan PENUH sebagai jaring pengaman kedua: kalau ada file di luar daftar yang ikut tertimpa,
# masih bisa dipulihkan. Media uploads tidak ikut (sudah aman di server).
tar czf "${BACKUP_DIR}/full_docroot.tar.gz" --exclude='uploads' --exclude='backup_*' --exclude='_backup_stray_*' . 2>/dev/null \
  && echo "    cadangan penuh: ${BACKUP_DIR}/full_docroot.tar.gz" || echo "    (cadangan penuh dilewati)"


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
for f in \
  legacy/public/admin/customers/create.php \
  public/admin/customers/create.php \
  pos/public/admin/customers/create.php \
  public/admin/customers/edit.php \
  pos/public/admin/customers/edit.php \
  legacy/public/admin/pos/index.php \
  public/admin/pos/index.php \
  pos/public/admin/pos/index.php \
  app/Modules/System/SystemUpgrade.php \
  legacy/app/Modules/System/SystemUpgrade.php \
  pos/app/Modules/System/SystemUpgrade.php ; do
  if [ ! -s "$f" ]; then echo "  [MISSING/KOSONG] $f"; OK=0; continue; fi
  echo "  [OK] $f"
done
[ $OK -eq 1 ] || { echo "!! Ada file patch tidak lengkap — jangan lanjut sebelum dicek."; exit 1; }

echo "==> Verifikasi marker..."
grep -q 'marketplace_password_hash' legacy/public/admin/customers/create.php \
  || { echo "!! Marker password di create.php tidak ada — patch tidak terpasang benar."; exit 1; }
grep -q 'Password Marketplace' public/admin/customers/edit.php \
  || { echo "!! Marker password di edit.php (tree public) tidak ada."; exit 1; }
grep -q '\$customerLoadError' legacy/public/admin/pos/index.php \
  || { echo "!! Marker guard input member POS tidak ada."; exit 1; }
grep -q 'v17.0.17' legacy/app/Modules/System/SystemUpgrade.php \
  || { echo "!! Versi SystemUpgrade belum ter-bump."; exit 1; }
grep -q 'v17.0.17' legacy/public/admin/system-upgrade/index.php \
  || { echo "!! Versi halaman system-upgrade belum ter-bump."; exit 1; }
echo "  [OK] semua marker terpasang (versi $(cat VERSION 2>/dev/null))"

# --- isi password default member (aman diulang, hanya yg kosong) ---
if [ -f tools/set_default_password.php ]; then
  echo "==> Set password default member (vapekong123)..."
  php tools/set_default_password.php || echo "!! Skrip password gagal — jalankan manual: php tools/set_default_password.php"
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: cPanel -> LiteSpeed Cache Manager -> Purge All (tanpa ini situs masih menyajikan versi lama)."
echo "==> Lalu buka /admin/system-upgrade/index.php dan klik 'Jalankan Upgrade Otomatis' (aman, idempotent)."
echo "==> Rollback: salin isi ${BACKUP_DIR}/ kembali (hilangkan akhiran .bak) lalu Purge All."