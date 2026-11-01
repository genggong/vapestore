#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

# checkout variants
for v,pref in [('public','public'),('legacy','legacy/public'),('pos','pos/public')]:
    p = ROOT/(pref+'/store/checkout.php')
    s = p.read_text()
    cek(f"[1] isCasaku didefinisikan ({v})", "$isCasaku = (function_exists('marketplace_payment_gateway'" in s)
    cek(f"[2] VA dibungkus if !isCasaku ({v})", "<?php if (!$isCasaku): ?>" in s and "value=\"bri_va\"" in s)
    cek(f"[3] VA lain dalam blok ({v})", "value=\"cimb_niaga_va\"" in s and "<?php endif; ?>" in s)
    cek(f"[4] QRIS selalu tampil ({v})", "value=\"qris\"" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[5] lint checkout ({v})", 'No syntax errors' in r.stdout)

# module variants: gateway default casaku
for v,pref in [('app','app'),('legacy','legacy/app'),('pos','pos/app')]:
    p = ROOT/(pref+'/Modules/Marketplace/CasaKuPayment.php')
    s = p.read_text()
    cek(f"[6] gateway default casaku ({v})", "setting('marketplace_payment_gateway', 'casaku')" in s and "? $g : 'casaku'" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[7] lint module ({v})", 'No syntax errors' in r.stdout)

cek('[8] VERSION == 17.0.59', (ROOT/'VERSION').read_text().strip()=='17.0.59', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.59/PATCH_v17.0.59.zip'
cek('[9] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = [pref+'/store/checkout.php' for pref in ['public','legacy/public','pos/public']] + \
                 [pref+'/Modules/Marketplace/CasaKuPayment.php' for pref in ['app','legacy/app','pos/app']] + ['VERSION']
        cek('[10] zip memuat 7 entry', set(names)==set(expect), str(names))
        allmatch = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[11] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
