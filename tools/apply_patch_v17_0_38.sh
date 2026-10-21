#!/usr/bin/env bash
# V17.0.38 — Ambil di Toko lewat batas: batal otomatis + stok kembali.
set -uo pipefail
VERSION="17.0.38"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.38/PATCH_v17.0.38.zip"
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
chmod +x tools/expire_pickup_orders.php 2>/dev/null || true

FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/app/Modules/Marketplace/Marketplace.php" 'function marketplace_pickup_expire_if_needed' "helper batal otomatis"
check "legacy/app/Modules/Marketplace/StorePickup.php" 'pickup_deadline_at < NOW()' "cek lewat batas via MySQL"
check "legacy/public/store/order.php" 'marketplace_pickup_expire_if_needed' "halaman pesanan memicu expire"
check "tools/expire_pickup_orders.php" 'DRY-RUN' "tool cron"
check "VERSION" '17.0.38' "VERSION 17.0.38"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi
echo
echo "==> SELESAI v${VERSION}. Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Cek dulu pesanan yang bakal dibatalkan (dry-run):"
echo "    php tools/expire_pickup_orders.php"
echo "==> Jalankan manual:"
echo "    php tools/expire_pickup_orders.php --apply"
echo "==> Cron tiap 5 menit (ganti USER):"
echo "    */5 * * * * /usr/local/bin/php /home/USER/public_html/tools/expire_pickup_orders.php --apply >> /home/USER/pickup_expire.log 2>&1"
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
