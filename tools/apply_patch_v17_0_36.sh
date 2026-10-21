#!/usr/bin/env bash
# V17.0.36 — guard function_exists di checkout/order/detail (anti white-screen).
set -uo pipefail
VERSION="17.0.36"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.36/PATCH_v17.0.36.zip"
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
echo "    ${COUNT} berkas"

unzip -o -q "${ZIP}"; rm -f "${ZIP}"

FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/public/store/checkout.php" "function_exists('marketplace_cod_enabled')" "guard COD di checkout"
check "legacy/public/store/checkout.php" "function_exists('marketplace_store_pickup_close_time')" "guard batas ambil di checkout"
check "legacy/public/store/order.php" "function_exists('marketplace_store_pickup_deadline_label')" "guard batas ambil di pesanan"
check "legacy/public/admin/marketplace-orders/detail.php" "function_exists('marketplace_store_pickup_deadline_label')" "guard di detail admin"
check "VERSION" '17.0.36' "VERSION 17.0.36"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi
echo
echo "==> SELESAI v${VERSION}. Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Buka https://vapekongstore.com/checkout — harus tampil normal."
echo "==> Kalau masih 'Terjadi kesalahan sistem', jalankan dbg35.php (lihat README) dan kirim hasilnya."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
