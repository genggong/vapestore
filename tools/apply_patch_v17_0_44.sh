#!/usr/bin/env bash
# V17.0.44 — Edit ongkir per kelurahan + filter cabang pada tarif kurir toko.
set -uo pipefail
VERSION="17.0.44"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.44/PATCH_v17.0.44.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

[ -f index.php ] || { echo "ERROR: jalankan dari docroot (~/public_html)." >&2; exit 1; }
echo "==> Docroot: $(pwd)"
wget -qO "${ZIP}" "${ZIP_URL}" || { echo "!! Gagal unduh patch. Docroot TIDAK diubah." >&2; exit 1; }
unzip -tq "${ZIP}" >/dev/null || { echo "!! Arsip rusak. Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1; }

echo "==> Backup ke ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}"; COUNT=0
while IFS= read -r f; do
  case "$f" in */) continue;; esac
  COUNT=$((COUNT+1)); [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"; cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done < <(unzip -Z1 "${ZIP}")
echo "    ${COUNT} berkas diperiksa"

unzip -o -q "${ZIP}"; rm -f "${ZIP}"

FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/public/admin/marketplace-courier-rates/index.php" 'fillEdit(this)' "tombol Edit per baris"
check "legacy/public/admin/marketplace-courier-rates/index.php" 'rateId' "mode edit form"
check "legacy/public/admin/marketplace-courier-rates/index.php" 'UPDATE marketplace_store_courier_rates SET fee' "update by id"
check "legacy/public/admin/marketplace-courier-rates/index.php" 'name="fb"' "filter cabang"
check "VERSION" '17.0.44' "VERSION 17.0.44"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Ada marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI v${VERSION}."
echo "==> Buka: Pengaturan Marketplace -> Tarif Kurir Toko per Kelurahan -> klik Edit pada baris."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
