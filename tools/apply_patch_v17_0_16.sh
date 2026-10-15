#!/usr/bin/env bash
# V17.0.16 — Patch Nilai Modal Stok, Ongkir Akuntansi, Average HPP & Request Stock
# Dijalankan DARI docroot hosting (folder berisi index.php CI4).
#   wget -qO apply_patch_v17_0_16.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_16.sh
#   bash apply_patch_v17_0_16.sh
set -euo pipefail

VERSION="17.0.16"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.16/PATCH_v17.0.16.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

# --- deteksi docroot ---
if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot (folder berisi index.php)." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Backup file lama ke: ${BACKUP_DIR}/"

# --- backup file yang akan ditimpa ---
mkdir -p "${BACKUP_DIR}"
while IFS= read -r f; do
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done <<'EOF'
legacy/app/Modules/Accounting/Accounting.php
legacy/public/admin/accounting/index.php
legacy/public/admin/accounting/export.php
legacy/public/admin/purchases/_helpers.php
legacy/public/admin/purchases/create.php
legacy/public/admin/stock-requests/action.php
legacy/public/admin/stock-requests/create.php
legacy/public/admin/stock-requests/index.php
legacy/public/admin/stock-requests/detail.php
legacy/public/admin/reports/stock.php
legacy/public/assets/js/stock-request.js
legacy/app/Modules/CatalogFast.php
legacy/public/admin/system-upgrade/index.php
legacy/app/Modules/System/SystemUpgrade.php
legacy/public/admin/dashboard.php
app/Modules/Accounting/Accounting.php
app/Modules/CatalogFast.php
app/Modules/System/SystemUpgrade.php
public/admin/dashboard.php
pos/app/Modules/System/SystemUpgrade.php
pos/public/admin/dashboard.php
public/admin/accounting/index.php
public/admin/accounting/export.php
public/admin/purchases/_helpers.php
public/admin/purchases/create.php
public/admin/stock-requests/action.php
public/admin/stock-requests/create.php
public/admin/stock-requests/index.php
public/admin/stock-requests/detail.php
public/admin/reports/stock.php
public/assets/js/stock-request.js
pos/app/Modules/Accounting/Accounting.php
pos/app/Modules/CatalogFast.php
pos/public/admin/accounting/index.php
pos/public/admin/accounting/export.php
pos/public/admin/purchases/_helpers.php
pos/public/admin/purchases/create.php
pos/public/admin/stock-requests/action.php
pos/public/admin/stock-requests/create.php
pos/public/admin/stock-requests/index.php
pos/public/admin/stock-requests/detail.php
pos/public/admin/reports/stock.php
pos/public/assets/js/stock-request.js
assets/js/stock-request.js
tools/backfill_v17_0_16.php
VERSION
EOF

# --- unduh patch (overwrite PAKSa - unzip -o) ---
echo "==> Unduh patch..."
wget -qO "${ZIP}" "${ZIP_URL}"
unzip -o "${ZIP}"
rm -f "${ZIP}"

# --- verifikasi file kunci: ada, tidak kosong, dan marker benar ---
echo "==> Verifikasi file kunci..."
OK=1
for f in \
  legacy/app/Modules/Accounting/Accounting.php \
  legacy/public/admin/accounting/index.php \
  legacy/public/admin/accounting/export.php \
  legacy/public/admin/purchases/_helpers.php \
  legacy/public/admin/purchases/create.php \
  legacy/public/admin/stock-requests/action.php \
  legacy/public/admin/stock-requests/create.php \
  legacy/public/admin/stock-requests/index.php \
  legacy/public/admin/stock-requests/detail.php \
  legacy/public/admin/reports/stock.php \
  legacy/public/admin/dashboard.php \
  legacy/public/assets/js/stock-request.js \
  legacy/app/Modules/CatalogFast.php \
  legacy/app/Modules/System/SystemUpgrade.php \
  app/Modules/Accounting/Accounting.php \
  app/Modules/CatalogFast.php \
  app/Modules/System/SystemUpgrade.php \
  public/admin/dashboard.php \
  pos/app/Modules/System/SystemUpgrade.php \
  pos/public/admin/dashboard.php \
  tools/backfill_v17_0_16.php ; do
  if [ ! -f "$f" ]; then echo "  [MISSING] $f"; OK=0; continue; fi
  if [ ! -s "$f" ]; then echo "  [KOSONG] $f"; OK=0; continue; fi
  echo "  [OK] $f"
done
[ $OK -eq 1 ] && echo "==> Semua file patch lengkap." || { echo "!! Ada file patch tidak lengkap — jangan lanjut sebelum dicek."; exit 1; }

# --- verifikasi marker source (ongkir terpisah harus ada) ---
if ! grep -q "shipping_income" legacy/app/Modules/Accounting/Accounting.php; then
  echo "!! Marker shipping_income tidak ditemukan — patch tidak terpasang benar."; exit 1
fi

# --- jalankan backfill verifikasi (aman, idempotent) ---
if [ -f "tools/backfill_v17_0_16.php" ]; then
  echo "==> Jalankan backfill verifikasi..."
  php tools/backfill_v17_0_16.php
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> Buka halaman: /admin/system-upgrade/index.php lalu klik 'Jalankan Upgrade Otomatis'"
echo "==> Jika perlu rollback: restore dari folder ${BACKUP_DIR}/"