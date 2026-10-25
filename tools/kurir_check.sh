#!/usr/bin/env bash
# V17.0.46 — cek/repair tarif Kurir Toko untuk kelurahan yang checkout-nya ditolak.
# Penggunaan dari docroot (~/public_html/app atau ~/public_html):
#   php tools/kurir_check.php "MEJASEM BARAT"            # cek saja
#   php tools/kurir_check.php "MEJASEM BARAT" --apply    # reactivate tarif non-aktif utk kelurahan ini
set -uo pipefail
[ -f index.php ] && cd "$(dirname "$0")/.."
[ -d app ] || { echo "ERROR: jalankan dari docroot yang punya folder app (~/public_html)." >&2; exit 1; }
# Muat kredensial DB dari app/Config/database.php tanpa mengeprint isinya.
DBHOST=$(php -r '$c=include "app/Config/database.php"; echo $c["default"]["hostname"] ?? "127.0.0.1";' 2>/dev/null)
DBNAME=$(php -r '$c=include "app/Config/database.php"; echo $c["default"]["database"] ?? "vapekong_pos";' 2>/dev/null)
DBUSER=$(php -r '$c=include "app/Config/database.php"; echo $c["default"]["username"] ?? "vapekong";' 2>/dev/null)
DBPASS=$(php -r '$c=include "app/Config/database.php"; echo $c["default"]["password"] ?? "";' 2>/dev/null)
[ -n "$DBUSER" ] || { echo "ERROR: DB user kosong. Periksa app/Config/database.php atau kubisahkan."; exit 1; }
export VAPEKONG_DB_HOST="$DBHOST" VAPEKONG_DB_NAME="$DBNAME" VAPEKONG_DB_USER="$DBUSER" VAPEKONG_DB_PASS="$DBPASS"
exec php tools/kurir_check.php "$@"
