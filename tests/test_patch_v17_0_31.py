"""Kontrak test v17.0.31 — notifikasi pesanan lewat Fonnte saja + varian di struk/invoice.

Jalankan: python3 -m unittest tests.test_patch_v17_0_31 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
WEB = {'legacy': 'legacy/public', 'public': 'public', 'pos': 'pos/public'}
MOD = {'legacy': 'legacy/app', 'public': 'app', 'pos': 'pos/app'}
ENV = dict(os.environ, VAPEKONG_DB_HOST='127.0.0.1', VAPEKONG_DB_PORT='3306',
           VAPEKONG_DB_NAME='vapekong_prod', VAPEKONG_DB_USER='vk_test2', VAPEKONG_DB_PASS='VkTest2!Pass2026')


def read(p):
    return (ROOT / p).read_text(encoding='utf-8', errors='replace')


def php(code):
    r = subprocess.run(['php', '-d', 'display_errors=1', '-r', code], capture_output=True, text=True, env=ENV,
                       cwd=str(ROOT))
    assert r.returncode == 0, (r.stderr or r.stdout)[:500]
    return r.stdout.strip()


class TestFonnteSaja(unittest.TestCase):
    def test_tidak_memakai_gateway_marketplace(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                blok = s[s.index('function marketplace_order_send_wa('):]
                blok = blok[:blok.index('\n}\n')]
                self.assertNotIn('marketplace_wa_send_text(', blok, 'notifikasi pesanan tidak boleh lewat Teknorilis')
                self.assertIn('wa_send_fonnte(', blok)

    def test_pesan_error_menyebut_fonnte(self):
        s = read('legacy/app/Modules/Marketplace/Marketplace.php')
        self.assertIn('Modul WhatsApp POS (Fonnte)', s)

    def test_teks_pengaturan_menyebut_fonnte(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                self.assertIn('(Fonnte)', read(f'{w}/admin/marketplace-settings/index.php'))


class TestVarianNota(unittest.TestCase):
    def test_struk_pos_menampilkan_varian(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/admin/transactions/print.php')
                self.assertIn("variant_name", s)
                self.assertIn('Varian: <?= e($it', s)

    def test_invoice_wa_menampilkan_varian(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/admin/whatsapp/_helpers.php')
                self.assertIn("if (!empty($it['variant_name'])) $name .= ' - ' . $it['variant_name'];", s)

    def test_pdf_invoice_marketplace_sudah_varian(self):
        s = read('legacy/app/Modules/Marketplace/MarketplaceV15.php').replace(' ', '')
        self.assertTrue(".'-'.$i['variant_name']" in s or "'-'.$i['variant_name']" in s,
                        'PDF invoice marketplace harus menyertakan nama varian')


class TestRuntime(unittest.TestCase):
    def test_invoice_wa_berisi_varian(self):
        out = php(r'''
require "/home/ubuntu/vapestore/legacy/app/bootstrap.php";
require "/home/ubuntu/vapestore/legacy/public/admin/whatsapp/_helpers.php";
$db = Database::connect();
$row = $db->query("SELECT transaction_id, variant_name FROM transaction_items WHERE variant_name IS NOT NULL AND variant_name<>'' ORDER BY id DESC LIMIT 1")->fetch();
if (!$row) { echo 'SKIP'; exit; }
$t = $db->query("SELECT * FROM transactions WHERE id=" . (int) $row['transaction_id'])->fetch();
$it = $db->query("SELECT * FROM transaction_items WHERE transaction_id=" . (int) $row['transaction_id'])->fetchAll();
$msg = wa_invoice_message($t, $it);
echo str_contains($msg, (string) $row['variant_name']) ? 'ADA' : 'TIDAK ADA';
''')
        self.assertIn(out, ('ADA', 'SKIP'))

    def test_normalisasi_nomor_cabang(self):
        out = php(r'''
require "/home/ubuntu/vapestore/legacy/app/bootstrap.php";
require "/home/ubuntu/vapestore/legacy/public/admin/whatsapp/_helpers.php";
echo wa_normalize_target('0831326788499');
''')
        self.assertEqual(out, '62831326788499')


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 31))

    def test_halaman_system_upgrade(self):
        v = read('VERSION').strip()
        self.assertIn(f'v{v}', read('legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(v, read(rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)
