#!/usr/bin/env bash
# V17.0.39 — tukar poin member maksimal 100 poin (Rp10.000) + fix tabel poin di dalam transaksi.
set -uo pipefail
VERSION="17.0.39"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.39/PATCH_v17.0.39.zip"
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
check "legacy/app/Modules/Marketplace/MarketplaceV15.php" 'member_point_max_redeem' "batas 100 poin di server"
check "legacy/app/Modules/Marketplace/MarketplaceV15.php" 'marketplace_v15_points_ensure' "tabel poin disiapkan di luar transaksi"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'marketplace_v15_points_ensure($db)' "checkout menyiapkan tabel poin"
check "legacy/public/store/checkout.php" 'data-point-max' "batas poin di checkout"
check "legacy/public/assets/js/marketplace-checkout.js" 'pointMax' "batas poin di JS"
check "VERSION" '17.0.39' "VERSION 17.0.39"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi
echo
echo "==> SELESAI v${VERSION}. Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Uji: checkout sebagai member -> 'Tukar Poin' maksimal 100 poin (Rp10.000)."
echo "==> Ubah batas (opsional, default 100):"
echo "    mysql -e \"INSERT INTO settings(setting_key,setting_value) VALUES('member_point_max_redeem','100') ON DUPLICATE KEY UPDATE setting_value='100'\" DBNAME"
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
