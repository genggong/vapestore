#!/usr/bin/env python3
import pathlib, subprocess, sys
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

# V17.0.51 — log diagnostik 403 webhook CasaKu
for w in ('legacy/public','public','pos/public'):
    p = ROOT/f'{w}/marketplace-casaku-webhook.php'
    cek(f'[1] webhook ({w})', p.is_file())
    s = p.read_text()
    cek(f'[2] log diagn 403 ({w})', 'casaku-webhook.log' in s)
    cek(f'[3] content-type json ({w})', "Content-Type: application/json" in s)
    cek(f'[4] txn tidak dikenal -> 200 ({w})', 'Webhook diterima (transaksi tidak dikenal' in s)
    cek(f'[5] tidak ada 404 pesanan ({w})', "'Pesanan tidak ditemukan., 404" not in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f'[6] lint ({w})', 'No syntax errors' in r.stdout)

cek('[7] VERSION >= 17.0.52', (ROOT/'VERSION').read_text().strip() >= '17.0.52', (ROOT/'VERSION').read_text().strip())
print()
if failed:
    print(f'GAGAL: {failed}'); sys.exit(1)
print('SEMUA CEK LULUS')
