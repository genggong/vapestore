#!/bin/bash
# Apply PATCH v17.0.65 (fix auto-apply JS/CSS di storefront LIVE yang diserve dari ROOT assets/) for vapestore
# Run from docroot (~/public_html):  cd ~/public_html && bash p17065.sh
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B" || cd ~/public_html

case "$PWD" in
  *public_html) echo "OK docroot: $PWD" ;;
  *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1 ;;
esac

TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup -> ~/backup_code_$TS.tar.gz"
tar -czf ~/backup_code_$TS.tar.gz \
  assets/js/storefront.js assets/css/storefront.css \
  legacy/public/store/_header.php legacy/public/store/_footer.php \
  VERSION 2>/dev/null || { echo "!! gagal backup, henti"; exit 2; }

echo ">> Unduh patch"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.65/PATCH_v17.0.65.zip"
wget -qO p17065.zip "$URL" || { echo "!! unduh gagal"; exit 3; }

echo ">> Uji zip"
unzip -t p17065.zip || { echo "!! zip rusak"; exit 4; }

echo ">> Apply (overwrite)"
unzip -o p17065.zip -d "$B"

rm -f p17065.zip
echo ">> Selesai. JANGAN LUPA: Upgrade Otomatis + Purge LiteSpeed Cache (penting supaya browser ambil storefront.js versi baru)."
