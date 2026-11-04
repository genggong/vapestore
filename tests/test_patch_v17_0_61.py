#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

VAR = [('public','public'),('legacy','legacy/public'),('pos','pos/public')]
for v,pref in VAR:
    p = ROOT/(pref+'/admin/transactions/delete.php')
    s = p.read_text()
    cek(f"[1] blok restore varian ada ({v})", "Kembalikan stok VARIAN" in s)
    cek(f"[2] baca transaction_items.variant_id ({v})", "transaction_items WHERE transaction_id = ? AND variant_id IS NOT NULL" in s)
    cek(f"[3] update stok varian ({v})", "UPDATE marketplace_product_variant_stocks SET stock = stock + ?" in s)
    cek(f"[4] insert bila baris kosong ({v})", "INSERT INTO marketplace_product_variant_stocks" in s)
    cek(f"[5] dalam transaksi ({v})", "beginTransaction" in s and "commit()" in s)
    # invariant komentar
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[6] lint ({v})", 'No syntax errors' in r.stdout)

cek('[7] VERSION == 17.0.61', (ROOT/'VERSION').read_text().strip()=='17.0.61', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.61/PATCH_v17.0.61.zip'
cek('[8] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = [pref+'/admin/transactions/delete.php' for _,pref in VAR] + ['VERSION']
        cek('[9] zip memuat 4 entry', set(names)==set(expect), str(names))
        allmatch = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[10] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
