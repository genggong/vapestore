#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

# ===== inti fix v17.0.65: storefront.js/css LIVE diserve dari ROOT assets/ (bukan 3 pohon) =====
js  = (ROOT/'assets/js/storefront.js').read_text()
css = (ROOT/'assets/css/storefront.css').read_text()
cek('[1] root assets/js memuat auto-apply debounce 300', "querySelector('input[name=\"q\"]')" in js and 'setTimeout(() => catalogForm.submit(), 300)' in js and '.catalog-filter-form' in js)
cek('[2] root assets/css mobile select:last-child', 'catalog-filter-form select:last-child{grid-column:1/-1}' in css and '.filter-search{grid-column:1/-1' in css)

hdr = (ROOT/'legacy/public/store/_header.php').read_text()
ftr = (ROOT/'legacy/public/store/_footer.php').read_text()
cek('[3] header css cache ?v=17.0.65', "storefront.css?v=17.0.65" in hdr)
cek('[4] footer js cache ?v=17.0.65', "storefront.js?v=17.0.65" in ftr)

# ===== kontinuitas dari v17.0.64 (3 pohon; sudah terpasang live) =====
for v,pref in [('public','public'),('legacy','legacy/public'),('pos','pos/public')]:
    s=(ROOT/(pref+'/store/products.php')).read_text()
    cek(f"[5] buttons dihapus ({v})", 'Terapkan' not in s and 'btn-soft' not in s)
    d=(ROOT/(pref+'/store/_data.php')).read_text()
    cek(f"[6] multi-token ({v})", 'function storefront_search_clause' in d)
    j=(ROOT/(pref+'/assets/js/storefront.js')).read_text()
    cek(f"[7] js auto-apply pohon ({v})", 'setTimeout(() => catalogForm.submit(), 300)' in j)
    c=(ROOT/(pref+'/assets/css/storefront.css')).read_text()
    cek(f"[8] css mobile pohon ({v})", 'select:last-child{grid-column:1/-1}' in c)

r = subprocess.run(['node','--check',str(ROOT/'assets/js/storefront.js')],capture_output=True,text=True)
cek('[9] lint root storefront.js', r.returncode==0, r.stderr.strip())

cek('[10] VERSION == 17.0.65', (ROOT/'VERSION').read_text().strip()=='17.0.65', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.65/PATCH_v17.0.65.zip'
cek('[11] zip ada', zp.exists())
if zp.exists():
    expect = ['assets/js/storefront.js','assets/css/storefront.css',
              'legacy/public/store/_header.php','legacy/public/store/_footer.php','VERSION']
    with zipfile.ZipFile(zp) as z:
        cek('[12] zip memuat 5 entry', set(z.namelist())==set(expect), str(sorted(z.namelist())))
        allm = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[13] zip==source semua entry', allm)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
