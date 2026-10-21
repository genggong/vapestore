#!/usr/bin/env bash
# V17.0.39 FULL — paket kumulatif 17.0.35 s.d. 17.0.39 (COD, batal 10 menit, batas ambil, poin 100).
set -uo pipefail
VERSION="17.0.39"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.39/PATCH_v17.0.39_FULL.zip"
ZIP="PATCH_${VERSION}_FULL.zip"
BACKUP_DIR="backup_${VERSION}_FULL_$(date +%Y%m%d_%H%M%S)"

[ -f index.php ] || { echo "ERROR: jalankan dari docroot (~/public_html)." >&2; exit 1; }
echo "==> Docroot: $(pwd)"
echo "==> Versi terpasang sekarang: $(cat VERSION 2>/dev/null || echo 'tidak diketahui')"
wget -qO "${ZIP}" "${ZIP_URL}" || { echo "!! Gagal unduh paket. Docroot TIDAK diubah." >&2; exit 1; }
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
chmod +x tools/expire_pickup_orders.php 2>/dev/null || true

FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "VERSION" '17.0.39' "VERSION 17.0.39"
check "legacy/public/store/checkout.php" 'data-cod-hint' "COD di samping QRIS"
check "legacy/public/store/checkout.php" 'data-point-max' "batas poin 100"
check "legacy/public/store/order.php" 'cod-prepare-banner' "banner siapkan uang"
check "legacy/public/store/order.php" 'cancel-order.php' "batal 10 menit"
check "legacy/public/store/order.php" 'marketplace_pickup_expire_if_needed' "expire batas ambil"
check "legacy/app/Modules/Marketplace/MarketplaceV15.php" 'member_point_max_redeem' "clamp poin server"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'marketplace_v15_points_ensure($db)' "tabel poin di luar transaksi"
check "legacy/app/Modules/Marketplace/StorePickup.php" 'pickup_deadline_at < NOW()' "batas ambil via MySQL"
check "tools/expire_pickup_orders.php" 'DRY-RUN' "tool cron"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Ada marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi
echo
echo "==> SELESAI v${VERSION}. Isi: COD di samping QRIS, notif siapkan uang, batal 10 menit,"
echo "    batas ambil hari ini + batal otomatis, dan tukar poin maksimal 100 poin (Rp10.000)."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
