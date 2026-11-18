#!/usr/bin/env python3
"""Test patch v17.0.80 — autocomplete wilayah checkout + parse label RajaOngkir (Kurir Toko & RajaOngkir)."""
import pathlib, hashlib, subprocess, zipfile
ROOT=pathlib.Path('/home/ubuntu/vapestore'); VERSION='17.0.80'; failed=[]
def check(n,c):
    print(('  [OK] ' if c else '  [!!] ')+n)
    if not c: failed.append(n)
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()

# Marketplace parse label — 3 varian
for v in ['app','legacy/app','pos/app']:
    t=(ROOT/f'{v}/Modules/Marketplace/Marketplace.php').read_text()
    check(f'{v}: parse label ada', 'marketplace_rajaongkir_parse_label' in t and 'RajaOngkir domestic-destination hanya memberi label' in t)
    check(f'{v}: lint', 'No syntax errors' in subprocess.run(['php','-l',str(ROOT/f'{v}/Modules/Marketplace/Marketplace.php')],capture_output=True,text=True).stdout)

# checkout.php — autocomplete + teks + versi — 3 varian
for v in ['public','legacy/public','pos/public']:
    c=(ROOT/f'{v}/store/checkout.php').read_text()
    check(f'{v}: autocomplete markup', 'data-dest-url' in c and 'id="destinationAutocomplete"' in c)
    check(f'{v}: placeholder teks (tanpa kata "min")', 'placeholder="Isi Kelurahan / Kecamatan / Kota"' in c)
    check(f'{v}: tanpa teks "minimal 3 huruf"', 'minimal 3 huruf' not in c and 'min. 3 huruf' not in c)
    check(f'{v}: js cache 17.0.80', 'marketplace-checkout.js?v=17.0.80' in c)
    check(f'{v}: lint', 'No syntax errors' in subprocess.run(['php','-l',str(ROOT/f'{v}/store/checkout.php')],capture_output=True,text=True).stdout)

# JS — 4 tree
for f in ['assets/js/marketplace-checkout.js','public/assets/js/marketplace-checkout.js','legacy/public/assets/js/marketplace-checkout.js','pos/public/assets/js/marketplace-checkout.js']:
    t=(ROOT/f).read_text()
    check(f'JS {f}: autocomplete + chooseDestination(id+nama)', 'destinationAutocomplete' in t and 'chooseDestination({ id' in t and 'loadShipping()' in t)
    check(f'JS {f}: tanpa teks min kata', 'minimal 3' not in t and 'min. 3 huruf' not in t)
    check(f'JS {f}: node ok', subprocess.run(['node','--check',str(ROOT/f)],capture_output=True).returncode==0)

# CSS — root + 3
for f in ['assets/css/storefront.css','public/assets/css/storefront.css','legacy/public/assets/css/storefront.css','pos/public/assets/css/storefront.css']:
    check(f'CSS {f}: blok autocomplete', '.dst-autocomplete-dropdown' in (ROOT/f).read_text())

check('_header css 17.0.80', "storefront.css?v=17.0.80" in (ROOT/'legacy/public/store/_header.php').read_text())
check('VERSION == 17.0.80', ROOT.joinpath('VERSION').read_text().strip()==VERSION)

zp=ROOT/f'patch/v{VERSION}/PATCH_v{VERSION}.zip'; check('release zip exists', zp.exists())
if zp.exists():
    exp=['app/Modules/Marketplace/Marketplace.php','legacy/app/Modules/Marketplace/Marketplace.php','pos/app/Modules/Marketplace/Marketplace.php',
         'assets/js/marketplace-checkout.js','public/assets/js/marketplace-checkout.js','legacy/public/assets/js/marketplace-checkout.js','pos/public/assets/js/marketplace-checkout.js',
         'assets/css/storefront.css','public/assets/css/storefront.css','legacy/public/assets/css/storefront.css','pos/public/assets/css/storefront.css',
         'public/store/checkout.php','legacy/public/store/checkout.php','pos/public/store/checkout.php','legacy/public/store/_header.php','VERSION']
    with zipfile.ZipFile(zp) as z:
        check('release zip entries', sorted(z.namelist())==sorted(exp))
        check('release zip matches source', all(hashlib.sha256(z.read(n)).hexdigest()==sha(ROOT/n) for n in exp))
print()
if failed:
    print('GAGAL: '+', '.join(failed)); raise SystemExit(1)
print('SEMUA CEK LULUS')
