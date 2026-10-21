#!/usr/bin/env bash
# V17.0.34 — COD (Bayar di Tempat) untuk Ambil di Toko / Kurir Toko + WA kurir saat ditugaskan.
set -uo pipefail

VERSION="17.0.34"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.34/PATCH_v17.0.34.zip"
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
check "legacy/app/Modules/Marketplace/Marketplace.php" 'function marketplace_cod_allowed' "helper COD"
check "legacy/app/Modules/Marketplace/StoreCourier.php" 'marketplace_notify_store_courier' "WA kurir saat ditugaskan"
check "legacy/public/store/checkout.php" 'payment-method-cod' "kartu COD di checkout"
check "legacy/public/store/order.php" 'Bayar di Tempat (COD)' "instruksi COD pelanggan"
check "legacy/public/admin/marketplace-orders/action.php" 'cod_paid' "aksi Tandai Lunas COD"
check "legacy/public/admin/marketplace-orders/detail.php" 'Tandai Lunas (COD)' "tombol COD di detail"
check "legacy/public/admin/marketplace-settings/index.php" 'marketplace_cod_enabled' "pengaturan COD"
check "assets/js/marketplace-checkout.js" 'syncCodOption' "JS toggle COD"
check "VERSION" '17.0.34' "VERSION 17.0.34"

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
echo "   1. Checkout -> pilih Ambil di Toko / Kurir Toko -> muncul 'Bayar di Tempat (COD)'."
echo "   2. Buat pesanan COD -> halaman pesanan menampilkan tagihan tunai + kode ambil."
echo "   3. Admin -> detail pesanan -> tombol 'Tandai Lunas (COD)'."
echo "   4. Tugaskan kurir toko -> kurir menerima WA (cek timeline pesanan)."
echo "   5. Pengaturan Marketplace -> 'Bayar di Tempat (COD)' bisa dinonaktifkan."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"