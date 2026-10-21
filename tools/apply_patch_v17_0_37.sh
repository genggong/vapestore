#!/usr/bin/env bash
# V17.0.37 — COD di samping QRIS + notif siapkan uang + batal pesanan 10 menit (semua metode).
set -uo pipefail
VERSION="17.0.37"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.37/PATCH_v17.0.37.zip"
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
check "legacy/public/store/checkout.php" 'data-cod-hint' "kartu COD di samping QRIS"
check "legacy/public/assets/js/marketplace-checkout.js" 'kartu COD selalu tampil' "JS COD tidak menyembunyikan"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'function marketplace_customer_cancel_order' "helper batal pesanan"
check "legacy/public/store/order.php" 'cancel-order.php' "tombol batal di halaman pesanan"
check "legacy/public/store/order.php" 'cod-prepare-banner' "banner siapkan uang"
check "legacy/public/store/cancel-order.php" 'marketplace_customer_cancel_order' "endpoint batal"
check "legacy/public/assets/css/storefront.css" '.cancel-window-box' "CSS batal"
check "VERSION" '17.0.37' "VERSION 17.0.37"

if [ $FAIL -ne 0 ]; then
  echo; echo "!! Marker tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi
echo
echo "==> SELESAI v${VERSION}. Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Uji:"
echo "   1. /checkout -> kartu 'Bayar di Tempat (COD)' ada tepat setelah QRIS (redup bila ekspedisi)."
echo "   2. Checkout COD -> halaman pesanan menampilkan 'Siapkan uang tunai Rp ...'."
echo "   3. Halaman pesanan -> kotak 'Bisa dibatalkan sendiri' + hitung mundur, tombol Batalkan Pesanan."
echo "   4. Tunggu 10 menit -> tombol berubah 'Pembatalan mandiri sudah ditutup'."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Backup: ${BACKUP_DIR}"
