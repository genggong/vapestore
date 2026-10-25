#!/usr/bin/env python3
"""Test kontrak v17.0.44 — Edit ongkir per kelurahan + filter cabang."""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path('/home/ubuntu/vapestore')
TREES = ('legacy/public', 'public', 'pos/public')
failed = []


def cek(n, c, d=''):
    print(('  [OK] ' if c else '  [!!] ') + n + (f' — {d}' if d and not c else ''))
    if not c:
        failed.append(n)


def read(p):
    return (ROOT / p).read_text()


for t in TREES:
    s = read(f'{t}/admin/marketplace-courier-rates/index.php')
    u = f'{t}/admin/marketplace-courier-rates/index.php'
    cek(f'[1] UPDATE by id — {u}', 'UPDATE marketplace_store_courier_rates SET fee=?,eta=?,is_active=?,sort_order=?,branch_id=?,updated_at=NOW() WHERE id=?' in s)
    cek(f'[2] mode edit (rateId) — {u}', 'id="rateId"' in s and 'editBanner' in s)
    cek(f'[3] tombol Edit per baris — {u}', 'onclick="fillEdit(this)"' in s)
    cek(f'[4] data-edit prefill — {u}', "data-edit" in s and 'fillEdit' in s)
    cek(f'[5] filter cabang (fb) — {u}', 'name="fb"' in s and "$_GET['fb']" in s)
    cek(f'[6] label Simpan/Perbarui — {u}', 'rateId' in s)

# lint tunggal gabungan
lint = []
for t in TREES:
    r = subprocess.run(['php', '-l', str(ROOT / f'{t}/admin/marketplace-courier-rates/index.php')], capture_output=True, text=True)
    if 'No syntax errors' not in r.stdout:
        lint.append(t)
cek('[7] php -l semua pohon', not lint, str(lint))

print()
if failed:
    print(f'GAGAL: {len(failed)} → {failed}')
    sys.exit(1)
print('SEMUA CEK LULUS')
