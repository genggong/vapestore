#!/usr/bin/env python3
"""Test patch v17.0.75 — autocomplete tunggal wilayah checkout."""
import pathlib
import hashlib
import subprocess
import zipfile

ROOT = pathlib.Path('/home/ubuntu/vapestore')
VERSION = '17.0.75'
STORES = ['public', 'legacy/public', 'pos/public']
failed = []


def check(name, cond):
    print(('  [OK] ' if cond else '  [!!] ') + name)
    if not cond:
        failed.append(name)


def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()


for v in STORES:
    t = (ROOT / v / 'store/checkout.php').read_text()
    check(f'{v}: autocomplete input', 'id="destinationAutocomplete"' in t)
    check(f'{v}: data-dest-url', 'data-dest-url=' in t)
    check(f'{v}: dropdown listbox', 'dstAutocompleteDropdown' in t)
    check(f'{v}: dropdown bertingkat lama dihapus', 'destinationProvinceSelect' not in t)

# JS (root served + tri-tree)
js_files = ['assets/js/marketplace-checkout.js',
            'public/assets/js/marketplace-checkout.js',
            'legacy/public/assets/js/marketplace-checkout.js',
            'pos/public/assets/js/marketplace-checkout.js']
for rel in js_files:
    t = (ROOT / rel).read_text()
    check(f'JS {rel}: debounce 400ms', 'destDebounce = setTimeout(() => destSearch(val), 400)' in t)
    check(f'JS {rel}: min 3 huruf', 'val.length < 3' in t)
    check(f'JS {rel}: wiring chooseDestination', 'chooseDestination({' in t)

# CSS
for rel in ['assets/css/storefront.css', 'public/assets/css/storefront.css',
            'legacy/public/assets/css/storefront.css', 'pos/public/assets/css/storefront.css']:
    t = (ROOT / rel).read_text()
    check(f'CSS {rel}: dropdown', '.dst-autocomplete-dropdown' in t)

# lint
for v in STORES:
    r = subprocess.run(['php', '-l', str(ROOT / v / 'store/checkout.php')], capture_output=True, text=True)
    check(f'lint {v}/checkout.php', 'No syntax errors' in r.stdout)
for f in js_files:
    r = subprocess.run(['node', '--check', str(ROOT / f)], capture_output=True, text=True)
    check(f'node --check {f}', r.returncode == 0)

check('VERSION == 17.0.75', ROOT.joinpath('VERSION').read_text().strip() == VERSION)
hdr = (ROOT / 'legacy/public/store/_header.php').read_text()
check('legacy _header cache v17.0.75', "storefront.css?v=17.0.75" in hdr)
for v in STORES:
    check(f'{v}: js cache v17.0.75', 'assets/js/marketplace-checkout.js?v=17.0.75' in (ROOT / v / 'store/checkout.php').read_text())
zip_path = ROOT / f'patch/v{VERSION}/PATCH_v{VERSION}.zip'
check('release zip exists', zip_path.exists())
if zip_path.exists():
    expected = []
    for v in STORES:
        expected.append(f'{v}/store/checkout.php')
    expected += [
        'assets/js/marketplace-checkout.js',
        'assets/css/storefront.css',
        'public/assets/js/marketplace-checkout.js',
        'public/assets/css/storefront.css',
        'legacy/public/assets/js/marketplace-checkout.js',
        'legacy/public/assets/css/storefront.css',
        'legacy/public/store/_header.php',
        'pos/public/assets/js/marketplace-checkout.js',
        'pos/public/assets/css/storefront.css',
        'VERSION',
    ]
    with zipfile.ZipFile(zip_path) as z:
        check('release zip entries', sorted(z.namelist()) == sorted(expected))
        check('release zip matches source', all(
            hashlib.sha256(z.read(n)).hexdigest() == sha(ROOT / n) for n in expected))

print()
if failed:
    print('GAGAL: ' + ', '.join(failed))
    raise SystemExit(1)
print('SEMUA CEK LULUS')
