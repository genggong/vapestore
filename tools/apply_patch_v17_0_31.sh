#!/usr/bin/env bash
# V17.0.31 — notifikasi pesanan lewat Fonnte + varian di struk & invoice WA.
set -uo pipefail

VERSION="17.0.31"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.31/PATCH_v17.0.31.zip"
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
check "legacy/app/Modules/Marketplace/Marketplace.php" 'wa_send_fonnte(' "notifikasi via Fonnte"
check "legacy/public/admin/transactions/print.php" 'Varian: <?= e($it' "varian di struk POS"
check "legacy/public/admin/whatsapp/_helpers.php" "variant_name" "varian di invoice WA"
check "legacy/public/admin/marketplace-settings/index.php" 'Notifikasi Pesanan Masuk (Fonnte)' "label pengaturan"
check "VERSION" '17.0.31' "VERSION 17.0.31"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

if grep -q "marketplace_wa_send_text(" legacy/app/Modules/Marketplace/Marketplace.php; then
  echo "  [i] Catatan: file Marketplace.php versi ini masih memuat fungsi gateway marketplace untuk event"
  echo "      customer (bukan notifikasi pesanan baru)."
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo
echo "==> Cek cepat:"
echo "   1. Cetak struk transaksi bervarian -> ada baris 'Varian: ...'."
echo "   2. Kirim invoice WA -> item tertulis 'nama produk - varian'."
echo "   3. Pesanan uji di website -> WA cabang masuk lewat Fonnte."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"