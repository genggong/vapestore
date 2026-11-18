#!/bin/bash
# RESTORE ke v17.0.74 (rollback semua perubahan 75..79). Aman: backup dulu.
set -o errexit -o nounset -o pipefail
B="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$B"
case "$PWD" in *public_html) echo "OK docroot: $PWD";; *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1;; esac
FILES=(
  app/Modules/Marketplace/Marketplace.php
  assets/css/storefront.css
  legacy/app/Modules/Marketplace/Marketplace.php
  legacy/public/admin/casaku/status.php
  legacy/public/admin/customers/index.php
  legacy/public/admin/settings/index.php
  legacy/public/assets/css/storefront.css
  legacy/public/store/_header.php
  legacy/public/store/api/resolve-destination.php
  pos/app/Modules/Marketplace/Marketplace.php
  pos/public/admin/casaku/status.php
  pos/public/admin/customers/index.php
  pos/public/admin/settings/index.php
  pos/public/assets/css/storefront.css
  pos/public/store/api/resolve-destination.php
  public/admin/casaku/status.php
  public/admin/customers/index.php
  public/admin/settings/index.php
  public/assets/css/storefront.css
  public/store/api/resolve-destination.php
)
if [ -e public/store/checkout.php ]; then FILES+=(public/store/checkout.php legacy/public/store/checkout.php pos/public/store/checkout.php); fi
if [ -e assets/js/marketplace-checkout.js ]; then FILES+=(assets/js/marketplace-checkout.js public/assets/js/marketplace-checkout.js legacy/public/assets/js/marketplace-checkout.js pos/public/assets/js/marketplace-checkout.js); fi
TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup state sekarang -> ~/backup_pre_restore74_$TS.tar.gz"
EXIST=(); for p in "${FILES[@]}"; do [ -e "$p" ] && EXIST+=("$p"); done
[ "${#EXIST[@]}" -gt 0 ] || { echo "!! tidak ada file, henti"; exit 2; }
tar -czf "$HOME/backup_pre_restore74_$TS.tar.gz" "${EXIST[@]}" || { echo "!! gagal backup, henti"; exit 2; }
tar -tzf "$HOME/backup_pre_restore74_$TS.tar.gz" >/dev/null || { echo "!! backup rusak, henti"; exit 2; }
echo "   backup valid: ${#EXIST[@]} file -> $HOME/backup_pre_restore74_$TS.tar.gz"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/rollback/restore-74/RESTORE_v17.0.74.zip"
wget -qO restore74.zip "$URL" || { echo "!! unduh gagal"; exit 3; }
unzip -t restore74.zip || { echo "!! zip rusak"; exit 4; }
unzip -o restore74.zip -d "$B"
rm -f restore74.zip
echo ">> Selesai. Situs kembali ke v17.0.74. Jalankan Upgrade Otomatis + Purge LiteSpeed + Ctrl+F5."
