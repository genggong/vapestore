#!/usr/bin/env bash
# V17.0.22 — Ongkir & penjualan online sampai ke laporan Akuntansi:
#   - kartu KPI "Pendapatan Ongkir" di halaman Akuntansi (3 tree)
#   - jalur pencatatan order online -> transactions (reconcile + shipping_cost) versi terkini
#   - tool backfill: catat order lunas+selesai yang belum masuk + isi ongkir pada transaksi 0
set -uo pipefail

VERSION="17.0.22"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.22/PATCH_v17.0.22.zip"
ZIP="PATCH_${VERSION}.zip"
BACKUP_DIR="backup_${VERSION}_$(date +%Y%m%d_%H%M%S)"

if [ ! -f index.php ]; then
  echo "ERROR: tidak menemukan index.php di folder ini. Jalankan dari docroot." >&2
  exit 1
fi

echo "==> Docroot: $(pwd)"
echo "==> Backup file lama ke: ${BACKUP_DIR}/"
mkdir -p "${BACKUP_DIR}"
for f in \
  legacy/public/admin/accounting/index.php legacy/public/admin/accounting/export.php \
  public/admin/accounting/index.php public/admin/accounting/export.php \
  pos/public/admin/accounting/index.php pos/public/admin/accounting/export.php \
  app/Modules/Accounting/Accounting.php legacy/app/Modules/Accounting/Accounting.php pos/app/Modules/Accounting/Accounting.php \
  app/Modules/Marketplace/Marketplace.php legacy/app/Modules/Marketplace/Marketplace.php pos/app/Modules/Marketplace/Marketplace.php \
  app/Modules/Marketplace/StoreCourier.php legacy/app/Modules/Marketplace/StoreCourier.php pos/app/Modules/Marketplace/StoreCourier.php \
  app/Modules/Marketplace/StorePickup.php legacy/app/Modules/Marketplace/StorePickup.php pos/app/Modules/Marketplace/StorePickup.php \
  legacy/public/admin/marketplace-orders/index.php legacy/public/admin/marketplace-orders/action.php \
  public/admin/marketplace-orders/index.php public/admin/marketplace-orders/action.php \
  pos/public/admin/marketplace-orders/index.php pos/public/admin/marketplace-orders/action.php ; do
  [ -f "$f" ] || continue
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  cp -p "$f" "${BACKUP_DIR}/${f}.bak" 2>/dev/null || true
done

echo "==> Unduh patch..."
if ! wget -qO "${ZIP}" "${ZIP_URL}"; then
  echo "!! Gagal mengunduh patch. Docroot TIDAK diubah." >&2; exit 1
fi
if ! unzip -tq "${ZIP}" >/dev/null; then
  echo "!! Arsip patch rusak. Docroot TIDAK diubah." >&2; rm -f "${ZIP}"; exit 1
fi
unzip -o "${ZIP}"
rm -f "${ZIP}"

echo "==> Verifikasi marker..."
OK=1
for f in legacy/public/admin/accounting/index.php legacy/app/Modules/Accounting/Accounting.php \
         legacy/app/Modules/Marketplace/Marketplace.php legacy/public/admin/marketplace-orders/index.php \
         tools/backfill_online_ongkir.php ; do
  [ -s "$f" ] || { echo "  [MISSING] $f"; OK=0; continue; }
  echo "  [OK] $f"
done
[ $OK -eq 1 ] || { echo "!! File patch tidak lengkap (file yang sudah ter-unzip tetap tertimpa)."; exit 1; }

grep -q "Pendapatan Ongkir" legacy/public/admin/accounting/index.php \
  || { echo "!! Kartu Pendapatan Ongkir tidak ada di halaman akuntansi."; exit 1; }
grep -q "pos_recorded_at IS NULL" legacy/public/admin/marketplace-orders/index.php \
  || { echo "!! Reconcile order online tidak ada di halaman pesanan online."; exit 1; }
grep -q "shipping_cost" legacy/app/Modules/Marketplace/Marketplace.php \
  || { echo "!! Modul marketplace tidak mencatat shipping_cost."; exit 1; }
echo "  [OK] KPI ongkir + jalur pencatatan order online terpasang"

echo "==> Jalankan backfill ongkir/penjualan online..."
if [ -f tools/backfill_online_ongkir.php ]; then
  php tools/backfill_online_ongkir.php || echo "!! Backfill gagal — jalankan manual: php tools/backfill_online_ongkir.php"
else
  echo "    (tool backfill tidak ada)"
fi

cat <<'NOTE'

==> SELESAI. Patch v17.0.22 terpasang.
==> Purge All (LiteSpeed Web Cache Manager), lalu buka:
      /admin/accounting/index.php        -> kartu "Pendapatan Ongkir" terisi
      /admin/marketplace-orders/index.php -> order lunas+selesai otomatis dicatat ke transaksi POS
==> Order online baru ke depan otomatis tercatat (tanpa perlu tool ini lagi).
==> Rollback: for b in $(find backup_17.0.22_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
NOTE
echo "==> Folder backup: ${BACKUP_DIR}"