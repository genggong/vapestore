#!/usr/bin/env bash
set -uo pipefail
VERSION="17.0.49"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.49/PATCH_v17.0.49.zip"
ZIP="PATCH_${VERSION}.zip"; BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"
[ -f index.php ] || { echo "ERROR: jalankan dari docroot." >&2; exit 1; }
wget -qO "${ZIP}" "${ZIP_URL}" || { echo "!! Gagal unduh zip." >&2; rm -f "${ZIP}"; exit 1; }
unzip -tq "${ZIP}" >/dev/null || { echo "!! Arsip rusak." >&2; rm -f "${ZIP}"; exit 1; }
mkdir -p "${BACKUP_DIR}"; while IFS= read -r f; do case "$f" in */) continue;; esac; [ -f "$f" ] || continue; mkdir -p "${BACKUP_DIR}/$(dirname "$f")"; cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true; done < <(unzip -Z1 "${ZIP}")
unzip -o -q "${ZIP}"; rm -f "${ZIP}"
FAIL=0
check() { if [ -f "$1" ] && grep -Fq -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/app/Modules/Accounting/Accounting.php" "['operating_expense'];" "laba bersih tanpa ongkir ganda"
check "legacy/public/admin/accounting/index.php" "Ongkir sudah masuk penjualan bersih" "label ongkir"
check "VERSION" '17.0.49' "VERSION 17.0.49"
if [ $FAIL -ne 0 ]; then echo "!! Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"; exit 1; fi
echo
echo "==> SELESAI v${VERSION}. Purge All + Ctrl+F5."
echo "==> Backup: ${BACKUP_DIR}"
