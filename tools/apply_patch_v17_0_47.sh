#!/usr/bin/env bash
# V17.0.47 — fix Edit tarif kurir toko + laba bersih minus ongkir + kartu Biaya Operasional.
set -uo pipefail
VERSION="17.0.47"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.47/PATCH_v17.0.47.zip"
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
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/public/admin/marketplace-courier-rates/index.php" 'window.fillEdit=fillEdit' "tombol Edit berfungsi"
check "legacy/public/admin/marketplace-courier-rates/index.php" 'const p2=document.getElementById' "proxy elemen select"
check "legacy/app/Modules/Accounting/Accounting.php" "['operating_expense'] - \$branch['shipping_income']" "laba minus ongkir"
check "legacy/public/admin/accounting/index.php" 'Biaya Operasional' "kartu Biaya Operasional"
check "VERSION" '17.0.47' "VERSION 17.0.47"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Marker tidak cocok. Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI v${VERSION}. ZIP sudah diextract & dihapus."
echo "==> Cek laba bersih di menu Akuntansi (ongkir kini dikurangi); cek tombol Edit tarif kurir."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
