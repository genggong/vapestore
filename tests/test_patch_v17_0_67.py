#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

DASH = "payment_method = 'cod' THEN grand_total"
ACC  = "'cod' => 'Tunai'"

for pref in ['public','legacy/public','pos/public']:
    d=(ROOT/(pref+'/admin/dashboard.php')).read_text()
    cek(f"[1] dashboard kas hitung cod ({pref})", DASH in d)
    r=subprocess.run(['php','-l',str(ROOT/(pref+'/admin/dashboard.php'))],capture_output=True,text=True)
    cek(f"[2] lint dashboard ({pref})", 'No syntax errors' in r.stdout)

for pref in ['app','legacy/app','pos/app']:
    a=(ROOT/(pref+'/Modules/Accounting/Accounting.php')).read_text()
    cek(f"[3] akuntansi cod->Tunai ({pref})", ACC in a)
    r=subprocess.run(['php','-l',str(ROOT/(pref+'/Modules/Accounting/Accounting.php'))],capture_output=True,text=True)
    cek(f"[4] lint accounting ({pref})", 'No syntax errors' in r.stdout)

cek('[5] VERSION == 17.0.67', (ROOT/'VERSION').read_text().strip()=='17.0.67', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.67/PATCH_v17.0.67.zip'
cek('[6] zip ada', zp.exists())
if zp.exists():
    expect=[]
    for pref in ['public','legacy/public','pos/public']:
        expect.append(pref+'/admin/dashboard.php')
    for pref in ['app','legacy/app','pos/app']:
        expect.append(pref+'/Modules/Accounting/Accounting.php')
    expect.append('VERSION')
    with zipfile.ZipFile(zp) as z:
        cek('[7] zip memuat 6 entry', set(z.namelist())==set(expect), str(sorted(z.namelist())))
        allm=all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[8] zip==source', allm)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
