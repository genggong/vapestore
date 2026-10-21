"""Kontrak test v17.0.33 — nolkan stok cabang + switcher cabang global (POS & laporan ikut).

Jalankan: python3 -m unittest tests.test_patch_v17_0_33 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
WEB = {'legacy': 'legacy/public', 'public': 'public', 'pos': 'pos/public'}
ASSETS = ['assets/css/admin.css', 'legacy/public/assets/css/admin.css', 'public/assets/css/admin.css',
          'pos/public/assets/css/admin.css']
ENV = dict(os.environ, VAPEKONG_DB_HOST='127.0.0.1', VAPEKONG_DB_PORT='3306',
           VAPEKONG_DB_NAME='vapekong_prod', VAPEKONG_DB_USER='vk_test2', VAPEKONG_DB_PASS='VkTest2!Pass2026')


def read(p):
    return (ROOT / p).read_text(encoding='utf-8', errors='replace')


def sql(q):
    r = subprocess.run(['mysql', '-h127.0.0.1', '-uvk_test2', '-pVkTest2!Pass2026', 'vapekong_prod', '-N', '-B', '-e', q],
                       capture_output=True, text=True)
    return r.stdout.strip()


class TestSwitcher(unittest.TestCase):
    def test_endpoint_ada_di_semua_tree(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/admin/switch-branch.php')
                self.assertIn('branch_is_admin()', s)
                self.assertIn("$_SESSION['active_branch_id']", s)

    def test_redirect_disinapkan(self):
        s = read('legacy/public/admin/switch-branch.php')
        self.assertIn("strpos($back, '//') === 0", s)
        self.assertIn('switch-branch.php', s)

    def test_sidebar_punya_selector(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/layouts/admin-sidebar.php')
                self.assertIn('sidebar-branch', s)
                self.assertIn('admin/switch-branch.php', s)
                self.assertIn("count($sidebarBranches) > 1", s)

    def test_css_selector_ada(self):
        for a in ASSETS:
            with self.subTest(file=a):
                self.assertIn('.sidebar-branch', read(a))


class TestStokPerCabang(unittest.TestCase):
    def test_stok_cabang_menang(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/admin/pos/index.php')
                blok = re.search(r'function pos_theme_stock_expr.*?\n\}', s, re.S).group(0)
                self.assertIn('COALESCE({$branchAlias}.stock, {$productAlias}.stock)', blok)
                self.assertNotIn('{$productAlias}.updated_at', blok)

    def test_sync_master_hanya_cabang_utama(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/admin/pos/index.php')
                blok = re.search(r'function pos_theme_sync_latest_master_to_branch.*?\n\}', s, re.S).group(0)
                self.assertIn("SELECT id FROM branches WHERE is_main=1", blok)
                self.assertIn('if ($mainId <= 0 || $mainId !== $branchId) return;', blok)

    def test_shift_ikut_cabang(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                self.assertTrue('?int $branchId = null' in read(f'{w}/admin/shifts/_helpers.php'),
                                'shift_active harus menerima branchId')
                self.assertTrue('shift_active($db, (int)Auth::id(), (int)($currentBranch' in read(f'{w}/admin/pos/index.php'),
                                'POS harus memanggil shift_active dengan cabang aktif')


class TestToolNolkanStok(unittest.TestCase):
    def test_dry_run_default(self):
        s = read('tools/reset_branch_stock.php')
        self.assertIn("in_array('--apply', $args, true)", s)
        self.assertIn('DRY-RUN', s)
        self.assertIn('CREATE TABLE `{$bak1}`', s)

    def test_dry_run_tidak_menulis(self):
        sql("UPDATE product_branch_stocks SET stock=5 WHERE branch_id=2 AND product_id=4221")
        try:
            before = sql("SELECT stock FROM product_branch_stocks WHERE branch_id=2 AND product_id=4221")
            r = subprocess.run(['php', 'tools/reset_branch_stock.php', '--branch-name=Slawi'],
                               capture_output=True, text=True, env=ENV, cwd=str(ROOT))
            self.assertIn('DRY-RUN', r.stdout)
            after = sql("SELECT stock FROM product_branch_stocks WHERE branch_id=2 AND product_id=4221")
            self.assertEqual(before, after, 'dry-run tidak boleh mengubah stok')
        finally:
            sql("UPDATE product_branch_stocks SET stock=0 WHERE branch_id=2 AND product_id=4221")

    def test_apply_menolkan_dan_mencatat(self):
        sql("UPDATE product_branch_stocks SET stock=5 WHERE branch_id=2 AND product_id=4221")
        try:
            r = subprocess.run(['php', 'tools/reset_branch_stock.php', '--branch-name=Slawi', '--apply',
                                '--note=Uji 33'], capture_output=True, text=True, env=ENV, cwd=str(ROOT))
            self.assertEqual(r.returncode, 0, (r.stdout + r.stderr)[:400])
            self.assertEqual(sql("SELECT stock FROM product_branch_stocks WHERE branch_id=2 AND product_id=4221"), '0')
            mov = sql("SELECT COUNT(*) FROM stock_movements WHERE reference_type='reset_branch_stock' "
                      "AND product_id=4221 AND note LIKE '%Uji 33%'")
            self.assertGreaterEqual(int(mov or 0), 1)
        finally:
            sql("DELETE FROM stock_movements WHERE reference_type='reset_branch_stock' AND note LIKE '%Uji 33%'")

    def test_cabang_utama_ditolak_tanpa_force(self):
        r = subprocess.run(['php', 'tools/reset_branch_stock.php', '--branch=1'], capture_output=True, text=True,
                           env=ENV, cwd=str(ROOT))
        self.assertNotEqual(r.returncode, 0)
        self.assertIn('--force', r.stdout + r.stderr)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 33))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
