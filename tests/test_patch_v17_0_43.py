#!/usr/bin/env python3
"""Test kontrak v17.0.43 — Stok Opname Gudang."""
import pathlib
import re
import subprocess
import sys

ROOT = pathlib.Path('/home/ubuntu/vapestore')
TREES = ('legacy/public', 'public', 'pos/public')
failed = []


def cek(nama, kondisi, detail=''):
    print(('  [OK] ' if kondisi else '  [!!] ') + nama + (f' — {detail}' if detail and not kondisi else ''))
    if not kondisi:
        failed.append(nama)


def read(p):
    return (ROOT / p).read_text()


# 1. berkas modul ada di 3 pohon
for t in TREES:
    for f in ('_helpers.php', 'index.php', 'create.php', 'edit.php', 'finalize.php', 'template.php', 'import.php'):
        p = f'{t}/admin/stock-opname-gudang/{f}'
        cek(f'berkas {p}', (ROOT / p).is_file())

# 2. helper
h = read('legacy/public/admin/stock-opname-gudang/_helpers.php')
cek('tabel warehouse_opnames & items', 'CREATE TABLE IF NOT EXISTS warehouse_opnames' in h and 'warehouse_opname_items' in h)
cek('seed melewati stok 0', 'if (!$includeZero && $stock <= 0) continue;' in h)
cek('seed baca warehouse_stocks', 'FROM warehouse_stocks WHERE warehouse_id=?' in h)
cek('sync totals', 'function wopname_sync_totals' in h)

# 3. template: stok 0 tidak masuk + kolom physical_stock
tpl = read('legacy/public/admin/stock-opname-gudang/template.php')
cek('template filter stok 0', "if (!$includeZero && $stock <= 0) { $skipped++; continue; }" in tpl)
cek('template header physical_stock', "'physical_stock'" in tpl)
cek('template pakai warehouse_stocks', 'warehouse_stocks ws ON ws.product_id=p.id' in tpl)
cek('template include_zero opsional', "!empty($_GET['include_zero'])" in tpl)

# 4. finalisasi menulis stok gudang + kartu stok
fin = read('legacy/public/admin/stock-opname-gudang/finalize.php')
cek('finalize update warehouse_stocks', 'UPDATE warehouse_stocks SET stock=?' in fin)
cek('finalize insert bila belum ada', 'INSERT INTO warehouse_stocks' in fin)
cek('finalize catat stock_movements', "reference_type,reference_id" in fin and "'warehouse_opname'" in fin)
cek('finalize guard status draft', "status='draft'" in fin and 'beginTransaction' in fin)

# 5. edit: selisih = fisik - sistem
ed = read('legacy/public/admin/stock-opname-gudang/edit.php')
cek('edit hitung selisih', '$diff = $val - (int) $row[\'system_stock\'];' in ed)
cek('edit hanya draft bisa diubah', "!== 'draft'" in ed)

# 6. import: butuh name + physical_stock, lewati baris kosong
imp = read('legacy/public/admin/stock-opname-gudang/import.php')
cek('import deteksi header', "in_array('physical_stock', $norm, true)" in imp)
cek('import lewati baris tanpa fisik', "if ($raw === '') continue;" in imp)
cek('import cocokkan product_id/kode/nama', 'findById' in imp and 'findByCode' in imp and 'findByName' in imp)

# 7. menu sidebar di 3 pohon
for t in TREES:
    s = read(f'{t}/layouts/admin-sidebar.php')
    cek(f'menu Opname Gudang di {t}', 'admin/stock-opname-gudang/index.php' in s and "'Opname Gudang'" in s)

# 8. CSS kelas baru tersedia di 4 salinan
for c in ('assets/css', 'legacy/public/assets/css', 'public/assets/css', 'pos/public/assets/css'):
    s = read(f'{c}/stock-opname.css')
    cek(f'css {c}', '.opname-input' in s and '.opname-chip' in s and '.opname-search' in s)

# 9. VERSION & lint
cek('VERSION 17.0.43', read('VERSION').strip() == '17.0.43', read('VERSION').strip())
lint_fail = []
for t in TREES:
    for f in (ROOT / f'{t}/admin/stock-opname-gudang').glob('*.php'):
        r = subprocess.run(['php', '-l', str(f)], capture_output=True, text=True)
        if 'No syntax errors' not in r.stdout:
            lint_fail.append(f)
cek('php -l semua berkas modul', not lint_fail, str(lint_fail))

print()
if failed:
    print(f'GAGAL: {len(failed)} cek → {failed}')
    sys.exit(1)
print('SEMUA CEK LULUS')
