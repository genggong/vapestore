"""Kontrak test v17.0.23 — Nilai modal Dashboard = Akuntansi, dan menu Gudang > Edit Stok.

Jalankan: python3 -m unittest tests.test_patch_v17_0_23 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
TREES = {
    'legacy': ROOT / 'legacy' / 'public' / 'admin',
    'public': ROOT / 'public' / 'admin',
    'pos': ROOT / 'pos' / 'public' / 'admin',
}
MODS = {
    'root': ROOT / 'app' / 'Modules',
    'legacy': ROOT / 'legacy' / 'app' / 'Modules',
    'pos': ROOT / 'pos' / 'app' / 'Modules',
}
SIDEBARS = {
    'legacy': ROOT / 'legacy' / 'public' / 'layouts' / 'admin-sidebar.php',
    'public': ROOT / 'public' / 'layouts' / 'admin-sidebar.php',
    'pos': ROOT / 'pos' / 'public' / 'layouts' / 'admin-sidebar.php',
}

_DB = None
for name in ('vapekong_prod', 'vapekong_pos_test'):
    php = (f'try{{new PDO("mysql:host=127.0.0.1;dbname={name}","vk_test2","VkTest2!Pass2026");echo "OK";}}'
           f'catch(Throwable $e){{echo "NO";}}')
    r = subprocess.run(['php', '-r', php], capture_output=True, text=True)
    if r.stdout.strip() == 'OK':
        _DB = {'VAPEKONG_DB_HOST': '127.0.0.1', 'VAPEKONG_DB_PORT': '3306', 'VAPEKONG_DB_NAME': name,
               'VAPEKONG_DB_USER': 'vk_test2', 'VAPEKONG_DB_PASS': 'VkTest2!Pass2026'}
        break


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestDashboardSamaAkuntansi(unittest.TestCase):
    def test_dashboard_pakai_fungsi_akuntansi(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'dashboard.php')
                self.assertIn('accounting_inventory_value', src,
                              f'{tree}: dashboard belum memakai perhitungan akuntansi')

    def test_fungsi_bersama_ada_di_3_tree(self):
        for tree, mods in MODS.items():
            with self.subTest(tree=tree):
                src = read(mods / 'Accounting' / 'Accounting.php')
                self.assertIn('function accounting_inventory_value', src)
                self.assertIn('product_branch_stocks', src, 'basis cabang hilang')
                self.assertIn('p.purchase_price <= 0', src, 'aturan varian (hb induk 0) hilang')
                self.assertIn('marketplace_product_variant_stocks', src)

    def test_akuntansi_tetap_basis_cabang(self):
        """Basis KPI akuntansi tidak boleh berubah saat penyamaan."""
        for tree, mods in MODS.items():
            with self.subTest(tree=tree):
                src = read(mods / 'Accounting' / 'Accounting.php')
                self.assertRegex(src, r"FROM product_branch_stocks pbs")


@unittest.skipUnless(_DB, 'butuh DB lokal untuk uji runtime nilai persediaan')
class TestRuntimeNilaiSama(unittest.TestCase):
    def test_dashboard_dan_akuntansi_menghasilkan_angka_sama(self):
        env = dict(os.environ, **_DB)
        php = (
            'require "legacy/app/bootstrap.php";'
            'require "legacy/app/Modules/Accounting/Accounting.php";'
            '$db = Database::connect();'
            # panggil fungsi bersama, lalu fungsi dashboard (diextrak dari file halaman)
            '$shared = accounting_inventory_value($db);'
            '$page = file_get_contents("legacy/public/admin/dashboard.php");'
            'preg_match(\'/function dashboard_stock_value\\(PDO \\$db\\): float \\{(.*?)\\n\\}/s\', $page, $m);'
            'eval("function dashboard_stock_value_test(PDO \\$db): float {" . $m[1] . "}");'
            '$dash = dashboard_stock_value_test($db);'
            'echo json_encode(["shared"=>$shared,"dashboard"=>$dash]);'
        )
        r = subprocess.run(['php', '-r', php], capture_output=True, text=True, cwd=str(ROOT), env=env)
        out = r.stdout.strip().splitlines()[-1] if r.stdout.strip() else '{}'
        import json
        data = json.loads(out or '{}')
        self.assertIn('shared', data, f'gagal evaluasi: {r.stderr[-300:]}')
        self.assertAlmostEqual(float(data['shared']), float(data['dashboard']), places=2,
                               msg='nilai dashboard != nilai akuntansi')


class TestEditStokMenu(unittest.TestCase):
    def test_halaman_ada_di_3_tree(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                p = base / 'warehouse' / 'edit-stock.php'
                self.assertTrue(p.is_file(), f'{tree}: halaman edit-stock.php tidak ada')
                src = read(p)
                self.assertIn('warehouse_require_manage()', src, f'{tree}: tanpa guard role')
                self.assertIn('CSRF::verify()', src, f'{tree}: tanpa proteksi CSRF')
                self.assertIn('warehouse_stocks SET stock=', src, f'{tree}: tidak mengubah stok gudang')
                self.assertIn('product_branch_stocks', src, f'{tree}: tidak mengubah stok cabang')
                self.assertIn('marketplace_product_variants SET purchase_price', src, f'{tree}: tidak mengubah modal varian')
                self.assertIn('UPDATE products SET purchase_price', src, f'{tree}: tidak mengubah modal produk')
                self.assertIn('warehouse_record_movement', src, f'{tree}: tanpa riwayat pergerakan gudang')
                self.assertIn("INSERT INTO stock_movements", src, f'{tree}: tanpa riwayat pergerakan cabang')
                self.assertIn('$db->beginTransaction()', src, f'{tree}: tanpa transaksi')
                self.assertIn('$db->rollBack()', src, f'{tree}: tanpa rollback')

    def test_hanya_baris_terisi_yang_diproses(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'warehouse' / 'edit-stock.php')
                self.assertIn('$isFilled', src, f'{tree}: baris kosong ikut diproses (bug lama)')
                self.assertNotIn('SELECT id, name, purchase_price, minimum_stock FROM marketplace_product_variants', src,
                                 f'{tree}: kolom minimum_stock tidak ada di tabel varian')

    def test_menu_di_sidebar_3_tree(self):
        for tree, p in SIDEBARS.items():
            with self.subTest(tree=tree):
                src = read(p)
                self.assertIn("admin/warehouse/edit-stock.php", src, f'{tree}: menu Edit Stok tidak terdaftar')
                self.assertIn("'Edit Stok'", src)

    def test_edit_stok_bisa_ubah_varian(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'warehouse' / 'edit-stock.php')
                self.assertIn('warehouse_set_variant_stock', src)
                self.assertIn('marketplace_v16_set_variant_stock', src)
                self.assertIn('marketplace_v16_sync_parent_stock', src)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 23))

    def test_system_upgrade_ikut_version(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)