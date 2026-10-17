#!/bin/bash
# ============================================================
#  RESTORE MASTER VAPEKONG v17.0.16  ->  timpa semua di docroot
#  Aman: backup -> unduh -> UJI ZIP -> baru menimpa.
#  Jalankan DARI docroot hosting (folder berisi index.php):
#     cd ~/public_html
#     wget -qO restore_master.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v17.0.22/restore_master.sh"
#     bash restore_master.sh
#  Override uji/mirror: VK_BASE_URL=... VK_DOCROOT=... bash restore_master.sh
# ============================================================
set -u

VERSION="17.0.22"
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
BACKUP=~/backup_code_$TS.tar.gz
_n=1; while [ -e "$BACKUP" ]; do _n=$((_n+1)); BACKUP=~/backup_code_${TS}_$_n.tar.gz; done
echo "===[0/6] Docroot: $(pwd)  ->  target v$VERSION"
echo -n "    versi sekarang: "; cat VERSION 2>/dev/null || echo "(tidak diketahui)"

# --- 1. BACKUP: apa pun yang terjadi setelah ini, ada titik pulang ---
echo "===[1/6] Backup kode saat ini -> $BACKUP"
tar czf "$BACKUP" \
    --exclude='uploads' --exclude='storage/backups' --exclude='backup_*' --exclude='_backup_stray_*' \
    . 2>/dev/null
echo "    selesai ($(du -h "$BACKUP" 2>/dev/null | cut -f1)) — uploads/media tidak ikut, tetap aman di server"

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
STRAY=~/_backup_stray_$TS
mkdir -p "$STRAY"
for d in store admin; do
  if [ -d "$d" ]; then
    t="$STRAY/$d"; _i=1
    while [ -e "$t" ]; do _i=$((_i+1)); t="$STRAY/${d}_$_i"; done
    if mv "$d" "$t"; then
      echo "    dipindah: $d/ -> $t"
    else
      echo "ERROR: gagal memindahkan $d/ — folder ini MENUTUPI route (sumber 500), wajib dipindah."
      echo "       Pindahkan manual lalu jalankan ulang:  mv $d ~/_backup_stray_manual_$TS/"
      exit 1
    fi
  fi
done

# --- 5. TIMPA (kecuali file konfigurasi milik hosting) ---
echo "===[5/6] TIMPA semua file master (overwrite)"
# File yang WAJIB dipertahankan: konfigurasi DB & setelan server milik hosting ini.
# docs/DEPLOY_STAGING.md: "build ini mempertahankan konfigurasi database aplikasi lama di
# legacy/app/Config/database.php" — kalau ditimpa, kredensial DB hilang dan situs mati.
PRESERVE=""; DIPERTAHANKAN=""
for f in .htaccess .env php.ini .user.ini pos/php.ini pos/.user.ini \
         app/Config/Database.php app/Config/database.php \
         legacy/app/Config/database.php legacy/app/Config/Database.php \
         pos/app/Config/database.php pos/app/Config/Database.php; do
  if [ -e "$f" ] && unzip -Z1 "$ZIP_TMP" 2>/dev/null | grep -qx "$f"; then
    PRESERVE="$PRESERVE $f"
    DIPERTAHANKAN="$DIPERTAHANKAN $f"
  fi
done
if [ -n "$DIPERTAHANKAN" ]; then echo "    dipertahankan (config hosting):$DIPERTAHANKAN"; fi

if [ -n "$PRESERVE" ]; then
  unzip -o -q "$ZIP_TMP" -d . -x $PRESERVE || { echo "ERROR: gagal unzip — sebagian file mungkin sudah tertimpa. Pulihkan: tar xzf $BACKUP"; rm -f "$ZIP_TMP"; exit 1; }
else
  unzip -o -q "$ZIP_TMP" -d . || { echo "ERROR: gagal unzip — sebagian file mungkin sudah tertimpa. Pulihkan: tar xzf $BACKUP"; rm -f "$ZIP_TMP"; exit 1; }
fi
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
# v17.0.18: konstanta APP_ASSET_VERSION didefinisikan di bootstrap dengan guard, jadi referensinya
    # bisa ada di layout ATAU halaman admin. Cukup pastikan ada minimal satu referensi.
    ASSET_REF=$(grep -l "APP_ASSET_VERSION" \
        legacy/public/layouts/admin-header.php legacy/public/admin/pos/index.php \
        legacy/public/admin/customers/edit.php legacy/public/maintenance.php 2>/dev/null | wc -l | tr -d " ")
    [ "${ASSET_REF:-0}" -ge 1 ] || { echo "GAGAL: tidak ada referensi APP_ASSET_VERSION"; MOK=0; }
[ "$MOK" = "1" ] && echo "OK (ongkir terpisah, guard sync stok, referensi aset cache-bust)"

# config hosting yang dipertahankan
for c in legacy/app/Config/database.php app/Config/database.php pos/app/Config/database.php; do
  if [ -e "$c" ]; then echo "    config DB  : $c  (dipertahankan apa adanya)"; fi
done
if [ -f .htaccess ] && ! grep -q 'RewriteRule ^ index.php \[L\]' .htaccess; then
  echo "    PERINGATAN : .htaccess TIDAK memuat 'RewriteRule ^ index.php [L]' (front-controller CI4)."
  echo "                 Tambahkan baris itu, atau hapus .htaccess lalu jalankan restore sekali lagi."
fi
echo

if [ "$V" = "$VERSION" ]; then
  echo "SELESAI. Master v$VERSION terpasang."
else
  echo "PERINGATAN: VERSION di server = '${V}' (harusnya $VERSION). Cek hasil unduhan zip."
fi
echo "Backup      : $BACKUP  |  folder nyasar: $STRAY"
echo "WAJIB       : LiteSpeed Cache Manager -> Purge All, baru tes situs."
echo "NOTE        : perubahan DB (index/tabel dari 'Jalankan Upgrade Otomatis') TIDAK ikut dirollback."
echo "Tes         : /  |  /produk  |  /store  |  /kurir/login  |  /admin  |  /admin/system-upgrade/index.php"