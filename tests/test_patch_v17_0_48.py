#!/usr/bin/env python3
"""Test kontrak v17.0.48 — menu Input Operasional untuk kasir."""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path('/home/ubuntu/vapestore')
TREES = ('legacy/public', 'public', 'pos/public')
APPT = ('legacy/app', 'app', 'pos/app')
failed = []


def cek(n, c, d=''):
    print(('  [OK] ' if c else '  [!!] ') + n + (f' — {d}' if d and not c else ''))
    if not c:
        failed.append(n)


# 1) halaman operasional di 3 pohon
for t in TREES:
    p = ROOT / f'{t}/admin/operasional/index.php'
    cek(f'[1] halaman operasional ({t})', p.is_file())
    s = p.read_text()
    cek(f'[2] guard kasir ({t})', 'guard_finance_operational()' in s)
    cek(f'[3] insert finance_transactions ({t})', 'INSERT INTO finance_transactions' in s and "'expense'" in s and 'reference_type' in s)
    cek(f'[4] kategori Operasional otomatis ({t})', "name='Operasional' AND type='expense'" in s)
    cek(f'[5] lint ({t})', 'No syntax errors' in subprocess.run(['php','-l',str(p)],capture_output=True,text=True).stdout)

# 2) Auth guard di 3 tree
for t in APPT:
    s = (ROOT / f'{t}/Core/Auth.php').read_text()
    cek(f'[6] guard_finance_operational ({t})', 'guard_finance_operational' in s and "'finance.operational'" in s)

# 3) sidebar: menu di bawah Request Stock
for t in TREES:
    s = (ROOT / f'{t}/layouts/admin-sidebar.php').read_text()
    cek(f'[7] menu sidebar ({t})', 'finance.operational' in s and 'operasional/index.php' in s and 'Input Operasional' in s)

# 4) role/permission
for t in TREES:
    s = (ROOT / f'{t}/admin/roles/_helpers.php').read_text()
    cek(f'[8] perm finance.operational grup ({t})', "'finance.operational' => ['Input Operasional'" in s)
    cek(f'[9] cashier default ({t})', "'finance.operational'," in s)
    cek(f'[10] cashier topup ({t})', "slug='cashier'" in s)

# 5) finance guard (kasir dilarang)
for t in TREES:
    s = (ROOT / f'{t}/admin/finance/index.php').read_text()
    cek(f'[11] finance guard ({t})', "requirePermission('finance.view')" in s)

cek('[12] VERSION', (ROOT / 'VERSION').read_text().strip() == '17.0.48', (ROOT / 'VERSION').read_text().strip())

print()
if failed:
    print(f'GAGAL: {len(failed)} → {failed}')
    sys.exit(1)
print('SEMUA CEK LULUS')
