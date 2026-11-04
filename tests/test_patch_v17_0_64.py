#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

VAR = [('public','public'),('legacy','legacy/public'),('pos','pos/public')]

# base live = file yang disinkronkan dari backup (foto/pagination benar)
BASE = pathlib.Path('/tmp/live_vk')

for v,pref in VAR:
    p = (ROOT/(pref+'/store/products.php'))
    s = p.read_text()
    cek(f"[1] tombol Terapkan/Reset dihapus ({v})", 'Terapkan' not in s and 'btn-soft' not in s and 'bi-funnel' not in s)

    d = (ROOT/(pref+'/store/_data.php')).read_text()
    cek(f"[2] multi-token search ({v})", 'function storefront_search_clause' in d)
    cek(f"[3] search pakai clause ({v})", d.count('storefront_search_clause($search') >= 1)
    cek(f"[4] pagination+total ada ({v})", 'function storefront_products_total' in d and 'function storefront_pagination' in d)

    # FOTO-SAFETY: fungsi foto harus identik dgn file base live (tidak tersentuh)
    base = (BASE/(pref+'/store/_data.php')).read_text()
    import re
    def grab(txt,name):
        m=re.search(r'function '+name+r'\(.*?\n\}',txt,re.S)
        return m.group(0) if m else 'MISSING'
    for fn in ['storefront_product_image','storefront_public_file_exists','storefront_category_image']:
        cek(f"[5] foto {fn} utuh ({v})", grab(d,fn)==grab(base,fn))

    j = (ROOT/(pref+'/assets/js/storefront.js')).read_text()
    cek(f"[6] auto-apply debounce 300 ({v})", 'catalogFilter' not in j and 'catalog-filter-form' in j and 'setTimeout(() => catalogForm.submit(), 300)' in j and "querySelector('input[name=\"q\"]')" in j)

    c = (ROOT/(pref+'/assets/css/storefront.css')).read_text()
    cek(f"[7] mobile bar select:last-child ({v})", 'catalog-filter-form select:last-child{grid-column:1/-1}' in c and '.filter-search{grid-column:1/-1' in c)

    for f in ['store/products.php','store/_data.php']:
        r = subprocess.run(['php','-l',str(ROOT/(pref+'/'+f))],capture_output=True,text=True)
        cek(f"[8] lint {f} ({v})", 'No syntax errors' in r.stdout, r.stdout.strip())

cmd_js = str(ROOT/'public/assets/js/storefront.js')
r = subprocess.run(['node','--check',cmd_js],capture_output=True,text=True)
cek('[9] lint storefront.js', r.returncode==0, r.stderr.strip())

cek('[10] VERSION == 17.0.64', (ROOT/'VERSION').read_text().strip()=='17.0.64', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.64/PATCH_v17.0.64.zip'
cek('[11] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = []
        for _,pref in VAR:
            for f in ['store/products.php','store/_data.php','assets/js/storefront.js','assets/css/storefront.css']:
                expect.append(pref+'/'+f)
        expect.append('VERSION')
        cek('[12] zip memuat 13 entry', set(names)==set(expect), str(sorted(names)))
        allmatch = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[13] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
