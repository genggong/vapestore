#!/bin/bash
# Apply PATCH v17.0.63 (fix 500 /produk: legacy _data.php kehilangan total+pagination) for vapestore
# Run from docroot (~/public_html):  cd ~/public_html && bash p17063.sh
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
  public/store/products.php public/store/_data.php public/assets/js/storefront.js public/assets/css/storefront.css \
  legacy/public/store/products.php legacy/public/store/_data.php legacy/public/assets/js/storefront.js legacy/public/assets/css/storefront.css \
  pos/public/store/products.php pos/public/store/_data.php pos/public/assets/js/storefront.js pos/public/assets/css/storefront.css \
  VERSION 2>/dev/null || { echo "!! gagal backup, henti"; exit 2; }

echo ">> Unduh patch"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.63/PATCH_v17.0.63.zip"
wget -qO p17063.zip "$URL" || { echo "!! unduh gagal"; exit 3; }

echo ">> Uji zip"
unzip -t p17063.zip || { echo "!! zip rusak"; exit 4; }

echo ">> Apply (overwrite)"
unzip -o p17063.zip -d "$B"

rm -f p17063.zip
echo ">> Selesai. Lanjut jalankan Upgrade Otomatis & purge cache."
