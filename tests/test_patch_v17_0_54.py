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

# V17.0.54 — guest checkout -> tautkan/buat member (casaKU tetap utuh)
for v in ({'app':'app','legacy':'legacy/app','pos':'pos/app'}).items():
    p = ROOT/(v[1]+'/Modules/Marketplace/Marketplace.php')
    s = p.read_text()
    cek(f'[1] guest->member blok ({v[0]})', 'V17.0.54 — GUEST checkout' in s)
    cek(f'[2] tautkan member by phone ({v[0]})', "AND phone=? AND TRIM(COALESCE(phone,''))<>''" in s)
    cek(f'[3] auto-create member vapekong123 ({v[0]})', "password_hash('vapekong123'" in s)
    cek(f'[4] CasaKu require{""} ({v[0]})', "require_once __DIR__ . '/CasaKuPayment.php'" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f'[5] lint ({v[0]})', 'No syntax errors' in r.stdout)

# edit tarif kurir sudah ada di vapestore (item 2)
for v in ('legacy','pos'):
    p = ROOT/f'{v}/public/admin/marketplace-courier-rates/index.php'
    cek(f'[6] edit tarif (fillEdit) ({v})', 'fillEdit' in p.read_text())

# item 2 juga di pohon 'public'? vapestore public/admin mungkin tak ada; cek
p2 = ROOT/'public/admin/marketplace-courier-rates/index.php'
if p2.exists():
    cek('[7] edit tarif (public)', 'fillEdit' in p2.read_text())
else:
    cek('[7] edit tarif (public dir tdk ada — wajar, pindah ke pohon lain di vapestore? cek manual)', False)

cek('[8] VERSION >= 17.0.54', _ver((ROOT/'VERSION').read_text().strip()) >= (17,0,54), (ROOT/'VERSION').read_text().strip())
print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
