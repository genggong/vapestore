#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

VAR = [('public','public'),('legacy','legacy/public'),('pos','pos/public')]

for v,pref in VAR:
    p = ROOT/(pref+'/store/products.php')
    s = p.read_text()
    cek(f"[1] tombol Terapkan/Reset dihapus ({v})", 'Terapkan' not in s and 'btn-soft' not in s and 'bi-funnel' not in s)

    d = (ROOT/(pref+'/store/_data.php')).read_text()
    cek(f"[2] multi-token search {v}", 'function storefront_search_clause' in d)
    cek(f"[3] search pakai clause ({v})", d.count('storefront_search_clause($search') >= 1)
    # negatif: tidak ada lagi LIKE tunggal untuk search
    cek(f"[4] tak ada LIKE lama ({v})", 'p.name LIKE ? OR p.product_code' not in d and 'p.name LIKE ? OR' not in d)

    j = (ROOT/(pref+'/assets/js/storefront.js')).read_text()
    cek(f"[5] auto-apply debounce 300 ({v})", 'catalog-filter-form' in j and 'setTimeout(() => catalogForm.submit(), 300)' in j)

    c = (ROOT/(pref+'/assets/css/storefront.css')).read_text()
    cek(f"[6] mobile bar select:last-child ({v})", 'catalog-filter-form select:last-child{grid-column:1/-1}' in c)

    for f in ['store/products.php','store/_data.php']:
        r = subprocess.run(['php','-l',str(ROOT/(pref+'/'+f))],capture_output=True,text=True)
        cek(f"[7] lint {f} ({v})", 'No syntax errors' in r.stdout)

cek('[8] VERSION == 17.0.62', (ROOT/'VERSION').read_text().strip()=='17.0.62', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.62/PATCH_v17.0.62.zip'
cek('[9] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = []
        for _,pref in VAR:
            for f in ['store/products.php','store/_data.php','assets/js/storefront.js','assets/css/storefront.css']:
                expect.append(pref+'/'+f)
        expect.append('VERSION')
        cek('[10] zip memuat 13 entry', set(names)==set(expect), str(names))
        allmatch = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[11] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
