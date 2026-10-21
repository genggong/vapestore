"""Kontrak test v17.0.32 — password marketplace member otomatis + login fleksibel.

Jalankan: python3 -m unittest tests.test_patch_v17_0_32 -v
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
    return r.returncode, (r.stdout or '') + (r.stderr or '')


def sql(q):
    r = subprocess.run(['mysql', '-h127.0.0.1', '-uvk_test2', '-pVkTest2!Pass2026', 'vapekong_prod', '-N', '-B', '-e', q],
                       capture_output=True, text=True)
    return r.stdout.strip()


class TestFormMember(unittest.TestCase):
    def test_kolom_password_otomatis(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/admin/customers/create.php')
                self.assertIn('member_default_password', s)
                self.assertIn("$defaultPassword = 'vapekong123'", s)
                self.assertIn('$usedDefaultPassword', s)

    def test_flash_menyebut_password(self):
        s = read('legacy/public/admin/customers/create.php')
        self.assertIn('Password login marketplace: ', s)

    def test_placeholder_menginformasikan_default(self):
        s = read('legacy/public/admin/customers/create.php')
        self.assertIn('Kosongkan = otomatis', s)


class TestLogin(unittest.TestCase):
    def test_fungsi_default_password(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('function marketplace_default_member_password()', s)
                self.assertIn('marketplace_default_member_password()', s)

    def test_self_heal_dan_nomor_fleksibel(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$db->exec("DELETE FROM customers WHERE name='ZZ Uji Login 32'");
$db->exec("INSERT INTO customers (name, phone, member_level, price_level, status, created_at) VALUES ('ZZ Uji Login 32','081234567890','regular','retail','active',NOW())");
$id = (int) $db->lastInsertId();
$_SESSION = [];
$hasil = [];
$hasil[] = marketplace_customer_login($db, '081234567890', 'vapekong123') ? 'ok' : 'gagal';
$hash = (string) $db->query("SELECT COALESCE(marketplace_password_hash,'') FROM customers WHERE id={$id}")->fetchColumn();
$hasil[] = $hash !== '' ? 'hash-tersimpan' : 'hash-kosong';
$_SESSION = [];
$hasil[] = marketplace_customer_login($db, '+6281234567890', 'vapekong123') ? 'format-lain-ok' : 'format-lain-gagal';
$_SESSION = [];
$hasil[] = marketplace_customer_login($db, '081234567890', 'passwordsalah') ? 'salah-diterima' : 'salah-ditolak';
$db->exec("DELETE FROM customers WHERE id={$id}");
echo implode('|', $hasil);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:300])
        self.assertIn('ok|hash-tersimpan|format-lain-ok|salah-ditolak', out)


class TestToolBackfill(unittest.TestCase):
    def test_dry_run_tidak_menulis(self):
        sql("INSERT INTO customers (name, phone, member_level, price_level, status, created_at) "
            "VALUES ('ZZ Uji Tool 32','0899998888','regular','retail','active',NOW())")
        try:
            before = sql("SELECT COUNT(*) FROM customers WHERE name='ZZ Uji Tool 32' AND marketplace_password_hash IS NULL")
            r = subprocess.run(['php', 'tools/set_default_password.php'], capture_output=True, text=True, env=ENV, cwd=str(ROOT))
            self.assertIn('DRY-RUN', r.stdout)
            self.assertIn('Jalankan ulang dengan --apply', r.stdout)
            after = sql("SELECT COUNT(*) FROM customers WHERE name='ZZ Uji Tool 32' AND marketplace_password_hash IS NULL")
            self.assertEqual(before, after, 'dry-run tidak boleh menulis')
        finally:
            sql("DELETE FROM customers WHERE name='ZZ Uji Tool 32'")


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 32))

    def test_halaman_system_upgrade(self):
        v = read('VERSION').strip()
        self.assertIn(f'v{v}', read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
