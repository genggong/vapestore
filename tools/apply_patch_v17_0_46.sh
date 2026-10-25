#!/usr/bin/env bash
# V17.0.46 — validasi area Kurir Toko (fix "Alamat tidak termasuk area layanan"). Pasang via unggah ZIP,
# skrip ini hanya pemandu; kode aktual ada di PATCH_v17.0.46.zip. Jalankan DARI docroot.
set -uo pipefail
VERSION="17.0.46"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

[ -f index.php ] || { echo "ERROR: jalankan dari docroot (~/public_html)." >&2; exit 1; }
[ -f "PATCH_${VERSION}.zip" ] || { echo "ERROR: file PATCH_${VERSION}.zip belum ada di docroot. Unduh ZIP terlebih dahulu (repo private, tidak bisa wget)." >&2; exit 1; }
unzip -tq "PATCH_${VERSION}.zip" >/dev/null || { echo "!! Arsip rusak. Docroot TIDAK diubah." >&2; exit 1; }

echo "==> Backup ke ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}" 2>/dev/null; mkdir -p "${BACKUP_DIR}"; COUNT=0
while IFS= read -r f; do
  case "$f" in */) continue;; esac
  COUNT=$((COUNT+1)); [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"; cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done < <(unzip -Z1 "PATCH_${VERSION}.zip")
echo "    ${COUNT} berkas diperiksa"

unzip -o -q "PATCH_${VERSION}.zip"

FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/app/Modules/Marketplace/StoreCourier.php" 'marketplace_v16_store_courier_rate($db, $destinationId' "validasi DB tanpa resolve API"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'validate_destination($payload, $destinationId, $branchId)' "branch diteruskan"
check "VERSION" '17.0.46' "VERSION 17.0.46"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Ada marker tidak cocok. Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi
echo
echo "==> SELESAI v${VERSION}."
echo "==> Uji: checkout Kurir Toko aktif — alamat kelurahan bertarif kini diterima."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Backup: ${BACKUP_DIR}"
