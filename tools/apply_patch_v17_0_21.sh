#!/usr/bin/env bash
# V17.0.21 — HOTFIX: daftar produk di "Buat Request Stock" terlihat kosong (hanya garis tipis).
# Penyebab: .request-product-list = flex-column + max-height; grup produk punya overflow:hidden
# sehingga min-height:auto = 0 -> grup menyusut jadi ~2px di dalam flex container dan isinya
# terpotong. Perbaikan: grup/anak daftar dipaksa flex:0 0 auto (tidak menyusut).
set -uo pipefail

VERSION="17.0.21"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.21/PATCH_v17.0.21.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Backup file lama ke: ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}"
for f in assets/css/warehouse.css legacy/public/assets/css/warehouse.css public/assets/css/warehouse.css \
         pos/public/assets/css/warehouse.css \
         legacy/public/admin/stock-requests/create.php public/admin/stock-requests/create.php \
         pos/public/admin/stock-requests/create.php VERSION \
         app/Modules/System/SystemUpgrade.php legacy/app/Modules/System/SystemUpgrade.php \
         pos/app/Modules/System/SystemUpgrade.php legacy/public/admin/system-upgrade/index.php ; do
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done

echo "==> Unduh patch..."
if ! wget -qO "${ZIP}" "${ZIP_URL}"; then
  echo "!! Gagal mengunduh patch. Docroot TIDAK diubah." >&2; exit 1
fi
if ! unzip -tq "${ZIP}" >/dev/null; then
  echo "!! Arsip patch rusak. Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1
fi
unzip -o "${ZIP}"
rm -f "${ZIP}"

echo "==> Verifikasi marker..."
OK=1
for f in assets/css/warehouse.css legacy/public/assets/css/warehouse.css \
         legacy/public/admin/stock-requests/create.php ; do
  [ -s "$f" ] || { echo "  [MISSING] $f"; OK=0; continue; }
  echo "  [OK] $f"
done
[ $OK -eq 1 ] || { echo "!! File patch tidak lengkap (file yang sudah ter-unzip tetap tertimpa)."; exit 1; }

grep -q '.request-product-list>\*{flex:0 0 auto}' assets/css/warehouse.css \
  || { echo "!! Guard flex pada daftar produk tidak ditemukan di warehouse.css."; exit 1; }
grep -q 'flex:0 0 auto' legacy/public/admin/stock-requests/create.php \
  || { echo "!! Aturan grup di create.php belum diperbaiki."; exit 1; }
echo "  [OK] guard flex terpasang (daftar produk tidak lagi menyusut)"

cat <<'NOTE'

==> SELESAI. Patch v17.0.21 terpasang.
==> Purge All (LiteSpeed Web Cache Manager), lalu buka:
      /admin/stock-requests/create.php   -> daftar produk muncul (751 grup / 936 baris)
==> CSS-nya di-cache browser; kalau masih kosong, Ctrl+F5 sekali.
==> Rollback: for b in $(find backup_17.0.21_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
NOTE
echo "==> Folder backup: ${BACKUP_DIR}"