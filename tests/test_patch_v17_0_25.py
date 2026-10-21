"""Kontrak test v17.0.25 — fix avg_hpp(), tambah/hapus role, permission Laporan Ongkir Toko.

Jalankan: python3 -m unittest tests.test_patch_v17_0_25 -v
"""
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
TREES = {'legacy': ROOT / 'legacy/public/admin', 'public': ROOT / 'public/admin', 'pos': ROOT / 'pos/public/admin'}
APPS = {'root': ROOT / 'app', 'legacy': ROOT / 'legacy/app', 'pos': ROOT / 'pos/app'}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestAvgHppFallback(unittest.TestCase):
    def test_fallback_ada_di_halaman_pembelian(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'purchases' / 'create.php')
                self.assertIn("if (!function_exists('avg_hpp'))", src,
                              'halaman pembelian bisa fatal bila _helpers.php server masih lama')

    def test_helper_asli_tetap_ada(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                self.assertIn('function avg_hpp(float $oldStock', read(base / 'purchases' / '_helpers.php'))

    def test_rumus_fallback_sama_dengan_helper(self):
        php = ('require "legacy/public/admin/purchases/_helpers.php";'
               '$a=[avg_hpp(0,0,10,300000),avg_hpp(10,300000,10,100000),avg_hpp(5,1000,0,999),avg_hpp(7,0,3,50000)];'
               'echo json_encode($a);')
        r = subprocess.run(['php', '-r', php], capture_output=True, text=True, cwd=str(ROOT))
        self.assertEqual(r.returncode, 0, r.stderr[-300:])
        import json
        self.assertEqual(json.loads(r.stdout.strip()), [300000, 200000, 1000, 50000])


class TestRoleCrud(unittest.TestCase):
    def test_halaman_create_dan_delete(self):
        for tree, base in TREES.items():
            for page in ('create.php', 'delete.php'):
                with self.subTest(tree=tree, page=page):
                    p = base / 'roles' / page
                    self.assertTrue(p.is_file(), f'{tree}: {page} tidak ada')
                    src = read(p)
                    self.assertIn("Auth::requirePermission('roles.manage')", src)
                    self.assertIn('CSRF::verify()', src)

    def test_create_menolak_slug_sistem(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'roles' / 'create.php')
                self.assertIn("$reserved = ['super_admin', 'owner'];", src)
                self.assertIn('INSERT INTO roles', src)
                self.assertIn('INSERT IGNORE INTO role_permissions', src)

    def test_delete_lindungi_role_sistem_dan_role_terpakai(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'roles' / 'delete.php')
                self.assertIn("['super_admin', 'owner']", src)
                self.assertIn('FROM users WHERE role_id', src, 'role yang masih dipakai user bisa terhapus')
                self.assertIn('DELETE FROM role_permissions', src)

    def test_index_punya_tombol_tambah_dan_hapus(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'roles' / 'index.php')
                self.assertIn("admin/roles/create.php", src)
                self.assertIn("admin/roles/delete.php", src)
                self.assertIn("in_array($role['slug'], ['super_admin', 'owner'], true)", src)


class TestPermissionOngkir(unittest.TestCase):
    def test_katalog_punya_permission_ongkir(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'roles' / '_helpers.php')
                self.assertIn("'courier_income.view' => ['Laporan Ongkir Toko'", src)
                self.assertIn("'courier_income.export'", src)
                self.assertIn("'Marketplace & Kurir'", src)

    def test_topup_dijalankan_walau_tabel_terisi(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'roles' / '_helpers.php')
                m = re.search(r'\$count > 0\) \{(.{0,200}?)return;', src, re.S)
                self.assertIsNotNone(m)
                self.assertIn('owner_permission_topup($db);', m.group(1),
                              'permission baru tidak masuk ke owner bila tabel sudah terisi')

    def test_halaman_ongkir_pakai_permission(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                idx = read(base / 'marketplace-courier-income' / 'index.php')
                self.assertIn("Auth::requirePermissionOrRole(['super_admin','owner'], 'courier_income.view')", idx)
                exp = read(base / 'marketplace-courier-income' / 'export.php')
                self.assertIn("'courier_income.export'", exp)

    def test_auth_punya_require_permission_or_role(self):
        for tree, app in APPS.items():
            with self.subTest(tree=tree):
                self.assertIn('function requirePermissionOrRole', read(app / 'Core' / 'Auth.php'))

    def test_legacy_punya_alias_redirect(self):
        """Auth legacy memanggil redirect(); legacy tree hanya punya legacy_redirect()."""
        src = read(ROOT / 'legacy/app/Helpers/helpers.php')
        self.assertIn('function redirect(string $path)', src)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 25))

    def test_halaman_system_upgrade(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)