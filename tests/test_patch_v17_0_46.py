#!/usr/bin/env python3
"""Test kontrak v17.0.46 — validasi area Kurir Toko meneruskan branch_id.

Akar bug: `marketplace_store_courier_validate_destination()` dipanggil tanpa
branchId sehingga di dalamnya memakai `$branchId` yang undefined (=0/umum).
Padahal tarif per kelurahan dicatat per cabang (mis. Mejasem). Akibatnya tarif
khusus cabang tidak ketemu -> alamat dianggap di luar area layanan Kurir Toko.
"""
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
    sc = (ROOT / f'{t}/Modules/Marketplace/StoreCourier.php').read_text()
    mp = (ROOT / f'{t}/Modules/Marketplace/Marketplace.php').read_text()

    # [1] signature validate_destination kini menerima branchId
    cek(f'[1] signature terima branchId — {t}',
        "function marketplace_store_courier_validate_destination(array $payload, int $destinationId, int $branchId = 0): bool" in sc)

    # [2] pemanggilan rate meneruskan $branchId (bukan undefined)
    cek(f'[2] rate menerima $branchId — {t}',
        "marketplace_v16_store_courier_rate($db,$destinationId,$province,$city,$district,$subdistrict,$branchId)" in sc)

    # [3] pemanggil di create_order meneruskan $branchId
    cek(f'[3] create_order terusan branchId — {t}',
        "marketplace_store_courier_validate_destination($payload, $destinationId, $branchId)" in mp)

    # [4] branchId sudah di-set di kedua jalur (pickup & delivery) sebelum validasi
    cek(f'[4] $branchId definit sebelum validasi — {t}',
        mp.count('$branchId =') >= 2 and "$branchId = marketplace_delivery_branch" in mp)

    for phpin in ('StoreCourier.php', 'Marketplace.php'):
        r = subprocess.run(['php', '-l', str(ROOT / f'{t}/Modules/Marketplace/{phpin}')],
                           capture_output=True, text=True)
        cek(f'[5] php -l {phpin} — {t}', 'No syntax errors' in r.stdout)

print()
if failed:
    print(f'GAGAL: {len(failed)} → {failed}')
    sys.exit(1)
print('SEMUA CEK LULUS')
