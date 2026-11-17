#!/usr/bin/env python3
import pathlib
import re
import subprocess
import zipfile
import hashlib

ROOT = pathlib.Path('/home/ubuntu/vapestore')
VARIANTS = ['public', 'legacy/public', 'pos/public']
failed = []

def check(name, condition):
    print(('  [OK] ' if condition else '  [!!] ') + name)
    if not condition:
        failed.append(name)

for variant in VARIANTS:
    path = ROOT / variant / 'admin/pos/index.php'
    text = path.read_text()
    check(f'{variant}: referral phone input', 'referrer_phone' in text)
    check(f'{variant}: referral member dropdown', 'id="referrerMemberTheme"' in text and 'referrer_customer' in text)
    check(f'{variant}: referral displays member name', 'data-referrer-name' in text)
    check(f'{variant}: referral search bar', 'referrerSearchTheme' in text and 'referrerSearchResultsTheme' in text)
    check(f'{variant}: quick member', 'quick_member_create' in text and 'quickMemberTheme' in text)
    check(f'{variant}: transaction item insert arity', "VALUES(?,'product',?,?,?,?,?,?,0,?,NOW())" in text)
    check(f'{variant}: CasaKu POS method', "qris_casaku" in text and 'CasaKu' in text)
    check(f'{variant}: manual QRIS robust image URL', 'pos_theme_manual_qris_image_url' in text)
    checkout = (ROOT / variant / 'store/checkout.php').read_text()
    marketplace = (ROOT / variant.replace('public', 'app') / 'Modules/Marketplace/Marketplace.php').read_text()
    check(f'{variant}: checkout referral phone', 'referrer_phone' in checkout)
    check(f'{variant}: checkout referral server flow', 'marketplace_apply_order_referral' in marketplace)
    check(f'{variant}: referral table', 'customer_referrals' in text)
    check(f'{variant}: first-transaction guard', 'Referral hanya berlaku pada transaksi pertama' in text)
    check(f'{variant}: missing referrer rejected', 'Nomor HP Pengajak tidak ditemukan' in text)
    check(f'{variant}: 50 percent award', 'referralPoints' in text)
    result = subprocess.run(['php', '-l', str(path)], capture_output=True, text=True)
    check(f'{variant}: PHP lint', 'No syntax errors' in result.stdout)

for variant in VARIANTS:
    status = ROOT / variant / 'admin/pakasir/status.php'
    check(f'{variant}: pending QRIS referral handler', 'pakasir_status_apply_referral' in status.read_text())
    result = subprocess.run(['php', '-l', str(status)], capture_output=True, text=True)
    check(f'{variant}: status PHP lint', 'No syntax errors' in result.stdout)

# Structural safety: referral relationship is unique per referred customer.
source = (ROOT / 'public/admin/pos/index.php').read_text()
check('unique referral per referred customer', 'UNIQUE KEY customer_referrals_referred_unique (referred_customer_id)' in source)
check('referral points idempotency', 'customer_points' in source and 'Poin referral:' in source)
check('VERSION == 17.0.72', (ROOT / 'VERSION').read_text().strip() == '17.0.72')

zip_path = ROOT / 'patch/v17.0.72/PATCH_v17.0.72.zip'
check('release zip exists', zip_path.exists())
if zip_path.exists():
    expected = [
        'app/Helpers/helpers.php', 'app/Modules/Accounting/Accounting.php', 'app/Modules/Marketplace/Marketplace.php',
        'legacy/app/Helpers/helpers.php', 'legacy/app/Modules/Accounting/Accounting.php', 'legacy/app/Modules/Marketplace/Marketplace.php',
        'pos/app/Helpers/helpers.php', 'pos/app/Modules/Accounting/Accounting.php', 'pos/app/Modules/Marketplace/Marketplace.php',
        'public/admin/dashboard.php', 'public/admin/pos/index.php', 'public/admin/transactions/detail.php', 'public/admin/casaku/status.php', 'public/assets/js/marketplace-checkout.js', 'public/store/checkout.php',
        'legacy/public/admin/dashboard.php', 'legacy/public/admin/pos/index.php', 'legacy/public/admin/transactions/detail.php', 'legacy/public/admin/casaku/status.php', 'legacy/public/assets/js/marketplace-checkout.js', 'legacy/public/store/checkout.php',
        'pos/public/admin/dashboard.php', 'pos/public/admin/pos/index.php', 'pos/public/admin/transactions/detail.php', 'pos/public/admin/casaku/status.php', 'pos/public/assets/js/marketplace-checkout.js', 'pos/public/store/checkout.php',
        'VERSION',
    ]
    with zipfile.ZipFile(zip_path) as z:
        check('release zip entries', sorted(z.namelist()) == sorted(expected))
        check('release zip matches source', all(hashlib.sha256(z.read(n)).hexdigest() == hashlib.sha256((ROOT / n).read_bytes()).hexdigest() for n in expected))

if failed:
    print('GAGAL:', failed)
    raise SystemExit(1)
print('SEMUA CEK LULUS')
