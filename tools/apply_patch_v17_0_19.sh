#!/usr/bin/env bash
# V17.0.19 — Palet storefront HITAM PUTIH (biru/ungu -> hitam) + pemulihan tampilan
#            "Buat Request Stock" (daftar produk + stok gudang) di semua tree.
# Dijalankan DARI docroot hosting (folder berisi index.php CI4).
#   wget -qO apply_patch_v17_0_19.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_19.sh
#   bash apply_patch_v17_0_19.sh
set -uo pipefail

VERSION="17.0.19"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.19/PATCH_v17.0.19.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot (folder berisi index.php)." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Backup file lama ke: ${BACKUP_DIR}/"

# Daftar file = isi zip (semua salinan tree ikut supaya tidak ada generasi bercampur)
FILES="
VERSION
assets/css/storefront.css
assets/css/marketplace-theme.css
assets/css/warehouse.css
assets/js/stock-request.js
legacy/public/assets/css/storefront.css
legacy/public/assets/css/marketplace-theme.css
legacy/public/assets/css/warehouse.css
legacy/public/assets/js/stock-request.js
public/assets/css/storefront.css
public/assets/css/marketplace-theme.css
public/assets/css/warehouse.css
public/assets/js/stock-request.js
pos/public/assets/css/storefront.css
pos/public/assets/css/marketplace-theme.css
pos/public/assets/css/warehouse.css
pos/public/assets/js/stock-request.js
app/Modules/CatalogFast.php
app/Modules/Marketplace/Marketplace.php
app/Modules/System/SystemUpgrade.php
legacy/app/Modules/CatalogFast.php
legacy/app/Modules/Marketplace/Marketplace.php
legacy/app/Modules/System/SystemUpgrade.php
pos/app/Modules/CatalogFast.php
pos/app/Modules/Marketplace/Marketplace.php
pos/app/Modules/System/SystemUpgrade.php
legacy/public/admin/stock-requests/create.php
legacy/public/admin/stock-requests/index.php
legacy/public/admin/stock-requests/detail.php
legacy/public/admin/stock-requests/action.php
legacy/public/admin/system-upgrade/index.php
public/admin/stock-requests/create.php
public/admin/stock-requests/index.php
public/admin/stock-requests/detail.php
public/admin/stock-requests/action.php
pos/public/admin/stock-requests/create.php
pos/public/admin/stock-requests/index.php
pos/public/admin/stock-requests/detail.php
pos/public/admin/stock-requests/action.php
"

mkdir -p "${BACKUP_DIR}"
n=0
for f in $FILES; do
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null && n=$((n+1)) || true
done
echo "    file lama tersimpan: ${n}"

echo "==> Unduh patch..."
if ! wget -qO "${ZIP}" "${ZIP_URL}"; then
  echo "!! Gagal mengunduh patch. Docroot TIDAK diubah." >&2; exit 1
fi
if ! unzip -tq "${ZIP}" >/dev/null; then
  echo "!! Arsip patch rusak (unzip -t gagal). Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1
fi
unzip -o "${ZIP}"
rm -f "${ZIP}"

echo "==> Verifikasi file kunci..."
OK=1
for f in legacy/public/assets/css/storefront.css legacy/public/assets/js/stock-request.js \
         legacy/public/admin/stock-requests/create.php legacy/app/Modules/CatalogFast.php \
         legacy/app/Modules/Marketplace/Marketplace.php tools/set_theme_mono.php assets/css/storefront.css ; do
  if [ ! -s "$f" ]; then echo "  [MISSING/KOSONG] $f"; OK=0; else echo "  [OK] $f"; fi
done
[ $OK -eq 1 ] || { echo "!! File patch tidak lengkap."; echo "    Catatan: file yang berhasil di-unzip SUDAH tertimpa."; exit 1; }

echo "==> Verifikasi marker..."
grep -q -- "--purple:#111111" legacy/public/assets/css/storefront.css \
  || { echo "!! Palet monokrom belum terpasang (storefront.css)."; exit 1; }
if grep -qiE "#(7c3aed|6d28d9|5b21b6|a855f7|8b5cf6|2563eb|4f46e5)" legacy/public/assets/css/storefront.css; then
  echo "!! Masih ada warna biru/ungu di storefront.css."; exit 1
fi
grep -q "monochrome' =>" legacy/app/Modules/Marketplace/Marketplace.php \
  || { echo "!! Preset 'monochrome' tidak ada di Marketplace.php."; exit 1; }
grep -q "data-product-group" legacy/public/admin/stock-requests/create.php \
  || { echo "!! Markup daftar produk request stock hilang."; exit 1; }
grep -q "syncListStock" legacy/public/assets/js/stock-request.js \
  || { echo "!! JS request stock belum versi terbaru."; exit 1; }
echo "  [OK] palet monokrom + preset + tampilan request stock terpasang"

echo "==> Set tema marketplace ke Hitam Putih (preset monochrome)..."
if [ -f tools/set_theme_mono.php ]; then
  php tools/set_theme_mono.php || echo "!! Gagal set tema — jalankan manual: php tools/set_theme_mono.php"
else
  echo "    (tools/set_theme_mono.php tidak ada — set manual di Pengaturan Marketplace)"
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: LiteSpeed Cache Manager -> Purge All, lalu buka situs + Ctrl+F5 (aset CSS lama masih di-cache browser)."
echo "==> Cek: / dan /produk (hitam-putih), /admin/stock-requests/create.php (daftar produk + stok gudang muncul)."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done  lalu php tools/set_theme_mono.php --revert"