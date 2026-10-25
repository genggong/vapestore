#!/usr/bin/env python3
"""Test kontrak v17.0.47 — fix edit tarif kurir + laba bersih dikurangi ongkir + kartu Biaya Operasional."""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []


def cek(n, c, d=''):
    print(('  [OK] ' if c else '  [!!] ') + n + (f' — {d}' if d and not c else ''))
    if not c:
        failed.append(n)


# 1) edit tarif kurir: window.fillEdit + tidak shadow d
for t in ('legacy/public', 'public', 'pos/public'):
    s = (ROOT / f'{t}/admin/marketplace-courier-rates/index.php').read_text()
    cek(f'[1] fillEdit global ({t})', 'window.fillEdit=fillEdit' in s or 'window.fillEdit = fillEdit' in s)
    cek(f'[2] tidak shadow d ({t})', 'const data=JSON.parse(btn.closest' in s and "setOne(document.getElementById('rateDistrict')" in s.replace('const d2=', 'const d2=') or 'p2=document.getElementById' in s)
    cek(f'[3] lint kurir ({t})', 'No syntax errors' in subprocess.run(['php', '-l', str(ROOT / f'{t}/admin/marketplace-courier-rates/index.php')], capture_output=True, text=True).stdout)

# 2) accounting: ongkir dikurangi
for t in ('legacy/app', 'app', 'pos/app'):
    s = (ROOT / f'{t}/Modules/Accounting/Accounting.php').read_text()
    cek(f'[4] net_profit minus ongkir ({t})', "['shipping_income'];" in s and "['operating_expense'] - $branch['shipping_income']" in s)
    cek(f'[5] net_profit harian minus ongkir ({t})', "- $day['shipping_income'];" in s)

# 3) kartu Biaya Operasional
for t in ('legacy/public', 'public', 'pos/public'):
    s = (ROOT / f'{t}/admin/accounting/index.php').read_text()
    cek(f'[6] kartu Biaya Operasional ({t})', 'Biaya Operasional' in s and "'operating_expense']); ?>" in s)

# VERSION
cek('[7] VERSION', (ROOT / 'VERSION').read_text().strip() == '17.0.47', (ROOT / 'VERSION').read_text().strip())

print()
if failed:
    print(f'GAGAL: {len(failed)} → {failed}')
    sys.exit(1)
print('SEMUA CEK LULUS')
