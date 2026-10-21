#!/usr/bin/env bash
# V17.0.29 — tarif kurir toko per cabang, pengiriman multi-cabang, stok katalog gabungan.
set -uo pipefail

VERSION="17.0.29"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.29/PATCH_v17.0.29.zip"
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
check() { if [ -f "$1" ] && grep -q "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/app/Modules/Marketplace/MarketplaceV16.php" 'uniq_mp_store_courier_dest_branch' "index tarif per cabang"
check "legacy/app/Modules/Marketplace/MarketplaceV16.php" 'int $branchId=0): ?array' "lookup tarif menerima cabang"
check "legacy/app/Modules/Marketplace/Marketplace.php" 'function marketplace_delivery_branch' "pemilih cabang pengirim"
check "legacy/public/store/_data.php" 'SUM(pbs_all.stock)' "stok katalog gabungan semua cabang"
check "legacy/public/store/api/shipping-cost.php" 'marketplace_delivery_branch' "ongkir pakai cabang pengirim"
check "legacy/public/admin/marketplace-courier-rates/index.php" 'name="branch_id"' "form tarif pilih cabang"
check "VERSION" '17.0.29' "VERSION 17.0.29"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> Migrasi kolom branch_id berjalan otomatis saat halaman pertama dibuka."
echo "==> WAJIB: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo
echo "==> Cek cepat:"
echo "   1. POS -> Tarif Kurir Toko: ada pilihan 'Cabang Pengirim' dan kolom 'Cabang'."
echo "   2. Buka /produk: stok = gabungan semua cabang aktif."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"