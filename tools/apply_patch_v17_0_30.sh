#!/usr/bin/env bash
# V17.0.30 — sort Terlaris + stok habis di bawah, notifikasi WA pesanan ke cabang.
set -uo pipefail

VERSION="17.0.30"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.30/PATCH_v17.0.30.zip"
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

echo "==> Verifikasi marker..."
FAIL=0
check() { if [ -f "$1" ] && grep -q -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/public/store/_data.php" 'function storefront_sold_expr' "ekspresi terjual (POS + web)"
check "legacy/public/store/_data.php" 'outOfStockLast' "stok habis di bawah semua urutan"
check "legacy/public/store/products.php" 'value="best"' "opsi sort Terlaris"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'marketplace_order_notify_queue' "antrean notifikasi pesanan"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'marketplace_wa_send_text(' "pengiriman via WA Marketplace"
check "legacy/public/admin/marketplace-settings/index.php" 'marketplace_order_wa_enabled' "pengaturan notifikasi WA"
check "tools/send_pending_order_notifications.php" 'retry' "tool kirim ulang"
check "VERSION" '17.0.30' "VERSION 17.0.30"

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
echo "==> Cek cepat:"
echo "   1. /produk -> opsi urutan 'Terlaris'; produk habis selalu di halaman terakhir."
echo "   2. Pengaturan Marketplace -> WhatsApp Marketplace -> 'Notifikasi Pesanan Masuk' = Aktif."
echo "   3. Buat 1 pesanan uji -> WA nomor cabang harus menerima rincian pesanan."
echo "      Kalau belum masuk: php tools/send_pending_order_notifications.php"
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"