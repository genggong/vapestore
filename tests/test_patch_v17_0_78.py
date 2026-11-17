#!/usr/bin/env python3
"""Test patch v17.0.78 — Kurir Toko kembali via resolve selalu (id cocok tarif)."""
import pathlib, hashlib, subprocess, zipfile
ROOT=pathlib.Path('/home/ubuntu/vapestore'); VERSION='17.0.78'; failed=[]
def check(n,c):
    print(('  [OK] ' if c else '  [!!] ')+n)
    if not c: failed.append(n)
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()

js=['assets/js/marketplace-checkout.js','public/assets/js/marketplace-checkout.js','legacy/public/assets/js/marketplace-checkout.js','pos/public/assets/js/marketplace-checkout.js']
for f in js:
    t=(ROOT/f).read_text()
    check(f'JS {f}: selalu resolve', 'async function resolveChosenDestination(row)' in t and 'form.dataset.resolveUrl' in t)
    check(f'JS {f}: tidak bypas kelurahan', 'if (!(row.subdistrict && row.district && row.city))' not in t)
    check(f'JS {f}: node ok', subprocess.run(['node','--check',str(ROOT/f)],capture_output=True).returncode==0)
for v in ['public','legacy/public','pos/public']:
    r=(ROOT/v/'store/api/resolve-destination.php').read_text()
    check(f'{v}: resolve terima kelurahan saja', "if ($subdistrict === '')" in r and 'sampai Kelurahan/Desa' not in r)
    check(f'{v}: js cache 17.0.78', 'marketplace-checkout.js?v=17.0.78' in (ROOT/v/'store/checkout.php').read_text())
    check(f'{v}: lint resolve', 'No syntax errors' in subprocess.run(['php','-l',str(ROOT/v/'store/api/resolve-destination.php')],capture_output=True,text=True).stdout)
check('legacy _header css 17.0.78', "storefront.css?v=17.0.78" in (ROOT/'legacy/public/store/_header.php').read_text())
check('VERSION == 17.0.78', ROOT.joinpath('VERSION').read_text().strip()==VERSION)
zp=ROOT/f'patch/v{VERSION}/PATCH_v{VERSION}.zip'; check('release zip exists', zp.exists())
if zp.exists():
    exp=js+[f'{v}/store/api/resolve-destination.php' for v in ['public','legacy/public','pos/public']]+[f'{v}/store/checkout.php' for v in ['public','legacy/public','pos/public']]+['legacy/public/store/_header.php','VERSION']
    with zipfile.ZipFile(zp) as z:
        check('release zip entries', sorted(z.namelist())==sorted(exp))
        check('release zip matches source', all(hashlib.sha256(z.read(n)).hexdigest()==sha(ROOT/n) for n in exp))
print()
if failed:
    print('GAGAL: '+', '.join(failed)); raise SystemExit(1)
print('SEMUA CEK LULUS')
