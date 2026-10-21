#!/usr/bin/env bash
# V17.0.25 — fix fatal avg_hpp() saat simpan pembelian, tambah/hapus role (master role),
#            permission Laporan Ongkir Toko untuk role mana pun.
set -uo pipefail

VERSION="17.0.25"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.25/PATCH_v17.0.25.zip"
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
check "legacy/public/admin/purchases/create.php" "function_exists('avg_hpp')" "pengaman avg_hpp() di halaman pembelian"
check "legacy/public/admin/purchases/_helpers.php" 'function avg_hpp(float $oldStock' "helper avg_hpp terkirim"
check "legacy/app/Core/Auth.php" 'function requirePermissionOrRole' "guard role-atau-permission"
check "legacy/app/Helpers/helpers.php" 'function redirect(string $path)' "alias redirect() untuk tree legacy"
check "legacy/public/admin/roles/create.php" 'INSERT IGNORE INTO role_permissions' "halaman Tambah Role"
check "legacy/public/admin/roles/delete.php" 'FROM users WHERE role_id' "halaman Hapus Role (proteksi user)"
check "legacy/public/admin/roles/index.php" 'admin/roles/delete.php' "tombol Hapus di daftar role"
check "legacy/public/admin/roles/_helpers.php" "courier_income.view" "permission Laporan Ongkir Toko"
check "legacy/public/admin/marketplace-courier-income/index.php" 'courier_income.view' "laporan ongkir pakai permission"
check "VERSION" '17.0.25' "VERSION 17.0.25"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Berkas sudah ter-unzip, jadi rollback bila perlu:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: cPanel -> LiteSpeed Web Cache Manager -> Purge All."
echo
echo "==> Cek cepat:"
echo "    1. Pembelian: simpan 1 produk/varian -> tidak ada lagi 'undefined function avg_hpp'."
echo "    2. Role: /admin/roles/index.php -> Tambah Role (mis. Master Kurir) + tombol Hapus."
echo "    3. Atur Akses role itu -> centang 'Laporan Ongkir Toko' -> login sebagai user role tsb."
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"