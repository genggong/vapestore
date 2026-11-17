#!/bin/bash
# Apply PATCH v17.0.73 (kode unik QRIS marketplace + cache POS)
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B" || cd ~/public_html
case "$PWD" in *public_html) echo "OK docroot: $PWD";; *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1;; esac
TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup -> ~/backup_code_$TS.tar.gz"
BACKUP_PATHS=(
  app/Modules/Marketplace/Marketplace.php app/Modules/Marketplace/MarketplaceV15.php app/Modules/Marketplace/SchemaDoctor.php
  legacy/app/Modules/Marketplace/Marketplace.php legacy/app/Modules/Marketplace/MarketplaceV15.php legacy/app/Modules/Marketplace/SchemaDoctor.php
  pos/app/Modules/Marketplace/Marketplace.php pos/app/Modules/Marketplace/MarketplaceV15.php pos/app/Modules/Marketplace/SchemaDoctor.php VERSION
)
BACKUP_EXISTING=()
for path in "${BACKUP_PATHS[@]}"; do
  if [ -e "$path" ]; then BACKUP_EXISTING+=("$path"); else echo "   lewati backup (belum ada): $path"; fi
done
[ "${#BACKUP_EXISTING[@]}" -gt 0 ] || { echo "!! tidak ada file untuk backup, henti"; exit 2; }
tar -czf "$HOME/backup_code_$TS.tar.gz" "${BACKUP_EXISTING[@]}" || { echo "!! gagal backup, henti"; exit 2; }
tar -tzf "$HOME/backup_code_$TS.tar.gz" >/dev/null || { echo "!! arsip backup rusak, henti"; exit 2; }
echo "   backup valid: ${#BACKUP_EXISTING[@]} file"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.73/PATCH_v17.0.73.zip"
wget -qO p17073.zip "$URL" || { echo "!! unduh gagal"; exit 3; }
unzip -t p17073.zip || { echo "!! zip rusak"; exit 4; }
unzip -o p17073.zip -d "$B"
rm -f p17073.zip
echo ">> Selesai. Jalankan Upgrade Otomatis + Purge LiteSpeed."
