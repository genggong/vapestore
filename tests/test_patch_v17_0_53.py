#!/usr/bin/env python3
import pathlib, subprocess, sys
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

def _ver(n):
    try: return tuple(int(x) for x in str(n).split('.'))
    except Exception: return (0,)

# V17.0.53 — kirim QRIS Merchant ID (uuid) sebagai qr_id, bukan orderId
for m in ('app/Modules','legacy/app/Modules','pos/app/Modules'):
    p = ROOT/f'{m}/Marketplace/CasaKuPayment.php'
    cek(f'[1] config qr_id ({m})', 'marketplace_casaku_qr_id' in p.read_text())
    s = p.read_text()
    cek(f'[2] qr_id dari config ({m})', "'qr_id' => $config['qr_id']" in s)
    cek(f'[3] tidak pakai orderId ({m})', "'qr_id' => $orderId" not in s)
    cek(f'[4] pesan QRIS Merchant ID ({m})', 'QRIS Merchant ID belum diatur' in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f'[5] lint ({m})', 'No syntax errors' in r.stdout)

for w in ('legacy/public','public','pos/public'):
    p = ROOT/f'{w}/admin/marketplace-settings/index.php'
    s = p.read_text()
    cek(f'[6] field QRIS Merchant ID ({w})', 'marketplace_casaku_qr_id' in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f'[7] lint admin ({w})', 'No syntax errors' in r.stdout)

cek('[8] VERSION >= 17.0.53', _ver((ROOT/'VERSION').read_text().strip()) >= (17,0,53), (ROOT/'VERSION').read_text().strip())
print()
if failed:
    print(f'GAGAL: {failed}'); sys.exit(1)
print('SEMUA CEK LULUS')
