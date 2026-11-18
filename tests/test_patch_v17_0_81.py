#!/usr/bin/env python3
"""Test v17.0.81 — WA nota QRIS Casaku bersih (strip JSON) + label 'QRIS Otomatis'."""
import pathlib, hashlib, zipfile, subprocess
ROOT=pathlib.Path('/home/ubuntu/vapestore'); VERSION='17.0.81'; failed=[]
def ck(n,c):
    print(('  [OK] ' if c else '  [!!] ')+n)
    if not c: failed.append(n)
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()

helpers=['pos/public/admin/whatsapp/_helpers.php','public/admin/whatsapp/_helpers.php','legacy/public/admin/whatsapp/_helpers.php']
for f in helpers:
    s=(ROOT/f).read_text()
    ck(f'h:{f} strip CASAKU', r'\[CASAKU_PAYMENT\].*?\[\/CASAKU_PAYMENT\]' in s)
    ck(f'h:{f} ganti teks QRIS Otomatis', "Pembayaran QRIS Otomatis" in s)
    ck(f'h:{f} lint', 'No syntax errors' in subprocess.run(['php','-l',str(ROOT/f)],capture_output=True,text=True).stdout)
labels=['pos/app/Helpers/helpers.php','app/Helpers/helpers.php','legacy/app/Helpers/helpers.php',
        'pos/app/Modules/Accounting/Accounting.php','app/Modules/Accounting/Accounting.php','legacy/app/Modules/Accounting/Accounting.php']
for f in labels:
    ck(f'l:{f} qris_casaku -> QRIS Otomatis', "'qris_casaku' => 'QRIS Otomatis'" in (ROOT/f).read_text())
ck('VERSION == 17.0.81', ROOT.joinpath('VERSION').read_text().strip()==VERSION)
zp=ROOT/f'patch/v{VERSION}/PATCH_v{VERSION}.zip'; ck('release zip exists', zp.exists())
if zp.exists():
    exp=helpers+labels+['VERSION']
    with zipfile.ZipFile(zp) as z:
        ck('release zip entries', sorted(z.namelist())==sorted(exp))
        ck('release zip matches source', all(hashlib.sha256(z.read(n)).hexdigest()==sha(ROOT/n) for n in exp))
print()
if failed: print('GAGAL: '+', '.join(failed)); raise SystemExit(1)
print('SEMUA CEK LULUS')
