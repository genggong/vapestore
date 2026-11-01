#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

VARIAN = {'public':'public','legacy':'legacy/public','pos':'pos/public'}

# 1) Operasional: nominal step=1, bukan 1000
for v,pref in VARIAN.items():
    p = ROOT/(pref+'/admin/operasional/index.php')
    s = p.read_text()
    cek(f"[1] operasional step=1 ({v})", 'name="amount" min="1" step="1"' in s)
    cek(f"[2] operasional tdk step=1000 ({v})", 'step="1000"' not in s)
    cek(f"[3] operasional form utuh ({v})", 'CSRF::input()' in s and 'finance_transactions' in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[4] lint operasional ({v})", 'No syntax errors' in r.stdout)

# 2) Tarif Kurir Toko: edit global, hapus, cabang
for v,pref in VARIAN.items():
    p = ROOT/(pref+'/admin/marketplace-courier-rates/index.php')
    s = p.read_text()
    cek(f"[5] kurir edit global ({v})", 'window.fillEdit=fillEdit' in s and 'window.cancelEdit=cancelEdit' in s)
    cek(f"[6] kurir variabel p2/c2/d2/s2 ({v})", "const p2=document.getElementById('rateProvince')" in s)
    cek(f"[7] kurir hapus (delete) ({v})", 'value="delete"' in s)
    cek(f"[8] kurir cabang ({v})", 'name="branch_id"' in s)
    cek(f"[9] kurir region-options ({v})", "region-options.php" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[10] lint kurir ({v})", 'No syntax errors' in r.stdout)

cek('[11] VERSION == 17.0.56', (ROOT/'VERSION').read_text().strip()=='17.0.56', (ROOT/'VERSION').read_text().strip())

# zip == source
zp = ROOT/'patch/v17.0.56/PATCH_v17.0.56.zip'
cek('[12] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = [pref+'/admin/operasional/index.php' for pref in VARIAN.values()] + \
                 [pref+'/admin/marketplace-courier-rates/index.php' for pref in VARIAN.values()] + ['VERSION']
        cek('[13] zip memuat 7 entry', set(names)==set(expect), str(names))
        allmatch = True
        for n in expect:
            if hashlib.sha256(z.read(n)).hexdigest() != hashlib.sha256((ROOT/n).read_bytes()).hexdigest():
                allmatch=False
        cek('[14] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
