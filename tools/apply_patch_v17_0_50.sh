#!/usr/bin/env bash
# V17.0.50 — payment gateway CasaKu (QRIS) switchable.
set -uo pipefail
VERSION="17.0.50"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.50/PATCH_v17.0.50.zip"
ZIP="PATCH_${VERSION}.zip"; BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"
[ -f index.php ] || { echo "ERROR: jalankan dari docroot." >&2; exit 1; }
wget -qO "${ZIP}" "${ZIP_URL}" || { echo "!! Gagal unduh zip." >&2; rm -f "${ZIP}"; exit 1; }
unzip -tq "${ZIP}" >/dev/null || { echo "!! Arsip rusak." >&2; rm -f "${ZIP}"; exit 1; }
mkdir -p "${BACKUP_DIR}"; while IFS= read -r f; do case "$f" in */) continue;; esac; [ -f "$f" ] || continue; mkdir -p "${BACKUP_DIR}/$(dirname "$f")"; cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true; done < <(unzip -Z1 "${ZIP}")
unzip -o -q "${ZIP}"; rm -f "${ZIP}"
FAIL=0
check() { if [ -f "$1" ] && grep -Fq -- "$2" "$1"; then echo "  [OK] $3"; else echo "  [!!] $3"; FAIL=1; fi; }
check "legacy/app/Modules/Marketplace/CasaKuPayment.php" "marketplace_casaku_create" "modul CasaKu"
check "legacy/app/Modules/Marketplace/Marketplace.php" "marketplace_payment_create(" "dispatcher gateway"
check "legacy/public/marketplace-casaku-webhook.php" "X-Casaku-Signature" "webhook HMAC"
check "legacy/public/admin/marketplace-settings/index.php" "CasaKu (QRIS Otomatis)" "pengaturan CasaKu"
check "VERSION" '17.0.50' "VERSION 17.0.50"
if [ $FAIL -ne 0 ]; then echo "!! Rollback: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"; exit 1; fi
echo
echo "==> SELESAI v${VERSION}. ZIP diextract & dihapus."
echo "==> Aktifkan: Pengaturan Marketplace -> Gateway = CasaKu + License Key + Webhook Secret."
echo "==> Tempel Webhook URL (dapat di kartu CasaKu) ke dashboard casaku.id."
echo "==> Wajib: LiteSpeed Web Cache Manager -> Purge All, lalu Ctrl+F5."
echo "==> Backup: ${BACKUP_DIR}"
