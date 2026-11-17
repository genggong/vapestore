#!/bin/bash
# Apply PATCH v17.0.74 (QRIS CasaKu bayar-dulu + Tambah Member + notif WA referrer)
set -o errexit -o nounset -o pipefail
B="${BASH_SOURCE[0]}"; B="$(cd "$(dirname "$B")" && pwd)"
cd "$B" || cd ~/public_html
case "$PWD" in *public_html) echo "OK docroot: $PWD";; *) echo "Peringatan: bukan docroot?"; read -r -p "Lanjut (y/n)? " a; [ "$a" = y ] || exit 1;; esac
TS=$(date +%Y%m%d_%H%M%S)
echo ">> Backup -> ~/backup_code_$TS.tar.gz"
BACKUP_PATHS=(
  public/admin/pos/index.php public/admin/transactions/detail.php public/admin/casaku/status.php
  legacy/public/admin/pos/index.php legacy/public/admin/transactions/detail.php legacy/public/admin/casaku/status.php
  pos/public/admin/pos/index.php pos/public/admin/transactions/detail.php pos/public/admin/casaku/status.php VERSION
)
BACKUP_EXISTING=()
for path in "${BACKUP_PATHS[@]}"; do
  if [ -e "$path" ]; then BACKUP_EXISTING+=("$path"); else echo "   lewati backup (belum ada): $path"; fi
done
[ "${#BACKUP_EXISTING[@]}" -gt 0 ] || { echo "!! tidak ada file untuk backup, henti"; exit 2; }
tar -czf "$HOME/backup_code_$TS.tar.gz" "${BACKUP_EXISTING[@]}" || { echo "!! gagal backup, henti"; exit 2; }
tar -tzf "$HOME/backup_code_$TS.tar.gz" >/dev/null || { echo "!! arsip backup rusak, henti"; exit 2; }
echo "   backup valid: ${#BACKUP_EXISTING[@]} file"
URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.74/PATCH_v17.0.74.zip"
wget -qO p17074.zip "$URL" || { echo "!! unduh gagal"; exit 3; }
unzip -t p17074.zip || { echo "!! zip rusak"; exit 4; }
unzip -o p17074.zip -d "$B"
rm -f p17074.zip
echo ">> Selesai. Jalankan Upgrade Otomatis + Purge LiteSpeed."
