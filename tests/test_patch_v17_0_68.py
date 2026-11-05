#!/usr/bin/env python3
import pathlib, subprocess, sys, zipfile, hashlib
ROOT = pathlib.Path('/home/ubuntu/vapestore')
failed = []
def cek(n,c,d=''):
    print(('  [OK] ' if c else '  [!!] ')+n+(f' — {d}' if d and not c else ''))
    if not c: failed.append(n)

# 1) Opsi qris_pakasir TIDAK muncul sebagai <option> polos (hanya sebagai komentar/fallback)
# 2) Komentar penjelasan hadir
COMMENT = "Pakasir (QRIS otomatis) disembunyikan"
import re
# opsi polos = <option> yang TIDAK berada di dalam komentar <!-- ... -->
UNCOMMENTED = re.compile(r'(?<!<!--\s)<option value="qris_pakasir">QRIS Otomatis \(Pakasir\)</option>')

for pref in ['public','legacy/public','pos/public']:
    f = ROOT/(pref+'/admin/pos/index.php')
    txt = f.read_text()
    cek(f"[1] komentar sembunyi Pakasir ({pref})", COMMENT in txt)
    cek(f"[2] tidak ada option qris_pakasir polos ({pref})", not UNCOMMENTED.search(txt))
    r = subprocess.run(['php','-l',str(f)],capture_output=True,text=True)
    cek(f"[3] lint pos ({pref})", 'No syntax errors' in r.stdout)

cek('[4] VERSION == 17.0.68', (ROOT/'VERSION').read_text().strip()=='17.0.68', (ROOT/'VERSION').read_text().strip())

zp = ROOT/'patch/v17.0.68/PATCH_v17.0.68.zip'
cek('[5] zip ada', zp.exists())
if zp.exists():
    expect=[]
    for pref in ['public','legacy/public','pos/public']:
        expect.append(pref+'/admin/pos/index.php')
    expect.append('VERSION')
    with zipfile.ZipFile(zp) as z:
        cek('[6] zip memuat 4 entry', sorted(z.namelist())==sorted(expect), str(sorted(z.namelist())))
        allm=all(hashlib.sha256(z.read(n)).hexdigest()==hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in expect)
        cek('[7] zip==source', allm)

print()
if failed:
    print('GAGAL:', failed); sys.exit(1)
print('SEMUA CEK LULUS')
