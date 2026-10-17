#!/bin/bash
# Uji end-to-end sistem patch/restore Vapekong di DIR PALSU (tidak menyentuh hosting asli).
set -u
SRC="$(cd "$(dirname "$0")/.." && pwd)"
VER="$(cat "$SRC/VERSION" | tr -d " \n")"
PORT=$(python3 -c "import socket;s=socket.socket();s.bind(('127.0.0.1',0));print(s.getsockname()[1]);s.close()")
ROOT="${VK_TEST_DIR:-/tmp/vk_testrun}"
BASE_URL="http://127.0.0.1:$PORT"
rm -rf "$ROOT"; mkdir -p "$ROOT"
FAKE="$ROOT/home"
DOC="$FAKE/public_html"
OK=0
chk(){ if ( eval "$2" ); then echo "  [OK]    $1"; else echo "  [GAGAL] $1"; OK=1; fi; }

# siapkan zip RUSAK (dipotong 100 KB pertama) yang tetap unduhannya sukses
CORRUPT="$SRC/patch/__corrupt_test__"
mkdir -p "$CORRUPT"
head -c 100000 "$SRC/patch/RESTORE_MASTER_v$VER/MASTER_VAPEKONG_v${VER}_CODE.zip" > "$CORRUPT/MASTER_VAPEKONG_v${VER}_CODE.zip"

cd "$SRC" && (python3 -m http.server $PORT --bind 127.0.0.1 >/dev/null 2>&1 & echo $! > "$ROOT/srv.pid")
sleep 1.5
kill -0 "$(cat "$ROOT/srv.pid")" 2>/dev/null && echo "[setup] server lokal :$PORT hidup" || { echo "[setup] GAGAL start server"; exit 1; }
curl -s -o /dev/null -w "[setup] zip master via HTTP : %{http_code} (%{size_download} B)\n" "$BASE_URL/patch/RESTORE_MASTER_v$VER/MASTER_VAPEKONG_v${VER}_CODE.zip"
curl -s -o /dev/null -w "[setup] latest.txt via HTTP  : %{http_code} (%{size_download} B) isi=" "$BASE_URL/patch/latest.txt"; curl -s "$BASE_URL/patch/latest.txt"

bikin_docroot(){
  rm -rf "$DOC"; mkdir -p "$DOC"; cd "$DOC" || exit 1
  echo '<?php // index lama'  > index.php
  echo '# htaccess lama'      > .htaccess
  echo '17.0.12'              > VERSION
  mkdir -p legacy/public/uploads public/uploads pos/public/uploads uploads
  mkdir -p legacy/storage/cache writable/cache app system legacy/app/Modules/Accounting
  mkdir -p legacy/public/admin/branches legacy/public/admin/warehouse legacy/public/layouts pos
  echo "media penting"   > uploads/foto-produk.jpg
  echo "media live"      > legacy/public/uploads/bukti.jpg
  echo "DB_PASS=rahasia123" > .env
  echo "cache basi"      > writable/cache/kosong.php
  echo "cache basi"      > legacy/storage/cache/lama.php
  echo '<?php $x=1; // versi lama tanpa marker' > legacy/app/Modules/Accounting/Accounting.php
  mkdir -p store admin
  echo '<?php // nyasar' > store/index.php
  echo '<?php // nyasar' > admin/login.php
  cp index.php public/index.php; cp index.php pos/index.php
}
bikin_docroot
echo "[setup] docroot palsu siap ($DOC) — VERSION=17.0.12, ada folder nyasar store/ + admin/, ada .env & uploads"

run_restore(){ HOME="$FAKE" VK_BASE_URL="$1" VK_DOCROOT="$DOC" bash "$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh"; }

echo; echo "############ TEST 1: restore dari kondisi rusak ############"
run_restore "$BASE_URL/patch/RESTORE_MASTER_v$VER" | tail -14; echo "[hasil] exit = ${PIPESTATUS[0]}"

echo; echo "############ TEST 2: kondisi setelah restore ############"
cd "$DOC"
chk "VERSION jadi 17.0.18"            "[ \"\$(cat '$DOC/VERSION')\" = '$VER' ]"
chk "index.php ditimpa master"        "grep -q 'CodeIgniter' '$DOC/index.php'"
chk "store/ dipindah ke staging"      "[ ! -d '$DOC/store' ] && [ -d \"\$(ls -d $FAKE/_backup_stray_*/store)\" ]"
chk "admin/ dipindah ke staging"      "[ ! -d '$DOC/admin' ] && [ -d \"\$(ls -d $FAKE/_backup_stray_*/admin)\" ]"
BACKUP="$(ls $FAKE/backup_code_*.tar.gz | head -1)"
chk "backup kode dibuat (~2.7M)"      "tar tzf '$BACKUP' >/dev/null 2>&1"
chk "backup memuat .env"              "tar tzf '$BACKUP' | grep -qx './.env'"
chk "backup memuat store/index.php"   "tar tzf '$BACKUP' | grep -qx './store/index.php'"
chk "backup MENGECUALIKAN uploads"    "! tar tzf '$BACKUP' | grep -q './uploads/foto-produk.jpg'"
chk "upload live SELAMAT"             "grep -q 'media penting' '$DOC/uploads/foto-produk.jpg'"
chk "legacy uploads SELAMAT"          "[ -f '$DOC/legacy/public/uploads/bukti.jpg' ]"
chk ".env SELAMAT"                    "grep -q 'rahasia123' '$DOC/.env'"
chk "cache writable dibersihkan"      "[ -z \"\$(ls $DOC/writable/cache/*.php 2>/dev/null)\" ]"
chk "cache legacy dibersihkan"        "[ -z \"\$(ls $DOC/legacy/storage/cache/*.php 2>/dev/null)\" ]"
chk "file kode ditimpa (marker v16)"  "grep -q 'shipping_income' '$DOC/legacy/app/Modules/Accounting/Accounting.php'"
chk "3 tree terisi (pos+public)"      "[ -f '$DOC/pos/public/admin/dashboard.php' ] && [ -f '$DOC/public/admin/dashboard.php' ]"
chk "writable runtime dibuat"         "[ -d '$DOC/writable/session' ] && [ -d '$DOC/writable/logs' ] && [ -d '$DOC/writable/backups' ]"
chk "hash.php TIDAK dikirim"          "[ ! -f '$DOC/hash.php' ]"

echo; echo "############ TEST 3: idempoten (restore ke-2, detik yang sama) ############"
bikin_docroot
run_restore "$BASE_URL/patch/RESTORE_MASTER_v$VER" >/dev/null 2>&1
run_restore "$BASE_URL/patch/RESTORE_MASTER_v$VER" >/dev/null 2>&1
chk "restore ke-2 exit 0"             "[ \"\$(cat '$DOC/VERSION')\" = '$VER' ]"
chk "VERSION tetap 17.0.18"           "[ \"\$(cat '$DOC/VERSION')\" = '$VER' ]"
chk "uploads masih utuh"              "grep -q 'media penting' '$DOC/uploads/foto-produk.jpg'"
chk "folder nyasar TETAP dipindah (run 2x detik sama)" "[ ! -d '$DOC/store' ] && [ ! -d '$DOC/admin' ]"
chk "backup unik per run (tidak saling timpa)"         "[ \$(ls $FAKE/backup_code_*.tar.gz | wc -l) -ge 2 ]"

echo; echo "############ TEST 4: UPDATE_VAPEKONG.sh ############"
echo "--- 4a. versi sudah terbaru ---"
HOME="$FAKE" VK_REPO_RAW="$BASE_URL" VK_DOCROOT="$DOC" bash "$SRC/patch/UPDATE_VAPEKONG.sh" 2>&1 | tail -3
echo "--- 4b. turunkan ke 17.0.12, jalankan update (harus menaikkan sendiri) ---"
echo '17.0.12' > "$DOC/VERSION"
HOME="$FAKE" VK_REPO_RAW="$BASE_URL" VK_DOCROOT="$DOC" bash "$SRC/patch/UPDATE_VAPEKONG.sh" 2>&1 | tail -3
chk "update.sh menaikkan ke 17.0.18"  "[ \"\$(cat '$DOC/VERSION')\" = '$VER' ]"
echo "--- 4c. versi server lebih baru dari repo (harus berhenti) ---"
echo '99.0.0' > "$DOC/VERSION"
HOME="$FAKE" VK_REPO_RAW="$BASE_URL" VK_DOCROOT="$DOC" bash "$SRC/patch/UPDATE_VAPEKONG.sh" 2>&1 | tail -2
chk "tidak menurunkan versi tanpa --force" "[ \"\$(cat '$DOC/VERSION')\" = '99.0.0' ]"
echo "--- 4d. --force ---"
HOME="$FAKE" VK_REPO_RAW="$BASE_URL" VK_DOCROOT="$DOC" bash "$SRC/patch/UPDATE_VAPEKONG.sh" --force 2>&1 | tail -2
chk "--force menimpakan master"       "[ \"\$(cat '$DOC/VERSION')\" = '$VER' ]"

echo; echo "############ TEST 5: guard docroot salah ############"
HOME="$FAKE" VK_BASE_URL="$BASE_URL/patch/RESTORE_MASTER_v$VER" VK_DOCROOT="$ROOT" bash "$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh" 2>&1 | tail -2
chk "tolak jalan tanpa index.php"     "! HOME=$FAKE VK_BASE_URL='$BASE_URL/patch/RESTORE_MASTER_v$VER' VK_DOCROOT='$ROOT' bash '$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh' >/dev/null 2>&1"

echo; echo "############ TEST 6: ZIP RUSAK -> tidak boleh menimpa ############"
bikin_docroot
HOME="$FAKE" VK_BASE_URL="$BASE_URL/patch/__corrupt_test__" VK_DOCROOT="$DOC" bash "$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh" 2>&1 | tail -3
chk "VERSION tetap 17.0.12"           "[ \"\$(cat '$DOC/VERSION')\" = '17.0.12' ]"
chk "index.php lama tidak tersentuh"  "grep -q 'index lama' '$DOC/index.php'"
chk "uploads tetap ada"               "grep -q 'media penting' '$DOC/uploads/foto-produk.jpg'"
chk "folder nyasar TIDAK dipindah"    "[ -d '$DOC/store' ] && [ -d '$DOC/admin' ]"

echo; echo "############ TEST 7: unduhan gagal (404) ############"
HOME="$FAKE" VK_BASE_URL="$BASE_URL/patch/tidak-ada-dir" VK_DOCROOT="$DOC" bash "$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh" 2>&1 | tail -2
chk "VERSION masih 17.0.12"           "[ \"\$(cat '$DOC/VERSION')\" = '17.0.12' ]"

echo; echo "############ TEST 8: KONFIG HOSTING TIDAK BOLEH DITIMPA ############"
bikin_docroot
mkdir -p "$DOC/legacy/app/Config" "$DOC/app/Config" "$DOC/pos/app/Config"
# tiru hosting yang menyimpan kredensial & setelan server di file (kasus paling berbahaya)
cat > "$DOC/legacy/app/Config/database.php" <<'PHP'
<?php
return ['host'=>'localhost','database'=>'vapekong_live','username'=>'vapekong_live','password'=>'RAHASIA_HOSTING_1'];
PHP
cat > "$DOC/app/Config/database.php" <<'PHP'
<?php
return ['host'=>'localhost','database'=>'vapekong_live','username'=>'vapekong_live','password'=>'RAHASIA_HOSTING_2'];
PHP
cat > "$DOC/.htaccess" <<'HT'
# aturan KHUSUS hosting ini (harus selamat)
Header set X-Custom "punyaku"
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
HT
cat > "$DOC/php.ini" <<'INI'
error_log = "/home/coptahco/logs/php.error.log"
INI
H1=$(sha256sum "$DOC/legacy/app/Config/database.php" | cut -d' ' -f1)
H2=$(sha256sum "$DOC/app/Config/database.php" | cut -d' ' -f1)
H3=$(sha256sum "$DOC/.htaccess" | cut -d' ' -f1)
H4=$(sha256sum "$DOC/php.ini" | cut -d' ' -f1)
run_restore "$BASE_URL/patch/RESTORE_MASTER_v$VER" | sed -n '/dipertahankan/,/config DB/p'
chk "kredensial legacy/app/Config/database.php utuh"  "[ \"\$(sha256sum '$DOC/legacy/app/Config/database.php' | cut -d' ' -f1)\" = '$H1' ]"
chk "kredensial app/Config/database.php utuh"         "[ \"\$(sha256sum '$DOC/app/Config/database.php' | cut -d' ' -f1)\" = '$H2' ]"
chk ".htaccess khusus hosting utuh"                   "[ \"\$(sha256sum '$DOC/.htaccess' | cut -d' ' -f1)\" = '$H3' ]"
chk "php.ini hosting utuh"                            "[ \"\$(sha256sum '$DOC/php.ini' | cut -d' ' -f1)\" = '$H4' ]"
chk "situs tetap ter-upgrade (VERSION 17.0.18)"       "[ \"\$(cat '$DOC/VERSION')\" = '$VER' ]"
chk "kode master tetap masuk (marker ongkir)"         "grep -q shipping_income '$DOC/legacy/app/Modules/Accounting/Accounting.php'"
echo "--- cek peringatan .htaccess tanpa front-controller ---"
cat > "$DOC/.htaccess" <<'HT'
# htaccess lama tanpa aturan CI4
Header set X-Custom "punyaku"
HT
HOME="$FAKE" VK_BASE_URL="$BASE_URL/patch/RESTORE_MASTER_v$VER" VK_DOCROOT="$DOC" bash "$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh" 2>&1 | grep -A2 "PERINGATAN" | head -4
chk "peringatan muncul saat aturan CI4 hilang"        "HOME=$FAKE VK_BASE_URL='$BASE_URL/patch/RESTORE_MASTER_v$VER' VK_DOCROOT='$DOC' bash '$SRC/patch/RESTORE_MASTER_v$VER/restore_master.sh' 2>&1 | grep -q 'RewriteRule ^ index.php'"

kill "$(cat "$ROOT/srv.pid")" 2>/dev/null
rm -rf "$CORRUPT"

echo
[ $OK -eq 0 ] && echo "############ RINGKASAN: SEMUA CEK LULUS ############" || echo "############ RINGKASAN: ADA CEK GAGAL ############"
exit $OK