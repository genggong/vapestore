#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib, re
ROOT = pathlib.Path('/home/ubuntu/vapestore')
BASE = pathlib.Path('/tmp/live_vk')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

TREES = [('public','public'),('legacy','legacy/public'),('pos','pos/public')]

# --- inti AJAX di products.php (3 pohon) ---
for v,pref in TREES:
    s=(ROOT/(pref+'/store/products.php')).read_text()
    cek(f"[1] ajax mode ({v})", "$ajax" in s and "X-Requested-With" in s and "'ok' => true" in s and '$renderCatalog' in s and 'json_encode(' in s)
    cek(f"[2] buttons tetap dihapus ({v})", 'Terapkan' not in s and 'btn-soft' not in s)
    cek(f"[3] tombol wishlist/add-cart tetap ada ({v})", 'wish-btn' in s and 'add-cart-btn' in s)
    r = subprocess.run(['php','-l',str(ROOT/(pref+'/store/products.php'))],capture_output=True,text=True)
    cek(f"[4] lint products.php ({v})", 'No syntax errors' in r.stdout, r.stdout.strip())

# --- JS AJAX di root assets + 3 pohon ---
for pref in ['assets','public/assets','legacy/public/assets','pos/public/assets']:
    j=(ROOT/(pref+'/js/storefront.js')).read_text()
    cek(f"[5] js ajax ({pref})", 'catalogLoad(' in j and "X-Requested-With" in j and 'fetch(' in j and 'window.history.replaceState' in j and 'catalogArea.innerHTML = data.html' in j)

# --- root assets diserve live: js+css ---
jsr=(ROOT/'assets/js/storefront.js').read_text(); cssr=(ROOT/'assets/css/storefront.css').read_text()
cek('[6] root js ajax search+select+pagination', "addEventListener('input'" in jsr and "setTimeout(() => catalogLoad(1), 300)" in jsr and "addEventListener('change', () => catalogLoad(1))" in jsr and 'bindCatalogNav' in jsr)
cek('[7] root css loading state', '.catalog-loading{' in cssr)

# --- foto aman: _data.php tidak tersentuh di rilis ini (masih base live) ---
for v,pref in TREES:
    cur=(ROOT/(pref+'/store/_data.php')).read_text(); base=(BASE/(pref+'/store/_data.php')).read_text()
    cek(f"[8] foto utuh vs live ({v})", re.search(r'function storefront_product_image\(.*?\n\}',cur,re.S).group(0)==re.search(r'function storefront_product_image\(.*?\n\}',base,re.S).group(0))
    cek(f"[9] pagination+total ada ({v})", 'function storefront_products_total' in cur and 'function storefront_pagination' in cur)

# --- cache version ---
hdr=(ROOT/'legacy/public/store/_header.php').read_text(); ftr=(ROOT/'legacy/public/store/_footer.php').read_text()
cek('[10] cache css ?v=17.0.66', 'storefront.css?v=17.0.66' in hdr)
cek('[11] cache js ?v=17.0.66', 'storefront.js?v=17.0.66' in ftr)

# lint semua js
for pref in ['assets','public/assets','legacy/public/assets','pos/public/assets']:
    r=subprocess.run(['node','--check',str(ROOT/(pref+'/js/storefront.js'))],capture_output=True,text=True)
    cek(f"[12] lint js ({pref})", r.returncode==0, r.stderr.strip())

cek('[13] VERSION == 17.0.66', (ROOT/'VERSION').read_text().strip()=='17.0.66', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.66/PATCH_v17.0.66.zip'
cek('[14] zip ada', zp.exists())
if zp.exists():
    expect=[]
    for _,pref in TREES:
        expect += [pref+'/store/products.php', pref+'/assets/js/storefront.js', pref+'/assets/css/storefront.css']
    expect += ['assets/js/storefront.js','assets/css/storefront.css',
               'legacy/public/store/_header.php','legacy/public/store/_footer.php','VERSION']
    with zipfile.ZipFile(zp) as z:
        cek('[15] zip memuat semua entry', set(z.namelist())==set(expect), str(len(z.namelist())))
        allm=all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[16] zip==source', allm)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
