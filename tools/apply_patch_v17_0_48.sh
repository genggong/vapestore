#!/usr/bin/env bash
# V17.0.48 — menu Input Operasional untuk kasir (beban tanpa buka menu Keuangan).
set -uo pipefail
VERSION="17.0.48"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.48/PATCH_v17.0.48.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

[ -f index.php ] || { echo "ERROR: jalankan dari docroot (~/public_html)." >&2; exit 1; }
echo "==> Docroot: $(pwd)"
wget -qO "${ZIP}" "${ZIP_URL}" || { echo "!! Gagal unduh zip. Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1; }
unzip -tq "${ZIP}" >/dev/null || { echo "!! Arsip rusak. TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1; }

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
check() { if [ -f "$1" ] && grep -Fq -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/public/admin/operasional/index.php" 'guard_finance_operational' "halaman Input Operasional"
check "legacy/public/admin/operasional/index.php" 'reference_type' "beban masuk keuangan+akuntansi"
check "legacy/public/layouts/admin-sidebar.php" 'operasional/index.php' "menu di bawah Request Stock"
check "legacy/public/admin/roles/_helpers.php" 'finance.operational' "permission + role cashier"
check "legacy/public/admin/finance/index.php" "requirePermission('finance.view')" "kasir tak bisa buka Keuangan"
check "VERSION" '17.0.48' "VERSION 17.0.48"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Marker tidak cocok. Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI v${VERSION}. ZIP sudah diextract & dihapus."
echo "==> Menu: Operasional > Input Operasional (di bawah Request Stock)."
echo "==> Catatan: role cashier otomatis dapat akses input operasional; menu Keuangan tetap admin-only."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
