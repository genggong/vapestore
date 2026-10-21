#!/usr/bin/env bash
# V17.0.35 — Ambil di Toko: batas pengambilan maksimal hari ini.
set -uo pipefail

VERSION="17.0.35"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.35/PATCH_v17.0.35.zip"
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
check "legacy/app/Modules/Marketplace/StorePickup.php" 'function marketplace_store_pickup_deadline' "helper batas ambil"
check "legacy/app/Modules/Marketplace/StorePickup.php" 'pickup_deadline_at' "kolom pickup_deadline_at"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'pickup_deadline_at=COALESCE' "order mengisi batas ambil"
check "legacy/public/store/order.php" 'Batas Ambil' "batas ambil di pesanan"
check "legacy/public/admin/marketplace-orders/detail.php" 'Batas ambil' "batas ambil di admin"
check "legacy/public/admin/marketplace-settings/index.php" 'marketplace_store_pickup_max_days' "pengaturan batas"
check "VERSION" '17.0.35' "VERSION 17.0.35"

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
echo "==> Uji cepat:"
echo "   1. Checkout -> Ambil di Toko -> muncul 'Ambil maksimal hari ini sebelum <jam tutup>'."
echo "   2. Buat pesanan pickup -> halaman pesanan menampilkan 'Batas Ambil: Hari ini, maks <jam>'."
echo "   3. Admin -> detail pesanan -> baris 'Batas ambil' (merah bila lewat)."
echo "   4. Pengaturan Marketplace -> 'Batas Pengambilan' (default Hari ini)."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"