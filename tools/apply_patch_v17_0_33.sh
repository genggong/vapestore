#!/usr/bin/env bash
# V17.0.33 — nolkan stok cabang + switcher cabang global (POS, transaksi, stok, laporan).
set -uo pipefail

VERSION="17.0.33"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.33/PATCH_v17.0.33.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot (~/public_html)." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Unduh patch..."
if ! wget -qO "${ZIP}" "${ZIP_URL}"; then
  echo "!! Gagal mengunduh patch. Docroot TIDAK diubah." >&2; exit 1
fi
if ! unzip -tq "${ZIP}" >/dev/null; then
  echo "!! Arsip patch rusak. Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1
fi

echo "==> Backup berkas yang akan ditimpa ke ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}"
COUNT=0
while IFS= read -r f; do
  case "$f" in */) continue;; esac
  COUNT=$((COUNT+1))
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done < <(unzip -Z1 "${ZIP}")
echo "    ${COUNT} berkas di paket"

echo "==> Timpa berkas..."
unzip -o -q "${ZIP}"
rm -f "${ZIP}"
chmod +x tools/reset_branch_stock.php 2>/dev/null || true

echo "==> Verifikasi marker..."
FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "tools/reset_branch_stock.php" "in_array('--apply', \$args, true)" "tool nolkan stok (dry-run default)"
check "legacy/public/admin/switch-branch.php" 'active_branch_id' "endpoint ganti cabang"
check "legacy/public/layouts/admin-sidebar.php" 'sidebar-branch' "selector cabang di sidebar"
check "legacy/public/admin/pos/index.php" 'COALESCE({$branchAlias}.stock' "stok POS per cabang"
check "legacy/public/admin/pos/index.php" 'if ($mainId <= 0 || $mainId !== $branchId) return;' "sync master hanya cabang utama"
check "legacy/public/admin/shifts/_helpers.php" '?int $branchId = null' "shift ikut cabang"
check "VERSION" '17.0.33' "VERSION 17.0.33"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo
echo "==> Nolkan stok cabang (contoh Slawi):"
echo "   php tools/reset_branch_stock.php --branch-name=Slawi          # dry-run"
echo "   php tools/reset_branch_stock.php --branch-name=Slawi --apply  # eksekusi"
echo
echo "==> Ganti cabang: selector 'Cabang aktif' di sidebar (admin/owner)."
echo "    POS, transaksi, stok & laporan otomatis mengikuti cabang itu."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"