#!/bin/bash
# ============================================================
#  RESTORE MASTER VAPEKONG v17.0.10  →  timpa semua di ~/public_html
#  Aman: backup dulu (kode + folder nyasar), lalu overwrite.
# ============================================================
set -u

cd ~/public_html || { echo "ERROR: folder ~/public_html tidak ditemukan"; exit 1; }
TS=$(date +%Y%m%d_%H%M%S)
BASE="https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v17.0.10"
ZIP_URL="$BASE/MASTER_VAPEKONG_v17.0.10_CODE.zip"

echo "===[1/6] Backup kode saat ini -> ~/backup_code_$TS.tar.gz"
tar czf ~/backup_code_$TS.tar.gz \
    --exclude='legacy/public/uploads' --exclude='public/uploads' \
    --exclude='storage/backups' . 2>/dev/null
echo "    selesai (media/uploads tidak ikut - sudah aman di server)"

echo "===[2/6] Pindahkan folder NYASAR (penyebab 500) ke backup"
mkdir -p ~/_backup_stray_$TS
for d in store admin; do
  if [ -d "$d" ]; then mv "$d" ~/_backup_stray_$TS/ && echo "    dipindah: $d/"; fi
done

echo "===[3/6] Unduh master v17.0.10"
wget -q -O /tmp/master_code.zip "$ZIP_URL" || { echo "ERROR: gagal unduh ($ZIP_URL)"; exit 1; }
echo "    ukuran: $(du -h /tmp/master_code.zip | cut -f1)"

echo "===[4/6] TIMPA semua file master (overwrite)"
unzip -o -q /tmp/master_code.zip -d ~/public_html || { echo "ERROR: gagal unzip"; exit 1; }
echo "    selesai"

echo "===[5/6] Bersihkan cache"
rm -f writable/cache/* legacy/storage/cache/* 2>/dev/null
echo "    selesai"

echo "===[6/6] VERIFIKASI"
echo -n "    VERSION  : "; cat VERSION 2>/dev/null
echo -n "    index.php: "; [ -f index.php ] && echo "ADA ($(stat -c%s index.php) B)" || echo "TIDAK ADA"
echo -n "    .htaccess: "; [ -f .htaccess ] && echo "ADA ($(stat -c%s .htaccess) B)" || echo "TIDAK ADA"
echo -n "    legacy/public: "; [ -d legacy/public ] && echo "ADA" || echo "TIDAK ADA"
echo -n "    app/ + system/: "; [ -d app ] && [ -d system ] && echo "ADA" || echo "TIDAK ADA"

echo
echo "SELESAI."
echo "Backup: ~/backup_code_$TS.tar.gz  |  folder nyasar: ~/_backup_stray_$TS"
echo "WAJIB: LiteSpeed Cache Manager -> Purge All, baru tes situs."
echo "Tes: https://vapekongstore.com/   /produk   /store   /kurir/login"