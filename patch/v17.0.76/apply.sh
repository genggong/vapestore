#!/bin/bash
# Apply PATCH v17.0.76 (autocomplete tunggal wilayah checkout)
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B" || cd ~/public_html
case "$PWD" in *public_html) echo "OK docroot: $PWD";; *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1;; esac
TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup -> ~/backup_code_$TS.tar.gz"
BACKUP_PATHS=(
  public/store/checkout.php legacy/public/store/checkout.php pos/public/store/checkout.php
  assets/js/marketplace-checkout.js assets/css/storefront.css
  public/assets/js/marketplace-checkout.js public/assets/css/storefront.css
  legacy/public/assets/js/marketplace-checkout.js legacy/public/assets/css/storefront.css
  pos/public/assets/js/marketplace-checkout.js pos/public/assets/css/storefront.css VERSION
)
BACKUP_EXISTING=()
for path in "${BACKUP_PATHS[@]}"; do
  if [ -e "$path" ]; then BACKUP_EXISTING+=("$path"); else echo "   lewati backup (belum ada): $path"; fi
done
[ "${#BACKUP_EXISTING[@]}" -gt 0 ] || { echo "!! tidak ada file untuk backup, henti"; exit 2; }
tar -czf "$HOME/backup_code_$TS.tar.gz" "${BACKUP_EXISTING[@]}" || { echo "!! gagal backup, henti"; exit 2; }
tar -tzf "$HOME/backup_code_$TS.tar.gz" >/dev/null || { echo "!! arsip backup rusak, henti"; exit 2; }
echo "   backup valid: ${#BACKUP_EXISTING[@]} file"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.76/PATCH_v17.0.76.zip"
wget -qO p17076.zip "$URL" || { echo "!! unduh gagal"; exit 3; }
unzip -t p17076.zip || { echo "!! zip rusak"; exit 4; }
unzip -o p17076.zip -d "$B"
rm -f p17076.zip
echo ">> Selesai. Jalankan Upgrade Otomatis + Purge LiteSpeed (asset cache di-bust otomatis lewat v= param)."
