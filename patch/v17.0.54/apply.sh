#!/bin/bash
# Apply PATCH v17.0.54 (Marketplace.php + VERSION) for vapestore
# Run from docroot (~/public_html):  cd ~/public_html && bash p1754.sh
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B" || cd ~/public_html

case "$PWD" in
  *public_html) echo "OK docroot: $PWD" ;;
  *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1 ;;
esac

TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup kode -> ~/backup_code_$TS.tar.gz"
tar -czf ~/backup_code_$TS.tar.gz \
  app/Modules/Marketplace/Marketplace.php \
  legacy/app/Modules/Marketplace/Marketplace.php \
  pos/app/Modules/Marketplace/Marketplace.php \
  VERSION 2>/dev/null || { echo "!! gagal backup, henti"; exit 2; }

echo ">> Unduh patch"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.54/PATCH_v17.0.54.zip"
wget -qO p1754.zip "$URL" || { echo "!! unduh gagal"; exit 3; }

echo ">> Uji zip"
unzip -t p1754.zip || { echo "!! zip rusak"; exit 4; }

echo ">> Apply (overwrite)"
unzip -o p1754.zip -d "$B"

rm -f p1754.zip
echo ">> Selesai. Lanjut jalankan Upgrade Otomatis & purge cache."
