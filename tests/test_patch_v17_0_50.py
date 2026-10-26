#!/usr/bin/env python3
import pathlib, subprocess, sys
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

for t in ('legacy/app','app','pos/app'):
    p = ROOT/f'{t}/Modules/Marketplace/CasaKuPayment.php'
    cek(f'[1] modul ({t})', p.is_file())
    s = p.read_text()
    cek(f'[3] create QRIS v2 ({t})', '/api/generate/v2/qris' in s)
    cek(f'[5] HMAC ({t})', 'hash_hmac' in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f'[7] lint ({t})', 'No syntax errors' in r.stdout)
    m = (ROOT/f'{t}/Modules/Marketplace/Marketplace.php').read_text()
    cek(f'[9] dispatcher ({t})', 'marketplace_payment_create(' in m)

for w in ('legacy/public','public','pos/public'):
    s = (ROOT/f'{w}/admin/marketplace-settings/index.php').read_text()
    cek(f'[11] settings ({w})', 'CasaKu (QRIS Otomatis)' in s)
    s2 = (ROOT/f'{w}/store/checkout.php').read_text()
    cek(f'[12] checkout ({w})', 'marketplace_payment_methods' in s2)
    cek(f'[13] webhook file ({w})', (ROOT/f'{w}/marketplace-casaku-webhook.php').is_file())

cek('[14] VERSION', (ROOT/'VERSION').read_text().strip()=='17.0.50', (ROOT/'VERSION').read_text().strip())
print()
if failed:
    print(f'GAGAL: {failed}'); sys.exit(1)
print('SEMUA CEK LULUS')
