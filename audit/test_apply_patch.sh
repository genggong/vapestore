#!/bin/bash
# Uji end-to-end tools/apply_patch_<versi terbaru>.sh di docroot tiruan:
# skrip yang benar-benar dipakai user (diunduh dari GitHub), DB audit lokal, HOME palsu.
set -u
ROOT="${VK_TEST_DIR:-/tmp/vk_apply_test}"
rm -rf "$ROOT"; mkdir -p "$ROOT"
SRC="$(cd "$(dirname "$0")/.." && pwd)"; VER="$(cat "$SRC/VERSION" | tr -d " \n")"
unzip -q "$SRC/patch/RESTORE_MASTER_v$VER/MASTER_VAPEKONG_v${VER}_CODE.zip" -d "$ROOT" || exit 1
cd "$ROOT"

# bikin tampak seperti instalasi LAMA: versi lama + 1 file rusak
echo '17.0.12' > VERSION
echo '<?php // lama' > legacy/app/Modules/Accounting/Accounting.php
echo '# ht hosting' > .htaccess
mkdir -p legacy/app/Config app/Config
echo '<?php return ["host"=>"x","database"=>"d","username"=>"u","password"=>"RAHASIA"];' > legacy/app/Config/database.php

export VAPEKONG_DB_HOST=127.0.0.1 VAPEKONG_DB_NAME=vapekong_audit VAPEKONG_DB_USER=vkaudit VAPEKONG_DB_PASS='VkAudit#2026'

APPLY="$(ls tools/apply_patch_v17_0_*.sh | sort | tail -1)"
APPLYVER="$(echo "$APPLY" | sed -E "s#tools/apply_patch_v([0-9_]+)\.sh#\1#" | tr "_" ".")"
echo "########## JALANKAN $APPLY (versi $APPLYVER) ##########"
bash "$APPLY" 2>&1 | tail -40
RC=${PIPESTATUS[0]}
echo "[exit=$RC]"

echo
echo "########## CEK HASIL ##########"
BK=$(ls -dt backup_* 2>/dev/null | head -1)
echo "folder backup : ${BK:-TIDAK ADA}"
echo "jumlah .bak   : $(find ${BK:-/nonexistent} -name '*.bak' 2>/dev/null | wc -l)"
echo "cadangan penuh: $(test -f "${BK:-/none}/full_docroot.tar.gz" && echo ADA || echo TIDAK ADA)"
echo "VERSION       : $(cat VERSION)"
echo "file lama ter-backup (Accounting.php): $(test -f "${BK:-/none}/legacy/app/Modules/Accounting/Accounting.php.bak" && echo ADA || echo TIDAK)"
echo ".htaccess hosting SELAMAT? $(grep -q 'ht hosting' .htaccess && echo YA || echo TIMPA)"
echo "config DB hosting SELAMAT? $(grep -q RAHASIA legacy/app/Config/database.php && echo YA || echo TIMPA)"
echo "index.php lurus? $(php -l index.php >/dev/null 2>&1 && echo YA || echo TIDAK)"
echo "marker ongkir  : $(grep -c shipping_income legacy/app/Modules/Accounting/Accounting.php)"
echo
echo "########## BUKTI BACKFILL ##########"
grep -E "DB OK:|VK BACKFILL OK|GAGAL" /tmp/apply_backfill.log 2>/dev/null || echo "(lihat output backfill di atas)"