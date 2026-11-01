#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

def _ver(n):
    try: return tuple(int(x) for x in str(n).split('.'))
    except Exception: return (0,)

VARIANTS = {'app':'app','legacy':'legacy/app','pos':'pos/app'}
# v17.0.55 — packageIds CasaKu -> ['com.bca.msb']
for v,pref in VARIANTS.items():
    p = ROOT/(pref+'/Modules/Marketplace/CasaKuPayment.php')
    s = p.read_text()
    cek(f"[1] packageIds com.bca.msb ({v})", "'packageIds' => ['com.bca.msb']" in s)
    cek(f"[2] packageIds [] hilang ({v})", "'packageIds' => []," not in s)
    cek(f"[3] casaku_create utuh ({v})", '/api/generate/v2/qris' in s and "'useQris' => true" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[4] lint ({v})", 'No syntax errors' in r.stdout)

cek('[5] VERSION == 17.0.55', (ROOT/'VERSION').read_text().strip()=='17.0.55', (ROOT/'VERSION').read_text().strip())

# zip == source (byte-identical per entry)
zp = ROOT/'patch/v17.0.55/PATCH_v17.0.55.zip'
cek('[6] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = ['app/Modules/Marketplace/CasaKuPayment.php',
                  'legacy/app/Modules/Marketplace/CasaKuPayment.php',
                  'pos/app/Modules/Marketplace/CasaKuPayment.php','VERSION']
        cek('[7] zip memuat 4 entry', set(names)==set(expect), str(names))
        allmatch = True
        for n in expect:
            ze = z.read(n)
            disk = (ROOT/n).read_bytes()
            if hashlib.sha256(ze).hexdigest() != hashlib.sha256(disk).hexdigest():
                allmatch = False; cek(f"[8] zip==source ({n})", False)
        if allmatch:
            cek('[8] zip==source semua entry', True)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
