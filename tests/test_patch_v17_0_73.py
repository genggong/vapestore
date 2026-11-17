#!/usr/bin/env python3
"""Test patch v17.0.73 — kode unik QRIS marketplace + cache POS."""
import pathlib
import subprocess
import zipfile
import hashlib

ROOT = pathlib.Path('/home/ubuntu/vapestore')
MODULES = ['public', 'legacy/public', 'pos/public']
MODULE_DIRS = [ROOT / v.replace('public', 'app') / 'Modules/Marketplace' for v in MODULES]
VERSION = '17.0.73'
failed = []


def check(name, condition):
    print(('  [OK] ' if condition else '  [!!] ') + name)
    if not condition:
        failed.append(name)


# --- Fitur: kode unik QRIS marketplace (di Marketplace.php) ---
for d in ['app', 'legacy/app', 'pos/app']:
    p = ROOT / d / 'Modules/Marketplace/Marketplace.php'
    t = p.read_text()
    check(f'{d}: function qris_unique_code', 'function marketplace_qris_unique_code' in t)
    check(f'{d}: random 1-99', 'random_int(1, 99)' in t)
    check(f'{d}: dipakai di create_order (bukan COD)', "paymentMethod === 'qris'" in t)
    check(f'{d}: catat marker ke customer_note', '[QRIS_UNIQUE_CODE]' in t)
    check(f'{d}: payment_amount menyertakan kode', 'grandAmount += $marketplaceQrisUniqueCode' in t)

# --- Fitur: cache POS (MarketplaceV15.php + SchemaDoctor.php) ---
for d in ['app', 'legacy/app', 'pos/app']:
    v15 = (ROOT / d / 'Modules/Marketplace/MarketplaceV15.php').read_text()
    sd = (ROOT / d / 'Modules/Marketplace/SchemaDoctor.php').read_text()
    check(f'{d}: cache variant_attributes', 'static $cache=[];$key=spl_object_id($db)' in v15)
    check(f'{d}: cache doctor_table_columns', 'static $cache = [];' in sd and 'spl_object_id($db) . \':\' . $table' in sd)

# --- Konsistensi antar varian ---
def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

for rel in ['Modules/Marketplace/Marketplace.php',
            'Modules/Marketplace/MarketplaceV15.php',
            'Modules/Marketplace/SchemaDoctor.php']:
    hashes = set(sha(ROOT / d / rel) for d in ['app', 'legacy/app', 'pos/app'])
    check(f'konsistensi {rel}', len(hashes) == 1)

# --- PHP lint pada file yang diedit ---
for d in ['app', 'legacy/app', 'pos/app']:
    for m in ['Marketplace.php', 'MarketplaceV15.php', 'SchemaDoctor.php']:
        f = ROOT / d / 'Modules/Marketplace' / m
        r = subprocess.run(['php', '-l', str(f)], capture_output=True, text=True)
        check(f'php -l {d}/{m}', 'No syntax errors' in r.stdout)

# --- VERSION & packaging ---
check('VERSION == 17.0.73', ROOT.joinpath('VERSION').read_text().strip() == VERSION)
zip_path = ROOT / f'patch/v{VERSION}/PATCH_v{VERSION}.zip'
check('release zip exists', zip_path.exists())
if zip_path.exists():
    expected = [
        'app/Modules/Marketplace/Marketplace.php',
        'app/Modules/Marketplace/MarketplaceV15.php',
        'app/Modules/Marketplace/SchemaDoctor.php',
        'legacy/app/Modules/Marketplace/Marketplace.php',
        'legacy/app/Modules/Marketplace/MarketplaceV15.php',
        'legacy/app/Modules/Marketplace/SchemaDoctor.php',
        'pos/app/Modules/Marketplace/Marketplace.php',
        'pos/app/Modules/Marketplace/MarketplaceV15.php',
        'pos/app/Modules/Marketplace/SchemaDoctor.php',
        'VERSION',
    ]
    with zipfile.ZipFile(zip_path) as z:
        check('release zip entries', sorted(z.namelist()) == sorted(expected))
        check('release zip matches source',
              all(hashlib.sha256(z.read(n)).hexdigest() == hashlib.sha256((ROOT / n).read_bytes()).hexdigest() for n in expected))

print()
if failed:
    print('GAGAL: ' + ', '.join(failed))
    raise SystemExit(1)
print('SEMUA CEK LULUS')
