#!/usr/bin/env python3
"""Test patch v17.0.74 — QRIS CasaKu pay-first + Tambah Member + WA referral."""
import pathlib
import hashlib
import subprocess
import zipfile

ROOT = pathlib.Path('/home/ubuntu/vapestore')
VERSION = '17.0.74'
ADMINS = ['public', 'legacy/public', 'pos/public']
failed = []


def check(name, cond):
    print(('  [OK] ' if cond else '  [!!] ') + name)
    if not cond:
        failed.append(name)


def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()


# --- POS (index.php) ---
for v in ADMINS:
    t = (ROOT / v / 'admin/pos/index.php').read_text()
    check(f'{v}: label Tambah Member', 'Member Cepat' not in t and 'Tambah Member' in t)
    check(f'{v}: tombol Batal/Simpan', 'quick-member-cancel' in t and 'quick-member-save' in t)
    check(f'{v}: dedupe QR CasaKu', "str_contains($note, '[CASAKU_PAYMENT]')" in t)
    check(f'{v}: redirect pay dulu', "&pay=1" in t)

# --- Detail (detail.php): panel QR besar di atas + status klikable ---
for v in ADMINS:
    t = (ROOT / v / 'admin/transactions/detail.php').read_text()
    check(f'{v}: panel casakuPayPanel', 'casakuPayPanel' in t)
    check(f'{v}: QR casakuQr', 'id="casakuQr"' in t)
    check(f'{v}: tombol Cek Status Pembayaran', 'Cek Status Pembayaran' in t)
    check(f'{v}: tidak ada status yatim legacy', 'casakuManualCheck' not in t and 'casakuLiveStatus' not in t)

# --- Status (status.php): WA ke referrer ---
for v in ADMINS:
    t = (ROOT / v / 'admin/casaku/status.php').read_text()
    check(f'{v}: include helper WA', "../whatsapp/_helpers.php" in t)
    check(f'{v}: kirim WA referrer', 'wa_send_fonnte' in t and 'Poin kamu bertambah' in t)
    check(f'{v}: total poin referrer', 'total poin kamu' in t)

# --- konsistensi fitur CasaKu antar varian (bukan seluruh file; legacy punya drift lama pakasir) ---
def norm(s):
    s = s.replace('legacy_base_url', 'base_url').replace('legacy_redirect', 'redirect')
    return ''.join(l for l in s.splitlines(keepends=True) if l.strip())

def casaku_region(path):
    t = path.read_text()
    i = t.index('<section class="casaku-pay-panel"')
    j = t.index('</section>', i) + len('</section>')
    return norm(t[i:j])

panel_regions = {norm((ROOT / v / 'admin/transactions/detail.php') and '') for v in ADMINS}  # placeholder init
regions = [casaku_region(ROOT / v / 'admin/transactions/detail.php') for v in ADMINS]
check(f'panel CasaKu identik antar varian (normalisasi)', len(set(regions)) == 1)

# lint
for v in ADMINS:
    for rel in ['admin/pos/index.php', 'admin/transactions/detail.php', 'admin/casaku/status.php']:
        f = ROOT / v / rel
        r = subprocess.run(['php', '-l', str(f)], capture_output=True, text=True)
        check(f'lint {v}/{rel.split("/")[-1]}', 'No syntax errors' in r.stdout)

# --- VERSION & packaging ---
check('VERSION == 17.0.74', ROOT.joinpath('VERSION').read_text().strip() == VERSION)
zip_path = ROOT / f'patch/v{VERSION}/PATCH_v{VERSION}.zip'
check('release zip exists', zip_path.exists())
if zip_path.exists():
    expected = [f'{v}/admin/pos/index.php' for v in ADMINS] + \
               [f'{v}/admin/transactions/detail.php' for v in ADMINS] + \
               [f'{v}/admin/casaku/status.php' for v in ADMINS] + ['VERSION']
    with zipfile.ZipFile(zip_path) as z:
        check('release zip entries', sorted(z.namelist()) == sorted(expected))
        check('release zip matches source', all(
            hashlib.sha256(z.read(n)).hexdigest() == sha(ROOT / n) for n in expected))

print()
if failed:
    print('GAGAL: ' + ', '.join(failed))
    raise SystemExit(1)
print('SEMUA CEK LULUS')
