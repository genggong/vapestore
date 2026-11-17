#!/usr/bin/env python3
"""Test patch v17.0.76 — restore Kurir Toko + hapus Pakasir + teks WA referral + kolom referral."""
import pathlib, hashlib, subprocess, zipfile
ROOT = pathlib.Path('/home/ubuntu/vapestore')
VERSION = '17.0.76'
ADM = ['public','legacy/public','pos/public']
failed = []
def check(n,c):
    print(('  [OK] ' if c else '  [!!] ')+n)
    if not c: failed.append(n)
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()

js = ['assets/js/marketplace-checkout.js','public/assets/js/marketplace-checkout.js','legacy/public/assets/js/marketplace-checkout.js','pos/public/assets/js/marketplace-checkout.js']
for f in js:
    t=(ROOT/f).read_text()
    check(f'JS {f}: restore kurir (resolve)', 'resolveChosenDestination' in t and 'form.dataset.resolveUrl' in t)
    check(f'JS {f}: node ok', subprocess.run(['node','--check',str(ROOT/f)],capture_output=True).returncode==0)
for v in ADM:
    s=(ROOT/v/'admin/settings/index.php').read_text()
    check(f'{v}: pakasir setting dihapus', 'pakasir_project' not in s and 'pakasir_api_key' not in s)
    check(f'{v}: teks WA referral', 'pos_referral_wa_message' in s and '{total_poin}' in s)
    st=(ROOT/v/'admin/casaku/status.php').read_text()
    check(f'{v}: status pakai setting WA', "setting('pos_referral_wa_message'" in st)
    cu=(ROOT/v/'admin/customers/index.php').read_text()
    check(f'{v}: kolom referral pelanggan', 'total_referral' in cu and 'Referral' in cu)
    check(f'{v}: js cache 17.0.76', 'marketplace-checkout.js?v=17.0.76' in (ROOT/v/'store/checkout.php').read_text())
for v in ['app','legacy/app','pos/app']:
    m=(ROOT/v/'Modules/Marketplace/Marketplace.php').read_text()
    check(f'{v}: msg JSON diagnostik', 'Response API bukan JSON yang valid (' in m)
check('legacy _header css 17.0.76', "storefront.css?v=17.0.76" in (ROOT/'legacy/public/store/_header.php').read_text())
for v in ADM:
    for f in ['admin/settings/index.php','admin/casaku/status.php','admin/customers/index.php']:
        check(f'lint {v}/{f}', 'No syntax errors' in subprocess.run(['php','-l',str(ROOT/v/f)],capture_output=True,text=True).stdout)
for v in ['app','legacy/app','pos/app']:
    check(f'lint {v}/Marketplace.php', 'No syntax errors' in subprocess.run(['php','-l',str(ROOT/v/'Modules/Marketplace/Marketplace.php')],capture_output=True,text=True).stdout)
check('VERSION == 17.0.76', ROOT.joinpath('VERSION').read_text().strip()==VERSION)
zip_path=ROOT/f'patch/v{VERSION}/PATCH_v{VERSION}.zip'
check('release zip exists', zip_path.exists())
if zip_path.exists():
    expected = js + [f'{v}/admin/settings/index.php' for v in ADM] + [f'{v}/admin/casaku/status.php' for v in ADM] + [f'{v}/admin/customers/index.php' for v in ADM] + [f'{v}/Modules/Marketplace/Marketplace.php' for v in ['app','legacy/app','pos/app']] + [f'{v}/store/checkout.php' for v in ADM] + ['legacy/public/store/_header.php','VERSION']
    with zipfile.ZipFile(zip_path) as z:
        check('release zip entries', sorted(z.namelist())==sorted(expected))
        check('release zip matches source', all(hashlib.sha256(z.read(n)).hexdigest()==sha(ROOT/n) for n in expected))
print()
if failed:
    print('GAGAL: '+', '.join(failed)); raise SystemExit(1)
print('SEMUA CEK LULUS')
