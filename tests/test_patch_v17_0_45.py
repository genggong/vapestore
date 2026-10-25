#!/usr/bin/env python3
"""Test kontrak v17.0.45 — notif WA Waktu pakai jam MySQL (bukan PHP UTC)."""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path('/home/ubuntu/vapestore')
TREES = ('legacy/app', 'app', 'pos/app')
failed = []


def cek(n, c, d=''):
    print(('  [OK] ' if c else '  [!!] ') + n + (f' — {d}' if d and not c else ''))
    if not c:
        failed.append(n)


for t in TREES:
    s = (ROOT / f'{t}/Modules/Marketplace/Marketplace.php').read_text()
    u = f'{t}/Modules/Marketplace/Marketplace.php'
    cek(f'[1] Waktu pakai jam MySQL — {u}', "SELECT NOW()" in s and "Waktu: '" in s.replace("$lines[] = 'Waktu: ' . date(", ""))
    cek(f'[2] fallback saat DB error — {u}', "catch (Throwable $e)" in s and "Waktu: ' . date('d/m/Y H:i')" in s)
    r = subprocess.run(['php', '-l', str(ROOT / f'{t}/Modules/Marketplace/Marketplace.php')], capture_output=True, text=True)
    cek(f'[3] php -l — {u}', 'No syntax errors' in r.stdout)

print()
if failed:
    print(f'GAGAL: {len(failed)} → {failed}')
    sys.exit(1)
print('SEMUA CEK LULUS')
