#!/usr/bin/env python3
import pathlib, subprocess, sys

ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []

def cek(n, c, d=''):
    print(('  [OK] ' if c else '  [!!] ') + n + (f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

for t in ('legacy/app','app','pos/app'):
    s = (ROOT / f'{t}/Modules/Accounting/Accounting.php').read_text()
    cek(f'[1] no double ongkir ({t})', "['operating_expense'];" in s and "['net_profit'] = $branch['gross_profit'] + $branch['other_income'] - $branch['operating_expense'];" in s)
    cek(f'[2] daily no double ({t})', "['net_profit'] = $day['gross_profit'] + $day['other_income'] - $day['operating_expense'];" in s)
    r = subprocess.run(['php','-l',str(ROOT/f'{t}/Modules/Accounting/Accounting.php')],capture_output=True,text=True)
    cek(f'[3] lint ({t})', 'No syntax errors' in r.stdout)

for w in ('legacy/public','public','pos/public'):
    s = (ROOT/f'{w}/admin/accounting/index.php').read_text()
    cek(f'[4] label ongkir ({w})', 'Ongkir sudah masuk penjualan bersih' in s)

cek('[5] VERSION', (ROOT/'VERSION').read_text().strip()=='17.0.49', (ROOT/'VERSION').read_text().strip())

print()
if failed:
    print(f'GAGAL: {failed}'); sys.exit(1)
print('SEMUA CEK LULUS')
