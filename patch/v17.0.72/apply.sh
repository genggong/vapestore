#!/bin/bash
# Apply PATCH v17.0.72 (referral poin POS)
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B" || cd ~/public_html
case "$PWD" in *public_html) echo "OK docroot: $PWD";; *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1;; esac
TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup -> ~/backup_code_$TS.tar.gz"
tar -czf ~/backup_code_$TS.tar.gz \
  app/Helpers/helpers.php app/Modules/Accounting/Accounting.php app/Modules/Marketplace/Marketplace.php \
  legacy/app/Helpers/helpers.php legacy/app/Modules/Accounting/Accounting.php legacy/app/Modules/Marketplace/Marketplace.php \
  pos/app/Helpers/helpers.php pos/app/Modules/Accounting/Accounting.php pos/app/Modules/Marketplace/Marketplace.php \
  public/admin/dashboard.php public/admin/pos/index.php public/admin/transactions/detail.php public/admin/casaku/status.php public/assets/js/marketplace-checkout.js public/store/checkout.php \
  legacy/public/admin/dashboard.php legacy/public/admin/pos/index.php legacy/public/admin/transactions/detail.php legacy/public/admin/casaku/status.php legacy/public/assets/js/marketplace-checkout.js legacy/public/store/checkout.php \
  pos/public/admin/dashboard.php pos/public/admin/pos/index.php pos/public/admin/transactions/detail.php pos/public/admin/casaku/status.php pos/public/assets/js/marketplace-checkout.js pos/public/store/checkout.php VERSION 2>/dev/null || { echo "!! gagal backup, henti"; exit 2; }
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.72/PATCH_v17.0.72.zip"
wget -qO p17072.zip "$URL" || { echo "!! unduh gagal"; exit 3; }
unzip -t p17072.zip || { echo "!! zip rusak"; exit 4; }
unzip -o p17072.zip -d "$B"
rm -f p17072.zip
echo ">> Selesai. Jalankan Upgrade Otomatis + Purge LiteSpeed."
