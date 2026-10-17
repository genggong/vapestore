#!/bin/bash
# ============================================================
#  RESTORE MASTER VAPEKONG v17.0.16  ->  timpa semua di docroot
#  Aman: backup -> unduh -> UJI ZIP -> baru menimpa.
#  Jalankan DARI docroot hosting (folder berisi index.php):
#     cd ~/public_html
#     wget -qO restore_master.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v17.0.16/restore_master.sh"
#     bash restore_master.sh
#  Override uji/mirror: VK_BASE_URL=... VK_DOCROOT=... bash restore_master.sh
# ============================================================
set -u

VERSION="17.0.16"
BASE="${VK_BASE_URL:-https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v${VERSION}}"
ZIP_URL="$BASE/MASTER_VAPEKONG_v${VERSION}_CODE.zip"
DOCROOT="${VK_DOCROOT:-$PWD}"

cd "$DOCROOT" || { echo "ERROR: folder $DOCROOT tidak ditemukan"; exit 1; }

if [ ! -f index.php ]; then
  echo "ERROR: tidak ada index.php di $(pwd)."
  echo "       Jalankan dari docroot:  cd ~/public_html && bash restore_master.sh"
  exit 1
fi
[ "$(id -u)" = "0" ] && echo "PERINGATAN: jalan sebagai root — file hasil akan milik root. Lebih baik sebagai user hosting."

TS=$(date +%Y%m%d_%H%M%S)
ZIP_TMP="/tmp/master_code_$TS.zip"
echo "===[0/6] Docroot: $(pwd)  ->  target v$VERSION"
echo -n "    versi sekarang: "; cat VERSION 2>/dev/null || echo "(tidak diketahui)"

# --- 1. BACKUP: apa pun yang terjadi setelah ini, ada titik pulang ---
echo "===[1/6] Backup kode saat ini -> ~/backup_code_$TS.tar.gz"
tar czf ~/backup_code_$TS.tar.gz \
    --exclude='uploads' --exclude='storage/backups' --exclude='backup_*' --exclude='_backup_stray_*' \
    . 2>/dev/null
echo "    selesai ($(du -h ~/backup_code_$TS.tar.gz 2>/dev/null | cut -f1)) — uploads/media tidak ikut, tetap aman di server"

# --- 2. UNDUH: belum menyentuh satu file pun ---
echo "===[2/6] Unduh master v$VERSION"
wget -q -O "$ZIP_TMP" "$ZIP_URL" || { echo "ERROR: gagal unduh ($ZIP_URL) — TIDAK ADA file yang diubah."; rm -f "$ZIP_TMP"; exit 1; }
[ -s "$ZIP_TMP" ] || { echo "ERROR: unduhan kosong — TIDAK ADA file yang diubah."; rm -f "$ZIP_TMP"; exit 1; }
echo "    ukuran: $(du -h "$ZIP_TMP" | cut -f1)"

# --- 3. UJI ZIP sebelum menimpa ---
echo "===[3/6] Uji integritas zip"
if ! unzip -t -q "$ZIP_TMP" >/dev/null 2>&1; then
  echo "ERROR: zip rusak / unduhan tidak lengkap — TIDAK ADA file yang diubah."
  rm -f "$ZIP_TMP"
  exit 1
fi
echo "    zip OK — siap menimpa"

# --- 4. Buang folder nyasar (menutupi route -> 500) ---
echo "===[4/6] Pindahkan folder NYASAR ke backup"
mkdir -p ~/_backup_stray_$TS
for d in store admin; do
  if [ -d "$d" ]; then mv "$d" ~/_backup_stray_$TS/ && echo "    dipindah: $d/"; fi
done

# --- 5. TIMPA ---
echo "===[5/6] TIMPA semua file master (overwrite)"
unzip -o -q "$ZIP_TMP" -d . || { echo "ERROR: gagal unzip — sebagian file mungkin sudah tertimpa. Pulihkan: tar xzf ~/backup_code_$TS.tar.gz"; rm -f "$ZIP_TMP"; exit 1; }
rm -f "$ZIP_TMP"
mkdir -p writable/cache writable/logs writable/session writable/debugbar writable/uploads writable/backups 2>/dev/null
rm -f writable/cache/* legacy/storage/cache/* pos/writable/cache/* 2>/dev/null
echo "    selesai"

# --- 6. VERIFIKASI ---
echo "===[6/6] VERIFIKASI"
V="$(cat VERSION 2>/dev/null | tr -d ' \n')"
echo "    VERSION   : ${V:-TIDAK ADA}"
echo -n "    index.php : "; [ -f index.php ] && echo "ADA ($(stat -c%s index.php) B)" || echo "TIDAK ADA"
echo -n "    .htaccess : "; [ -f .htaccess ] && echo "ADA ($(stat -c%s .htaccess) B)" || echo "TIDAK ADA"
echo -n "    legacy/public + app/ + system/ : "
[ -d legacy/public ] && [ -d app ] && [ -d system ] && echo "ADA" || echo "TIDAK LENGKAP"
echo -n "    marker patch v17.0.16 : "
MOK=1
grep -q "shipping_income"                legacy/app/Modules/Accounting/Accounting.php  || { echo "GAGAL: shipping_income"; MOK=0; }
grep -q "branch_sync_all_product_stocks" legacy/public/admin/branches/_helpers.php     || { echo "GAGAL: guard sync branch"; MOK=0; }
grep -q 'haveWs < $needWs'               legacy/public/admin/warehouse/_helpers.php   || { echo "GAGAL: guard sync warehouse"; MOK=0; }
grep -q "APP_ASSET_VERSION"              legacy/public/layouts/admin-header.php        || { echo "GAGAL: cache-bust aset"; MOK=0; }
[ "$MOK" = "1" ] && echo "OK (ongkir terpisah, guard sync stok, cache-bust aset)"
echo

if [ "$V" = "$VERSION" ]; then
  echo "SELESAI. Master v$VERSION terpasang."
else
  echo "PERINGATAN: VERSION di server = '${V}' (harusnya $VERSION). Cek hasil unduhan zip."
fi
echo "Backup      : ~/backup_code_$TS.tar.gz  |  folder nyasar: ~/_backup_stray_$TS"
echo "WAJIB       : LiteSpeed Cache Manager -> Purge All, baru tes situs."
echo "NOTE        : perubahan DB (index/tabel dari 'Jalankan Upgrade Otomatis') TIDAK ikut dirollback."
echo "Tes         : /  |  /produk  |  /store  |  /kurir/login  |  /admin/system-upgrade/index.php"