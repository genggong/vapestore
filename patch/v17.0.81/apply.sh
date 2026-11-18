#!/bin/bash
# Apply PATCH v17.0.81 — WA nota QRIS Casaku bersih (strip JSON) + label metode "QRIS Otomatis"
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B"
case "$PWD" in *public_html) echo "OK docroot: $PWD";; *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1;; esac
TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup -> ~/backup_code_$TS.tar.gz"
BACKUP_PATHS=(
  pos/public/admin/whatsapp/_helpers.php public/admin/whatsapp/_helpers.php legacy/public/admin/whatsapp/_helpers.php
  pos/app/Helpers/helpers.php app/Helpers/helpers.php legacy/app/Helpers/helpers.php
  pos/app/Modules/Accounting/Accounting.php app/Modules/Accounting/Accounting.php legacy/app/Modules/Accounting/Accounting.php VERSION
)
BACKUP_EXISTING=()
for path in "${BACKUP_PATHS[@]}"; do
  if [ -e "$path" ]; then BACKUP_EXISTING+=("$path"); else echo "   lewati backup (belum ada): $path"; fi
done
[ "${#BACKUP_EXISTING[@]}" -gt 0 ] || { echo "!! tidak ada file untuk backup, henti"; exit 2; }
tar -czf "$HOME/backup_code_$TS.tar.gz" "${BACKUP_EXISTING[@]}" || { echo "!! gagal backup, henti"; exit 2; }
tar -tzf "$HOME/backup_code_$TS.tar.gz" >/dev/null || { echo "!! arsip backup rusak, henti"; exit 2; }
echo "   backup valid: ${#BACKUP_EXISTING[@]} file"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.81/PATCH_v17.0.81.zip"
wget -qO p17081.zip "$URL" || { echo "!! unduh gagal"; exit 3; }
unzip -t p17081.zip || { echo "!! zip rusak"; exit 4; }
unzip -o p17081.zip -d "$B"
rm -f p17081.zip
echo ">> Selesai. Purge LiteSpeed, uji kirim nota QRIS Casaku via WhatsApp."
