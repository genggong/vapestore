#!/usr/bin/env bash
# ============================================================
#  SISTEM PATCH VAPEKONGSTORE.COM — satu perintah, tanpa upload
#  Dijalankan DARI docroot hosting (folder berisi index.php):
#     cd ~/public_html
#     wget -qO update.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/UPDATE_VAPEKONG.sh"
#     bash update.sh              # normal: hanya jalan kalau versi beda
#     bash update.sh --force      # paksa timpa walau versi sama (mode perbaikan)
#  Membaca versi terbaru dari patch/latest.txt, lalu menjalankan
#  restore_master.sh milik versi itu (backup + timpa + verifikasi).
# ============================================================
set -u

FORCE=0
case "${1:-}" in
  --force|-f) FORCE=1 ;;
  ""|--check|-c) ;;
  *) echo "Pakai: bash update.sh [--force]"; exit 2 ;;
esac

REPO_RAW="${VK_REPO_RAW:-https://raw.githubusercontent.com/genggong/vapestore/main}"
DOCROOT="${VK_DOCROOT:-$PWD}"
cd "$DOCROOT" || { echo "ERROR: folder $DOCROOT tidak ditemukan"; exit 1; }
[ -f index.php ] || { echo "ERROR: tidak ada index.php di $(pwd) — jalankan dari ~/public_html"; exit 1; }

CUR="$(cat VERSION 2>/dev/null | tr -d ' \n')"
LATEST_TXT="$(wget -qO- "$REPO_RAW/patch/latest.txt")" || { echo "ERROR: gagal ambil patch/latest.txt dari repo"; exit 1; }
LATEST="$(printf '%s' "$LATEST_TXT" | head -1 | cut -d'|' -f1 | tr -d ' \n')"
DIR="$(printf '%s' "$LATEST_TXT" | head -1 | cut -d'|' -f2 | tr -d ' \n')"
[ -n "$LATEST" ] && [ -n "$DIR" ] || { echo "ERROR: patch/latest.txt tidak valid: '$LATEST_TXT'"; exit 1; }

echo "VERSI terpasang : ${CUR:-tidak diketahui}"
echo "VERSI terbaru   : $LATEST   ($DIR)"

if [ "$FORCE" = "0" ]; then
  if [ "$CUR" = "$LATEST" ]; then
    echo "==> Sudah di versi terbaru. Tidak ada yang diubah."
    echo "    Untuk memaksa timpa ulang (mode perbaikan):  bash update.sh --force"
    exit 0
  fi
  # VERSION korup/kosong tidak boleh dianggap "lebih baru" oleh sort -V (string non-numerik
  # selalu terurut setelah angka) — kalau tidak valid, anggap belum diketahui lalu lanjut timpa.
  if [ -n "$CUR" ] && ! printf '%s' "$CUR" | grep -qE '^[0-9]+(\.[0-9]+)+$'; then
    echo "==> VERSION di server tidak valid ('$CUR') — dianggap belum diketahui, master v$LATEST akan ditimpakan."
    CUR=""
  fi
  if [ -n "$CUR" ] && [ "$(printf '%s\n%s\n' "$CUR" "$LATEST" | sort -V | tail -1)" = "$CUR" ]; then
    echo "==> Versi di server ($CUR) lebih baru dari master repo ($LATEST). Berhenti demi aman."
    echo "    Untuk memaksa turun versi:  bash update.sh --force"
    exit 0
  fi
else
  echo "==> Mode --force: master v$LATEST akan ditimpakan apa pun versi terpasangnya."
fi

echo "==> Jalankan restore master v$LATEST ..."
TMP="/tmp/vk_update_$(date +%Y%m%d_%H%M%S).sh"
wget -qO "$TMP" "$REPO_RAW/patch/$DIR/restore_master.sh" || { echo "ERROR: gagal unduh restore_master.sh v$LATEST"; exit 1; }
[ -s "$TMP" ] || { echo "ERROR: restore_master.sh kosong."; exit 1; }
bash -n "$TMP" 2>/dev/null || { echo "ERROR: restore_master.sh rusak (syntax) — tidak dijalankan."; exit 1; }

VK_BASE_URL="$REPO_RAW/patch/$DIR" bash "$TMP"
RC=$?
rm -f "$TMP"
if [ $RC -eq 0 ]; then echo "==> Sistem patch SELESAI (v$LATEST)."; else echo "==> Sistem patch GAGAL (exit $RC) — baca pesan di atas, backup aman."; fi
exit $RC