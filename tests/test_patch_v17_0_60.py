#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

VAR = [('public','public'),('legacy','legacy/public'),('pos','pos/public')]
for v,pref in VAR:
    p = ROOT/(pref+'/admin/pos/index.php')
    s = p.read_text()
    cek(f"[1] debounce 300ms di trigger ({v})", "window.__posSearchDebounce = setTimeout" in s and ", 300);" in s)
    cek(f"[2] clearTimeout sebelum set ({v})", "clearTimeout(window.__posSearchDebounce)" in s)
    cek(f"[3] Enter flush pencarian ({v})", s.count("clearTimeout(window.__posSearchDebounce)")==2, str(s.count("clearTimeout(window.__posSearchDebounce)")))
    cek(f"[4] smart search utuh ({v})", "function posSearchTokens" in s and "function applyPosSmartSearch" in s)
    cek(f"[5] single-enter keranjang ({v})", "if (shown.length === 1)" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[6] lint ({v})", 'No syntax errors' in r.stdout)

cek('[7] VERSION == 17.0.60', (ROOT/'VERSION').read_text().strip()=='17.0.60', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.60/PATCH_v17.0.60.zip'
cek('[8] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = [pref+'/admin/pos/index.php' for _,pref in VAR] + ['VERSION']
        cek('[9] zip memuat 4 entry', set(names)==set(expect), str(names))
        allmatch = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[10] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
