#!/usr/bin/env bash
# V17.0.28 — angka "Pesanan Selesai" dari data nyata + perbaikan storefront_table_exists().
set -uo pipefail

VERSION="17.0.28"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.28/PATCH_v17.0.28.zip"
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
check "legacy/public/store/_data.php" 'information_schema.TABLES' "helper table_exists pakai information_schema"
check "legacy/public/store/_data.php" "orders_online'\] + \$stats\['orders_pos'\]" "angka pesanan = web + kasir"
check "legacy/public/store/index.php" 'Pesanan Selesai' "label kartu Pesanan Selesai"
check "public/store/_data.php" 'information_schema.TABLES' "helper (root)"
check "pos/public/store/_data.php" 'information_schema.TABLES' "helper (pos)"
check "VERSION" '17.0.28' "VERSION 17.0.28"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5 di beranda."
echo
echo "==> Cek cepat: kartu beranda harus menampilkan jumlah pesanan nyata (bukan 0+),"
echo "    dan /tracking?code=<nomor pesanan/invoice> harus menemukan pesanan."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"