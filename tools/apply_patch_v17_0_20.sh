#!/usr/bin/env bash
# V17.0.20 — Cache-bust CSS storefront (agar palet hitam-putih v17.0.19 langsung sampai
#            ke SEMUA pengunjung, bukan hanya yang menekan Ctrl+F5).
# Dijalankan DARI docroot hosting (folder berisi index.php CI4).
# Urutan: apply v17.0.19 dulu (palet + tampilan request stock), lalu script ini.
set -uo pipefail

VERSION="17.0.20"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.20/PATCH_v17.0.20.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Backup file lama ke: ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}"
for f in legacy/public/store/_header.php public/store/_header.php pos/public/store/_header.php VERSION \
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
for f in legacy/public/store/_header.php public/store/_header.php pos/public/store/_header.php; do
  [ -s "$f" ] || { echo "!! $f kosong/hilang."; exit 1; }
  grep -q "storefront.css?v=17.0.20" "$f" \
    || { echo "!! $f belum memakai versi cache-bust baru."; exit 1; }
  if grep -qE "storefront\.css\?v=15\.|marketplace-theme\.css\?v=15\." "$f"; then
    echo "!! $f masih menyimpan versi CSS lama."; exit 1
  fi
  echo "  [OK] $f"
done

# peringatan bila palet v17.0.19 belum terpasang
if ! grep -q -- "--purple:#111111" legacy/public/assets/css/storefront.css 2>/dev/null; then
  echo "!! PERINGATAN: storefront.css belum monokrom — jalankan dulu apply_patch_v17_0_19.sh."
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> Purge All (LiteSpeed Web Cache Manager) lalu buka situs. Pengunjung lain otomatis dapat CSS baru (versi query berubah)."
echo "==> Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"