#!/usr/bin/env bash
# V17.0.23 — 1) Nilai modal stok Dashboard disamakan dengan Akuntansi
#            2) Menu Gudang > "Edit Stok" (ubah stok, min stok, harga modal — termasuk varian)
#            3) Tool reset stok gudang (dijalankan manual, lihat akhir output)
set -uo pipefail

VERSION="17.0.23"
ZIP_URL="https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.23/PATCH_v17.0.23.zip"
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
  legacy/public/admin/dashboard.php public/admin/dashboard.php pos/public/admin/dashboard.php \
  legacy/public/admin/warehouse/edit-stock.php public/admin/warehouse/edit-stock.php pos/public/admin/warehouse/edit-stock.php \
  legacy/public/layouts/admin-sidebar.php public/layouts/admin-sidebar.php pos/public/layouts/admin-sidebar.php \
  app/Modules/Accounting/Accounting.php legacy/app/Modules/Accounting/Accounting.php pos/app/Modules/Accounting/Accounting.php \
  app/Modules/System/SystemUpgrade.php legacy/app/Modules/System/SystemUpgrade.php pos/app/Modules/System/SystemUpgrade.php \
  legacy/public/admin/system-upgrade/index.php ; do
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
for f in legacy/public/admin/dashboard.php legacy/public/admin/warehouse/edit-stock.php \
         legacy/public/layouts/admin-sidebar.php legacy/app/Modules/Accounting/Accounting.php ; do
  [ -s "$f" ] || { echo "  [MISSING] $f"; OK=0; continue; }
  echo "  [OK] $f"
done
[ $OK -eq 1 ] || { echo "!! File patch tidak lengkap (file yang sudah ter-unzip tetap tertimpa)."; exit 1; }

grep -q "accounting_inventory_value" legacy/public/admin/dashboard.php \
  || { echo "!! Dashboard belum memakai perhitungan akuntansi."; exit 1; }
grep -q "function accounting_inventory_value" legacy/app/Modules/Accounting/Accounting.php \
  || { echo "!! Fungsi nilai persediaan bersama tidak ada di modul akuntansi."; exit 1; }
grep -q "warehouse_require_manage()" legacy/public/admin/warehouse/edit-stock.php \
  || { echo "!! Halaman Edit Stok tidak lengkap."; exit 1; }
grep -q "admin/warehouse/edit-stock.php" legacy/public/layouts/admin-sidebar.php \
  || { echo "!! Menu Edit Stok tidak terdaftar di sidebar."; exit 1; }
echo "  [OK] dashboard = akuntansi, halaman & menu Edit Stok terpasang"

echo
echo "==> SELESAI. Patch v${VERSION} terpasang."
echo "==> Purge All, lalu cek:"
echo "      /admin/dashboard.php              -> Nilai Modal Stok = angka Akuntansi"
echo "      /admin/accounting/index.php       -> KPI Nilai Persediaan (harus sama)"
echo "      /admin/warehouse/edit-stock.php   -> menu Gudang > Edit Stok"
echo
echo "==> UNTUK MENGOSONGKAN STOK GUDANG (mis. setelah tes pembelian) — dijalankan manual:"
echo "      php tools/reset_warehouse_stock.php            # dry run, hanya menampilkan"
echo "      php tools/reset_warehouse_stock.php --apply    # benar-benar menolkan stok gudang"
echo "    (otomatis membuat tabel backup + mencatat pergerakan; stok cabang & laporan tidak diubah)"
echo
echo "==> Rollback file: for b in \$(find ${BACKUP_DIR} -name '*.bak'); do cp -p \"\$b\" \"\${b%.bak}\"; done"
echo "==> Folder backup: ${BACKUP_DIR}"