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
    # resolve hanya sekali, di dalam branch insert
    idx_else = s.find('}else{')
    # v17.0.58: resolve harus di dalam else (setelah else) dan tidak di cabang edit
    head = s
    n_resolve = s.count('marketplace_rajaongkir_resolve_direct_destination')
    cek(f"[1] resolve hanya 1x ({v})", n_resolve==1, str(n_resolve))
    # posisi resolve harus setelah '}else{' pertama (cabang insert)
    pos_resolve = s.find('marketplace_rajaongkir_resolve_direct_destination')
    pos_else = s.find('}else{')
    cek(f"[2] resolve dalam cabang insert ({v})", pos_resolve > pos_else)
    # update branch tidak boleh memanggil resolve / tidak ada rowCount throw
    cek(f"[3] rowCount throw dihapus ({v})", "rowCount()===0" not in s)
    cek(f"[4] update + insert tetap ada ({v})",
        "UPDATE marketplace_store_courier_rates SET fee" in s and "INSERT INTO marketplace_store_courier_rates" in s)
    cek(f"[5] edit value provinsi pakai id ({v})", "setOne(p2,data.province,data.province_id)" in s)
    r = subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    cek(f"[6] lint ({v})", 'No syntax errors' in r.stdout)

cek('[7] VERSION == 17.0.58', (ROOT/'VERSION').read_text().strip()=='17.0.58', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.58/PATCH_v17.0.58.zip'
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
