#!/usr/bin/env bash
# V17.0.43 — menu Stok Opname Gudang (stok warehouse, template Excel tanpa produk stok 0).
set -uo pipefail
VERSION="17.0.43"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.43/PATCH_v17.0.43.zip"
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
check "legacy/public/admin/stock-opname-gudang/_helpers.php" 'warehouse_opname_items' "tabel opname gudang"
check "legacy/public/admin/stock-opname-gudang/template.php" 'physical_stock' "template excel"
check "legacy/public/admin/stock-opname-gudang/finalize.php" 'warehouse_opname' "finalisasi + kartu stok"
check "legacy/public/layouts/admin-sidebar.php" 'stock-opname-gudang' "menu sidebar Gudang"
check "legacy/public/assets/css/stock-opname.css" 'opname-chip' "CSS form"
check "VERSION" '17.0.43' "VERSION 17.0.43"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Ada marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI v${VERSION}."
echo "==> Buka: Admin -> Gudang -> Opname Gudang (tabel dibuat otomatis saat halaman dibuka)."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
