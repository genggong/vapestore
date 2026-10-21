#!/usr/bin/env bash
# V17.0.27 — teks kartu "Poin Member" jadi putih + cache-buster CSS storefront ikut versi rilis.
set -uo pipefail

VERSION="17.0.27"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.27/PATCH_v17.0.27.zip"
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
check "legacy/public/assets/css/marketplace-theme.css" 'account-points span,.member-points-card span{color:#fff' "label Poin Member putih (legacy)"
check "public/assets/css/marketplace-theme.css" 'account-points span,.member-points-card span{color:#fff' "label Poin Member putih (root)"
check "pos/public/assets/css/marketplace-theme.css" 'account-points span,.member-points-card span{color:#fff' "label Poin Member putih (pos)"
check "assets/css/marketplace-theme.css" 'account-points span,.member-points-card span{color:#fff' "label Poin Member putih (assets)"
check "legacy/public/store/_header.php" "APP_ASSET_VERSION" "cache-buster CSS ikut versi rilis"
check "VERSION" '17.0.27' "VERSION 17.0.27"

if [ $FAIL -ne 0 ]; then
  echo
  echo "!! Ada marker yang tidak cocok. Rollback:"
  echo "   for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
  exit 1
fi

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> WAJIB: LiteSpeed Web Cache Manager -> Purge All, lalu buka /akun dengan Ctrl+F5."
echo "    (CSS berubah, jadi browser harus ambil versi baru sekali.)"
echo
echo "==> Rollback berkas: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"