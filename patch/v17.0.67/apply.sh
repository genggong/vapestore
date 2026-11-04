#!/bin/bash
# Apply PATCH v17.0.67 (COD pesanan marketplace digabung ke kas/Tunai di dashboard & akuntansi) for vapestore
# Run from docroot (~/public_html):  cd ~/public_html && bash p17067.sh
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
  public/admin/dashboard.php app/Modules/Accounting/Accounting.php \
  legacy/public/admin/dashboard.php legacy/app/Modules/Accounting/Accounting.php \
  pos/public/admin/dashboard.php pos/app/Modules/Accounting/Accounting.php \
  VERSION 2>/dev/null || { echo "!! gagal backup, henti"; exit 2; }

echo ">> Unduh patch"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.67/PATCH_v17.0.67.zip"
wget -qO p17067.zip "$URL" || { echo "!! unduh gagal"; exit 3; }

echo ">> Uji zip"
unzip -t p17067.zip || { echo "!! zip rusak"; exit 4; }

echo ">> Apply (overwrite)"
unzip -o p17067.zip -d "$B"

rm -f p17067.zip
echo ">> Selesai. Upgrade Otomatis + Purge LiteSpeed."
