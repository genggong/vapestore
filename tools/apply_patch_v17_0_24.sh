#!/usr/bin/env bash
# V17.0.24 — Foto kategori (storefront), HPP rata-rata pembelian (fix varian), request stok tanpa blokir stok gudang.
set -uo pipefail

VERSION="17.0.24"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.24/PATCH_v17.0.24.zip"
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

# Daftar berkas backup diambil dari isi ZIP (bukan daftar manual yang bisa melenceng)
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
echo "    ${COUNT} berkas di paket (yang sudah ada dibackup)"

echo "==> Timpa berkas..."
unzip -o -q "${ZIP}"
rm -f "${ZIP}"

echo "==> Verifikasi marker..."
FAIL=0
check() { if [ -f "$1" ] && grep -q "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/public/admin/categories/create.php" 'name="image"' "form Kategori (foto) create"
check "legacy/public/admin/categories/edit.php" 'remove_image' "form Kategori (foto) edit + hapus foto"
check "legacy/public/admin/categories/_helpers.php" 'ADD COLUMN image VARCHAR(255)' "helper kolom categories.image"
check "legacy/public/store/_data.php" 'SELECT image FROM categories' "storefront baca foto kategori"
check "legacy/public/admin/purchases/create.php" 'V17.0.24: pembelian varian' "pembelian: modal induk tidak ditimpa varian"
check "legacy/public/admin/stock-requests/create.php" 'shortages\[\]' "request stok tidak diblok stok gudang"
check "legacy/app/Modules/System/SystemUpgrade.php" "categories','image'" "upgrader: kolom categories.image"
check "VERSION" '17.0.24' "VERSION 17.0.24"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Berkas sudah ter-unzip (ditimpa), jadi kemungkinan besar"
  echo "   hosting memakai generasi berkas yang berbeda. Jangan panik: rollback ada di ${BACKUP_DIR}."
  echo "   Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: cPanel -> LiteSpeed Web Cache Manager -> Purge All."
echo "==> Opsional (disarankan): /admin/system-upgrade/index.php -> Jalankan Upgrade Otomatis"
echo "    (menambah kolom categories.image permanen di database; halaman Kategori juga sudah"
echo "     membuatnya otomatis saat dibuka)."
echo
echo "==> Cek cepat:"
echo "    1. Kategori -> Edit -> pilih Foto Kategori -> Update -> buka homepage toko (Ctrl+F5)."
echo "    2. Request Stock dengan stok gudang 0 -> harus berhasil + peringatan kuning."
echo "    3. Pembelian varian dengan harga beda -> modal varian jadi rata-rata, modal induk tetap."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"