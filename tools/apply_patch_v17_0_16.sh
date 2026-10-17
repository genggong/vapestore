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

# --- unduh patch DULU, lalu backup diambil dari ISI ZIP (bukan daftar manual) ---
echo "==> Unduh patch..."
wget -qO "${ZIP}" "${ZIP_URL}" || { echo "ERROR: gagal unduh ${ZIP_URL}"; exit 1; }
unzip -t -q "${ZIP}" || { echo "ERROR: zip patch rusak — tidak ada file yang diubah"; rm -f "${ZIP}"; exit 1; }

echo "==> Backup file lama ke: ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}"
N_BACKUP=0; N_BARU=0; N_SKIP=0
while IFS= read -r f; do
  case "$f" in ""|*/) continue;; esac
  if [ -f "$f" ]; then
    mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
    cp -p "$f" "${BACKUP_DIR}/${f}.bak" && N_BACKUP=$((N_BACKUP+1)) || N_SKIP=$((N_SKIP+1))
  else
    N_BARU=$((N_BARU+1))   # file baru (belum ada di server) -> tidak ada yang perlu di-backup
  fi
done < <(unzip -Z1 "${ZIP}")
echo "    ${N_BACKUP} file lama di-backup, ${N_BARU} file baru, ${N_SKIP} gagal. Tiap file yang DITIMPA punya .bak."
tar czf "${BACKUP_DIR}/full_docroot.tar.gz" --exclude='uploads' --exclude='backup_*' --exclude='_backup_stray_*' . 2>/dev/null \
  && echo "    cadangan penuh: ${BACKUP_DIR}/full_docroot.tar.gz" || echo "    (cadangan penuh dilewati)"

# --- timpa (overwrite pakai unzip -o) ---
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
  legacy/public/admin/branches/_helpers.php \
  legacy/public/admin/warehouse/_helpers.php \
  legacy/public/assets/js/stock-request.js \
  legacy/app/Modules/CatalogFast.php \
  legacy/app/Modules/System/SystemUpgrade.php \
  app/Modules/Accounting/Accounting.php \
  app/Modules/CatalogFast.php \
  app/Modules/System/SystemUpgrade.php \
  public/admin/dashboard.php \
  public/admin/branches/_helpers.php \
  public/admin/warehouse/_helpers.php \
  pos/app/Modules/System/SystemUpgrade.php \
  pos/public/admin/dashboard.php \
  pos/public/admin/branches/_helpers.php \
  pos/public/admin/warehouse/_helpers.php \
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
# V17.0.16c: guard sync stok harus ada (kalau tidak, loading admin tetap berat)
if ! grep -q "branch_sync_all_product_stocks" legacy/public/admin/branches/_helpers.php; then
  echo "!! Marker guard sync branch tidak ditemukan — patch tidak terpasang benar."; exit 1
fi
if ! grep -q 'haveWs < $needWs' legacy/public/admin/warehouse/_helpers.php; then
  echo "!! Marker guard sync warehouse tidak ditemukan — patch tidak terpasang benar."; exit 1
fi
# V17.0.16c aset: helper cache-bust + layout harus tanpa ?v=time() (browser cache aset)
if ! grep -q "APP_ASSET_VERSION" legacy/public/layouts/admin-header.php; then
  echo "!! Marker cache-bust aset tidak ditemukan — patch JS belum terpasang."; exit 1
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