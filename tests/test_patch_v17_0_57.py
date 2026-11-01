#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

VARIAN = {'public':'public','legacy':'legacy/public','pos':'pos/public'}

for v,pref in VARIAN.items():
    p = ROOT/(pref+'/admin/marketplace-courier-rates/index.php')
    s = p.read_text()
    cek(f"[1] edit value provinsi pakai id ({v})", "setOne(p2,data.province,data.province_id)" in s)
    cek(f"[2] provinsi tak lagi kosong ({v})", "setOne(p2,data.province,'')" not in s)
    cek(f"[3] id wilayah kota/kec/kel tetap ({v})",
        "setOne(c2,data.city,data.city_id)" in s and "setOne(d2,data.district,data.district_id)" in s and "setOne(s2,data.subdistrict,data.subdistrict_id)" in s)
    cek(f"[4] data-edit memuat province_id ({v})", "province_id'=>(int)$r['province_id']" in s)
    cek(f"[5] edit global + hapus + cabang ({v})",
        'window.fillEdit=fillEdit' in s and 'value="delete"' in s and 'name="branch_id"' in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[6] lint ({v})", 'No syntax errors' in r.stdout)

cek('[7] VERSION == 17.0.57', (ROOT/'VERSION').read_text().strip()=='17.0.57', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.57/PATCH_v17.0.57.zip'
cek('[8] zip ada', zp.exists())
if zp.exists():
    with zipfile.ZipFile(zp) as z:
        names = z.namelist()
        expect = [pref+'/admin/marketplace-courier-rates/index.php' for pref in VARIAN.values()] + ['VERSION']
        cek('[9] zip memuat 4 entry', set(names)==set(expect), str(names))
        allmatch = all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[10] zip==source semua entry', allmatch)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
